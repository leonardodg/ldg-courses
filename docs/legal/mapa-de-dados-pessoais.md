# Mapa de dados pessoais

Documento **interno e técnico**: o que a plataforma coleta, onde guarda, por
quanto tempo e quem consegue apagar.

Não é a política de privacidade — é a fonte dela. Quando um dos dois divergir,
**este está certo e a política está desatualizada**, porque este é derivado do
código e verificável nele.

**Atualizado em:** 2026-09-01

---

## Como conferir se este documento ainda é verdade

O Moodle mantém um registro vivo, montado a partir dos `privacy provider` de cada
plugin:

```
/admin/tool/dataprivacy/dataregistry.php
/admin/settings.php?section=privacysettings
```

Qualquer plugin sem provider aparece lá como **"não implementado"**, e o
relatório que o titular recebe fica incompleto **sem ninguém perceber**. Foi o
caso do `enrol_marketplace`, `availability_marketplace` e `block_marketplace`
até 2026-09-01.

```bash
# Todo plugin do projeto tem provider?
for p in local/marketplace local/partners theme/ldg payment/gateway/asaas \
         payment/gateway/mercadopago enrol/marketplace \
         availability/condition/marketplace blocks/marketplace; do
  test -f "public/$p/classes/privacy/provider.php" || echo "SEM PROVIDER: $p"
done
```

---

## O que é coletado, por plugin

### `local_marketplace` — vínculo e acesso

| Tabela | Dado pessoal | Por quê |
|---|---|---|
| `local_marketplace_member` | `userid`, papel na empresa, data | quem responde por uma empresa vendedora |
| `local_marketplace_entitlement` | `userid`, oferta, vigência, status, ciclos pagos | é o **direito de acesso**: sem ele não há como saber o que a pessoa comprou |
| `local_marketplace_sale` | ligada ao `{payments}` do core | o comprador vem do core, não é duplicado aqui |

**Não duplicamos valor, moeda, comprador e data**: isso vive em `{payments}`, do
core, que é a fonte da verdade. Duplicar daria duas versões do mesmo número
financeiro, e duas versões para apagar.

### `local_partners` — candidatura de parceria

A tabela mais sensível do projeto, porque o formulário é **público e anônimo**.

| Dado | Observação |
|---|---|
| `companyname`, `cnpj` | pessoa **jurídica** — não é dado pessoal de quem preencheu |
| `contactname`, `contactemail`, `contactphone` | pessoa natural |
| `message` | texto livre; pode conter qualquer coisa que a pessoa escreveu |
| `country`, `learnersband` | atributos da **operação**, como a razão social — não identificam ninguém |
| `termsaccepted` | **momento** do aceite dos termos, não um booleano |
| `submitterip` | serve **só** ao limite de taxa |
| `userid` | presente apenas quando o envio veio de alguém autenticado |
| `reviewerid` | quem decidiu — dado de um usuário do site |

**O aceite sobrevive ao pedido de exclusão, e é de propósito.** Numa candidatura
aprovada, o esquecimento tira nome, e-mail, telefone, mensagem e IP, mas mantém
`termsaccepted`: ele é o registro de um ato jurídico, e apagá-lo destruiria a
prova de que a empresa consentiu — justamente o documento que a LGPD espera que
exista. `country` e `learnersband` ficam pelo mesmo critério que `companyname` e
`cnpj`: descrevem a operação, não a pessoa.

Candidatura anterior a setembro de 2026 tem os três **nulos**, e assim
permanecem. Retroagir o aceite com `1` inventaria um consentimento que ninguém
deu; com `0`, registraria uma recusa que também não houve.

**Dois regimes diferentes, e a distinção importa:**

| Origem | Tem `userid`? | Aparece na exportação? | Quem apaga |
|---|---|---|---|
| visitante anônimo | não | **não** — não há a quem associar | tarefa `purge_unconfirmed` ou o administrador |
| usuário autenticado | sim | **sim**, é dado pessoal dele | pedido de exclusão do titular |

### `paygw_asaas` e `paygw_mercadopago` — transações

| Dado | Observação |
|---|---|
| `userid`, valor, moeda, status, data | o mínimo para conciliar |
| `asaaspaymentid` / `mppaymentid` | id da transação no gateway |
| `customerid` (Asaas) | id do cliente criado lá |

**A credencial do vendedor não fica na tabela do gateway**: vive cifrada em
`payment_gateways.config`, do core, por ambiente.

### `enrol_marketplace`, `availability_marketplace`, `block_marketplace`

**Nada.** Os três leem o direito de acesso e não guardam dado próprio —
declarado por `null_provider`.

### `theme_ldg`

Apenas a preferência de modo claro/escuro (`dark-mode-on`), que é do core.

---

## Dados que saem da plataforma

Declarados como `add_external_location_link` nos providers — é o que aparece para
o titular como transferência a terceiro.

| Destino | O que vai | Por quê |
|---|---|---|
| **Asaas** | nome, e-mail, CPF do aluno, valor | o Asaas recusa a cobrança sem documento do pagador |
| **Mercado Pago** | valor, moeda, nome do item | criação da preferência de pagamento |
| **Google reCAPTCHA** | quando ligado, dados do navegador do visitante | anti-robô no formulário público |
| **Google Analytics** | quando configurado no tema | métrica de uso |
| **YouTube, Vimeo e demais serviços de vídeo** | **endereço IP e dados do navegador do aluno**, a cada aula de vídeo aberta | o vídeo do plano Free é embed: o player é carregado do serviço, e não daqui |

### O vídeo incorporado é transferência a terceiro, e não é opcional

Esta linha merece parágrafo próprio porque é fácil não enxergá-la: o
`mod_ldgvideo` não copia vídeo nenhum, e por isso mesmo **o navegador do aluno
fala direto com o YouTube** — mandando o IP dele assim que a página carrega,
**antes** de qualquer clique em play.

Não há como servir vídeo do plano Free sem isso: a hospedagem externa é o que
mantém o custo em zero, e é a razão de o plano existir. Então a política de
privacidade precisa **nomear** os serviços de vídeo, e não tratá-los como
opcional.

O que **reduz** o dano, e é recomendado no
[README do plugin](../../public/mod/ldgvideo/README.md): ligar o `nocookie` do
`media_youtube`, em *Plugins → Players de mídia*. Ele não elimina o IP — nada
elimina —, mas corta o cookie de rastreio antes do play.

**As duas linhas do Google são opcionais e nascem desligadas.** Se a política de
privacidade menciona Google, tem que ser condicional — e se elas forem ligadas em
produção, a política precisa ser atualizada **antes**.

---

## Retenção

| Dado | Prazo | Mecanismo |
|---|---|---|
| Candidatura **não confirmada** | `unconfirmedretentiondays`, padrão **7 dias** | tarefa agendada |
| Candidatura na fila, aprovada ou recusada | indefinido | decisão do administrador |
| Direito de acesso | enquanto existir a relação | — |
| Transação | indefinido | obrigação fiscal e de conciliação |
| IP do candidato | junto com a candidatura | — |

**Só a candidatura não confirmada tem prazo automático.** É o caso em que o
titular nunca provou existir: guardar indefinidamente nome, telefone e IP de
alguém que talvez nem exista é o oposto do que a LGPD pede.

O resto **não tem expurgo automático**, e isso é uma lacuna consciente — definir
prazo para transação e direito de acesso é decisão de negócio com implicação
fiscal, e ainda não foi tomada.

---

## Exclusão a pedido do titular

O Moodle roteia o pedido para todos os providers. O que cada um faz:

| Situação | O que acontece |
|---|---|
| Revisor de candidaturas | perde a **autoria** da revisão; a candidatura fica |
| Candidatura **não aprovada** que ele enviou | **apagada por inteiro** |
| Candidatura **aprovada** que ele enviou | a linha fica, **sem** nome, e-mail, telefone, mensagem e IP |
| Vínculo com empresa, direitos, transações | tratados pelos providers correspondentes |

**A candidatura aprovada não some**, e a razão é concreta: existe uma **empresa**
criada a partir dela, com categoria, cursos e vendas. Apagar a linha apagaria a
origem de um vínculo comercial que continua existindo. O que sai é o dado
pessoal; o que fica — razão social, CNPJ, empresa criada — é da pessoa jurídica.

Verificar antes de confiar:

```bash
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  php vendor/bin/phpunit --filter privacy_test
```

---

## Bases legais sugeridas

> ⚠️ **Sugestão técnica, não parecer jurídico.** Precisa de revisão por
> advogado antes de virar política pública.

| Tratamento | Base sugerida (LGPD) |
|---|---|
| Conta, matrícula, acesso ao curso | execução de contrato (art. 7º, V) |
| Transação e conciliação | execução de contrato + obrigação legal/fiscal |
| Candidatura de parceria | procedimentos preliminares de contrato (art. 7º, V) |
| IP para limite de taxa | legítimo interesse (art. 7º, IX) — segurança |
| reCAPTCHA, quando ligado | legítimo interesse — prevenção a fraude |
| Analytics, quando ligado | **consentimento** |

O último é o que mais costuma ser tratado errado: analytics não se sustenta em
legítimo interesse, e hoje **não há banner de consentimento na plataforma**.
Ligar o `googleanalytics` sem isso cria exposição.

---

## Lacunas conhecidas

1. **Sem banner de consentimento** — bloqueia o uso legítimo de analytics.
2. **Sem prazo de retenção** para transação e direito de acesso.
3. **Encarregado (DPO) não definido** — a política precisa de um canal e um nome.
4. **Sem política de retenção de log** (`{logstore_standard_log}` guarda IP e
   ação de todo usuário, e é do core).
5. **Empresas parceiras são operadoras** dos dados dos alunos delas, e não há
   contrato de operador entre a plataforma e elas.

A quinta é a mais séria em escala: quando a plataforma tiver muitas empresas
vendendo, a relação entre controlador e operador precisa estar escrita.
