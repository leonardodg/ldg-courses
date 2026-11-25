# Plataforma de cursos gratuitos e pagos — Plano de arquitetura e implementação

## 1. Visão geral

Uma plataforma baseada em Moodle (aproveitando o setup `gitworktree-bare-moodle` já existente) onde professores/vendedores publicam cursos gratuitos ou pagos, cada um com sua "empresa" (link customizado, tema, CNPJ opcional), e as vendas de conteúdo pago são divididas automaticamente entre professor e plataforma via Mercado Pago.

Este documento assume que a base técnica é **Moodle `MOODLE_502_STABLE`**, gerenciado com o fluxo de bare repo + worktrees descrito no seu `README.md`, e propõe reaproveitar o projeto `/home/leodg/ivana-academy` como ponto de partida (ver seção 10 — preciso de mais informação sobre o que já existe lá).

---

## 2. Requisitos consolidados

| Área | Requisito |
|---|---|
| Conteúdo gratuito | Vídeo hospedado fora da plataforma (YouTube/Vimeo não listado, etc.) |
| Conteúdo pago (fora da plataforma) | Plataforma retém 25% do valor da venda |
| Conteúdo pago (dentro da plataforma) | Modelo de cobrança ainda em aberto — depende de espaço em `moodledata` e banda por aluno |
| Pagamentos | Split automático via Mercado Pago, um vendedor = uma conta Mercado Pago vinculada |
| Perfil professor/vendedor | Link customizado (`meuscursos.joao.com`), tema customizado, empresa (CNPJ opcional) |
| Tema | Tema próprio para captação de novos professores/empreendedores |

---

## 3. Decisão de arquitetura: multi-tenant em Moodle

O conceito de "empresa" com tema, link e vínculo de professores é exatamente o que soluções de **multi-tenancy** do Moodle resolvem. Três caminhos existem hoje: IOMAD, Moodle Workplace e tool_mutenancy (MuTMS).

### 3.1 Como cada um guarda os dados

- **IOMAD**: é uma "distro" do Moodle (core levemente alterado + plugins), não um plugin puro. Adiciona tabelas próprias — `mdl_company`, `mdl_company_users`, `mdl_department`, `mdl_companytheme`, `mdl_iomad_courses`, entre outras — que referenciam os IDs padrão do Moodle (`userid`, `courseid`). O isolamento entre empresas é aplicado **no código** do IOMAD (as queries filtram por `companyid`), não é uma trava estrutural do banco.
- **Moodle Workplace** (`tool_tenant`): a tenancy é nativa do core — o campo `tenantid` é adicionado diretamente nas tabelas centrais (usuários, categorias de curso, etc). O isolamento é imposto num nível mais baixo, respeitado automaticamente por qualquer ferramenta "tenant-aware" da Workplace.
- **MuTMS** (`tool_mutenancy`): também exige um patch no core (não é plugin puro), estrutura parecida com a da Workplace, mas os próprios mantenedores afirmam que não pretendem implementar domínio próprio por tenant — "Moodle não foi desenhado pra isso".

### 3.2 Comparação

| | IOMAD | Moodle Workplace | MuTMS |
|---|---|---|---|
| Custo | Grátis | Licença paga (Moodle HQ) | Grátis |
| Instalação | Distro própria (core + plugins) | Core Moodle + módulo comercial | Patch de core + plugin |
| Isolamento de dados | Por código/query (`companyid`) | Nativo (`tenantid` no core) | Nativo, mas rotulado experimental |
| Domínio próprio por empresa | Sim — mas exige configurar "per-tenant URLs" manualmente, não é padrão de fábrica | Sim, nativo, pronto | Não — descartado pelos mantenedores |
| Tema por empresa | Sim, nativo (fora dos 3 temas prontos do IOMAD, exige ajuste manual de CSS/logo) | Sim, nativo, branding completo | Sim |
| Ecommerce/pagamento nativo | Sim — bloco próprio com conta de pagamento por tenant, funciona com qualquer gateway suportado pelo Moodle | Focado em treinamento corporativo, não em venda | Não |
| Maturidade | +10 anos, comunidade ativa | Produto oficial, mas pago | "Experimental" na diretoria de plugins |
| Risco no upgrade do core | Médio — depende da distro IOMAD acompanhar o Moodle oficial | Baixo — mantido pela própria Moodle HQ | Alto — patch manual a cada versão |

### 3.3 Como isso cai no seu Docker + reverse proxy (VPS Oracle)

O ponto central, independente de IOMAD ou Workplace: é **um único container Moodle** rodando — não um container por professor. O reverse proxy não precisa saber nada sobre "empresa", só faz duas coisas:

1. Resolve DNS wildcard (`*.suaplataforma.com`) + os domínios próprios dos professores (`meuscursos.joao.com`), todos apontando pro IP do VPS.
2. Termina TLS e encaminha a requisição pro container Moodle, preservando o header `Host`.

Dentro do Moodle, o **IOMAD lê esse `Host`** e decide qual empresa/tema mostrar — configurado por empresa na tela de edição da empresa (campo de URL customizada), não no proxy. Resultado: 1 VPS, 1 container Moodle, 1 banco, N domínios roteados só pelo cabeçalho Host — leve e barato, e é literalmente o que proxies como Traefik/nginx-proxy/Nginx Proxy Manager já fazem bem (mapear host → upstream), sem duplicar a aplicação.

Compare com a alternativa "um Moodle inteiro por professor": mais isolado, mas cada professor vira container + banco próprio, multiplicando custo de VPS e manutenção. Nenhuma das três opções trabalha assim — todas assumem uma instalação compartilhada.

### 3.4 Onde entra o Mercado Pago nisso

O bloco de ecommerce do IOMAD já lida com conta de pagamento por tenant e "qualquer gateway suportado pelo Moodle" — vale investigar se dá pra plugar um gateway Mercado Pago ali em vez de escrever o `local_mpsplit` (seção 4) 100% do zero. Mas a lógica de split (`marketplace_fee`/`application_fee`, OAuth por vendedor) é uma feature específica do Mercado Pago que dificilmente um plugin genérico de gateway Moodle já resolve pronta — isso continua sendo trabalho customizado, independente da escolha entre IOMAD/Workplace.

**Recomendação:** IOMAD. O modelo de "empresa com CNPJ opcional + tema + link" já é literalmente o objeto de primeira classe do IOMAD (`company`), e a arquitetura de container único bate com o proxy que você já vai manter. A única "dívida técnica" real é configurar `per-tenant URLs` — que é config, não código novo, e complementa o roteamento de domínio que o proxy já faz pela metade.

---

## 4. Split de pagamento com Mercado Pago

O Mercado Pago tem uma solução dedicada de **Split de Pagamentos / Marketplace** que resolve exatamente o cenário de repassar comissão automaticamente a cada professor.

Pontos técnicos confirmados na documentação:

- Cada vendedor precisa **vincular a conta Mercado Pago via OAuth** — o professor autoriza a plataforma, que recebe um `access_token` daquela conta e passa a poder processar pagamentos em nome dele.
- A divisão pode ser **percentual, valor fixo ou híbrida**.
- No **Checkout Pro**, a comissão da plataforma vai no parâmetro `marketplace_fee`; no **Checkout API/Transparente/Bricks**, o parâmetro equivalente é `application_fee`.
- A ordem de dedução é fixa: primeiro sai a taxa do próprio Mercado Pago, depois a comissão da plataforma sobre o valor restante.
- O `access_token` de cada vendedor **expira em torno de 6 meses** e precisa de rotina de renovação — sem isso, o repasse automático para aquele professor para de funcionar.
- O saldo dividido fica **dentro de contas Mercado Pago**; o professor ainda precisa sacar/transferir para o banco dele por fora — o split não faz TED direto para conta bancária externa.

### Plugin proposto: `local_mpsplit` (nome sugestivo)

Responsabilidades:
1. Tela de "conectar Mercado Pago" no perfil do professor/empresa (fluxo OAuth).
2. Armazenar `access_token`, `refresh_token`, validade e status por empresa/vendedor.
3. Ao gerar cobrança de um curso pago, montar a preferência de pagamento com `marketplace_fee`/`application_fee` de acordo com a regra:
   - 25% se o conteúdo está hospedado fora da plataforma;
   - percentual/plano a definir se hospedado dentro (ver seção 6).
4. Job agendado (cron do Moodle) para alertar/renovar tokens perto do vencimento.
5. Relatório por empresa: bruto, taxa Mercado Pago, comissão da plataforma, líquido — o Mercado Pago já oferece um relatório de vendas do split via API para alimentar essa tela.

---

## 5. Modelo de negócio — pendências a decidir

Você já identificou corretamente que falta fechar o modelo para conteúdo pago **hospedado dentro** da plataforma. Como ponto de partida para essa decisão, considere:

- **Custo variável real**: armazenamento em `moodledata` cresce linearmente com número de cursos/vídeos, e banda de streaming cresce com número de alunos assistindo — isso é o oposto de uma comissão fixa de 25%, que não escala com esse custo.
- Duas rotas comuns para não deixar o custo de infraestrutura "solto":
  1. **Plano de parceria por assinatura** (mensalidade fixa por professor, com cota de armazenamento/banda, e comissão reduzida por venda).
  2. **Comissão maior + limite técnico** (ex.: vídeo até X minutos/GB por curso, comissão de 30-35% ao invés de 25%, sem mensalidade).
- Tecnicamente, vale considerar **não** guardar o vídeo bruto em `moodledata` mesmo para conteúdo "hospedado na plataforma" — usar um serviço de streaming (Bunny Stream, Mux, Vimeo API privado) apenas com a cobrança repassada ao aluno pela plataforma, mantendo o Moodle livre do peso de arquivo e do custo de banda direto.

Este ponto fica marcado como **decisão de negócio em aberto** — o plano técnico (plugin de matrícula + split) é o mesmo nos dois casos, muda só o parâmetro de comissão e a política de armazenamento.

---

## 6. Modelo de dados complementar

Além das tabelas nativas do IOMAD (`company`, `company_users`, `companytheme`, etc.), sugestão de tabelas locais:

```
local_mpsplit_seller
  id, companyid, userid, mp_user_id, access_token, refresh_token,
  token_expires_at, oauth_status, created_at

local_mpsplit_sale
  id, courseid, companyid, buyerid, amount, mp_payment_id,
  hosting_type (external|platform), commission_pct, status, created_at

local_mpsplit_settings
  id, companyid, commission_pct_external, commission_pct_platform,
  partnership_plan_id, cnpj (nullable)

local_company_domain
  id, companyid, subdomain, custom_theme_id
```

---

## 7. Tema exclusivo (captação de professores/empreendedores)

Além do tema "produto" (usado pelos alunos dentro de cada empresa), sugiro tratar o tema de captação como um **site institucional separado** (landing page própria, fora do Moodle, ou tema de frontpage dedicado), focado em conversão: "crie seus cursos grátis ou pagos, receba automaticamente". Ele não precisa herdar as mesmas regras de customização por empresa — é o seu funil de vendas, não o produto entregue ao professor.

---

## 8. Fluxo de trabalho Git (reaproveitando o padrão atual)

O padrão de bare repo + worktree que você já usa em `gitworktree-bare-moodle` funciona bem para isolar plugins como features:

```
cd ~/localhost/gitworktree-bare-moodle
git worktree add feature-mpsplit -b feature/plugin-mercadopago-split dev
git worktree add feature-iomad-setup -b feature/iomad-empresa dev
```

Recomendação: tratar `local_mpsplit`, a configuração do IOMAD e o tema de captação como **plugins/temas Moodle formais** (não hacks de core) — isso preserva a possibilidade de continuar puxando `git pull` do Moodle oficial (`upstream/MOODLE_502_STABLE`) sem conflito, exatamente como o Passo 1 do seu fluxo já prevê.

---

## 9. Roadmap sugerido

| Fase | Entrega |
|---|---|
| 0 — Fundação | Subir `MOODLE_502_STABLE`, instalar IOMAD, validar tema base |
| 1 — MVP de vendas | `local_mpsplit` com OAuth + split funcionando para conteúdo hospedado fora (25% fixo), rodando ponta a ponta para uma empresa |
| 2 — Multi-empresa | Onboarding self-service de professores, link customizado por empresa, CNPJ opcional, tema por empresa |
| 3 — Conteúdo hospedado na plataforma | Fechar modelo de cobrança (seção 5) e implementar cota/streaming |
| 4 — Captação | Tema/landing de captação de novos professores + polimento geral |

---

## 10. Preciso de você para avançar

Não tenho acesso ao seu computador — trabalho num ambiente isolado, então não consigo abrir `/home/leodg/ivana-academy` diretamente. Para reaproveitar o que já existe lá, me ajuda com um dos dois caminhos:

1. **Me manda os arquivos-chave aqui no chat** (pode subir só o essencial): `composer.json`, lista de plugins instalados em `/plugin`, o README/config do devcontainer, e a versão do Moodle usada. Eu leio e ajusto este plano ao que já existe.
2. **Continua a implementação com o Claude Code**, que roda localmente e enxerga seus arquivos de verdade — eu sigo te ajudando no planejamento aqui, mas a parte de "abrir o ivana-academy e escrever código nele" precisa acontecer onde o Moodle real está.
