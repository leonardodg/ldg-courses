> **Situação:** executado · **Início:** 2026-09-04
> **Origem:** `~/.claude/plans/lovely-wandering-scott.md` — copiado literalmente, sem edição do corpo.
> **Resultado:** PR #68. Três coisas mudaram durante a execução, e estão registradas lá:
> os mockups do portal entraram (pedido no meio do caminho), o `portal-aluno-mobile.html` foi
> barrado por trazer credenciais, e a varredura das transcrições achou **mais dois planos**
> que este plano não previa — o de 31/08, sobrescrito, e o plano original do `cf`.

# Salvar os planos no projeto, apontar o `plansDirectory` e limpar as worktrees

## Contexto

O `docs/ai-plans/` existe para que a próxima sessão não reabra discussão já
encerrada. Hoje ele tem buracos: **quatro planos executados**, **um descartado** e
**dois inacabados** vivem só em `~/.claude/plans/` e numa worktree não rastreada
— fora do git, invisíveis para quem clona. E o `mod_video`, pedido em 03/09,
existe apenas como memória do agente.

A causa é de configuração, e não de disciplina: o Claude Code grava plano em
`~/.claude/plans/` por padrão, fora do projeto. Enquanto isso não mudar, todo
plano novo continua nascendo fora do git.

**Regra do usuário, dada nesta sessão:** *não reescrever plano nenhum*. Os
arquivos são **copiados como estão**; o que se acrescenta é um cabeçalho de
quatro linhas com o resultado, que é o que o `README.md` do `ai-plans` exige na
coluna "Resultado". Nada de reconstruir prosa perdida.

**Levantado antes de planejar:**

- O plano do `mod_video` **não se perdeu** — nunca foi documento. Virou a memória
  `plugin-futuro-mod-video.md`, completa: o recorte (YouTube por iframe, **só
  contas free**), a decisão de **dois plugins** (o pago, com Bunny, é outro), e o
  achado de que `local_marketplace\plan::max_resolution_for()` não tem consumidor
  de produção.
- O plano da `leodg-academy` tem **46 passos, zero marcados**: nunca executado. E
  foi superado — desenhava um tema *filho do Moove*, e o PR #64 fez o `theme_ldg`
  deixar de depender do Moove. Entra como **descartado**, que é justamente o que
  o README manda registrar.
- A `dev` local está **atrás da `origin/dev`** (não tem os merges dos PRs #66 e
  #67) e ao mesmo tempo **à frente**, com 2 commits do `cf` sem push.
- A sessão `session_01Av7hXEHNDCxd9FQKuUod4x` que você apontou é a transcrição
  `b2d6be81-…`: é dela que saem esses dois commits do `cf` e os planos 3 a 5 do
  portal. O plano em arquivo dela é o `optimized-napping-pike.md`.
- **Os dois inacabados dos gateways existem em arquivo, e não se perderam.** O
  `vast-splashing-pascal.md` tem a **Fase 2 `paygw_pagarme` inteira, nunca
  executada** — é o plano que o CNPJ trava — e mais dois itens "Em aberto" que
  são do `paygw_mercadopago`: cifrar os tokens, hoje em texto puro no
  `payment_gateways.config`, e recorrência com split. O `snappy-petal.md` é o
  plano fundador do marketplace, onde o `paygw_mercadopago` foi construído (PRs
  #27 a #48). Nenhum dos dois está no repositório: o
  `docs/history/2026-08-24-plano-inicial.md` **não é** o mesmo arquivo (166
  linhas contra 267), e o `docs/ai-plans/2026-08-27-gateways-asaas-e-pagarme.md`
  é o registro do ciclo, não o plano.

---

## Parte 1 — Sincronizar a `dev` (pré-requisito)

Sem isso o ramo novo nasce atrás e o PR vem sujo de merge.

```bash
git -C dev fetch origin
git -C dev rebase origin/dev     # traz os merges dos PRs #66/#67
git -C dev push origin dev
```

Os dois commits reaplicados são `d9105028e53` (`fix(cf)`) e `47feeed8dfb`
(`docs(cf)`). Se conflitar, **parar e mostrar**: eles só tocam
`.devcontainer/bin/cf`, e conflito ali significa que a `origin/dev` andou por um
caminho inesperado.

## Parte 2 — A configuração, para nenhum plano mais nascer fora do projeto

Não precisa de hook: existe a chave nativa **`plansDirectory`** — *"Custom
directory for plan files, relative to project root. If not set, defaults to
`~/.claude/plans/`"*.

**Arquivo versionado, na raiz do repositório** (`.claude/settings.json`, hoje
inexistente — vai no mesmo ramo da Parte 3):

```json
{
  "plansDirectory": "docs/ai-plans"
}
```

Versionado de propósito: assim ele vale em **toda worktree** e sobrevive a um
clone novo. A sessão que rodar dentro de qualquer worktree grava o plano direto
em `docs/ai-plans/`.

**Um segundo arquivo, este fora do git**, em
`/home/leodg/localhost/gitworktree-bare-moodle/.claude/settings.json` — a pasta
que contém as worktrees não é worktree nenhuma, e uma sessão aberta ali (como
esta) não enxerga o `.claude/` do repositório:

```json
{
  "plansDirectory": "dev/docs/ai-plans"
}
```

Aponta para a worktree de repouso, que sempre existe. Sem ele, uma sessão aberta
na pasta-mãe criaria um `docs/` solto fora do git — exatamente o problema que
este plano está resolvendo.

**Uma consequência a documentar, não a combater:** o Claude Code nomeia plano com
nome gerado (`lovely-wandering-scott.md`), e o `README.md` do `ai-plans` pede
`AAAA-MM-DD-titulo-em-kebab-case.md`. Acrescentar um parágrafo curto ao README
dizendo que o arquivo **chega com nome gerado e é renomeado para a convenção**
quando o trabalho é registrado no índice. Renomear na chegada quebraria o arquivo
que a sessão em curso ainda está editando.

## Parte 3 — Os sete planos, copiados como estão

Trabalho na worktree **`docs-organize-and-improve`** (fica de pé), em ramo novo
tirado da `origin/dev` já atualizada. Os 17 arquivos hoje no índice viajam junto
no `checkout -b` — nenhum existe na `dev`, então não há conflito.

```bash
git -C docs-organize-and-improve checkout -b docs/planos-executados-e-mod-video origin/dev
```

| Arquivo novo em `docs/ai-plans/` | Origem, copiada literal | Resultado a registrar |
|---|---|---|
| `2026-08-24-plataforma-marketplace-e-mercadopago.md` | `~/.claude/plans/vamos-implementar-solucao-proposta-snappy-petal.md` | **executado, com pontas soltas** — plano fundador; `paygw_mercadopago` entregue nos PRs #27 a #48 |
| `2026-08-24-leodg-academy-tema-filho-do-moove.md` | `leodg-academy/docs/superpowers/plans/2026-08-24-…-implementation.md` | **descartado** — 46 passos, nenhum executado; superado pelo PR #64 |
| `2026-08-24-leodg-academy-tema-spec.md` | `leodg-academy/docs/superpowers/specs/2026-08-24-…-design.md` | a spec que acompanha o descartado |
| `2026-08-27-plano-original-asaas-e-pagarme.md` | `~/.claude/plans/vast-splashing-pascal.md` | **inacabado** — Fase 0 e Fase 1 entregues (PRs #53 a #59); **Fase 2 `paygw_pagarme` travada no CNPJ**; dois "Em aberto" do `paygw_mercadopago` |
| `2026-09-02-format-ldg-e-fechamento-do-tema.md` | `~/.claude/plans/shiny-dreaming-popcorn.md` | **executado** — PR #65, `format_ldg` em `MATURITY_BETA` |
| `2026-09-03-cf-new-guarda-de-versao.md` | `~/.claude/plans/optimized-napping-pike.md` | **executado** — commits `d9105028e53` e `47feeed8dfb`, mais o *fast-forward* que destravou a `fix-theme-ldg` |
| `2026-09-03-mod-video-youtube.md` | memória `plugin-futuro-mod-video.md` | **pendente** — não começou |

Nome do plano dos gateways escolhido para **não colidir** com o
`2026-08-27-gateways-asaas-e-pagarme.md`, que já existe e é o *registro do ciclo*
— coisa diferente do plano. Os dois se referenciam: o registro ganha uma linha
apontando para o plano original, e é ali que a Fase 2 do Pagar.me está escrita
por inteiro, esperando o CNPJ.

O cabeçalho acrescentado é só isto: situação, data, arquivo de origem, e o commit
ou PR que executou. **O corpo não se toca** — nem para corrigir, nem para
"melhorar".

O `mod_video` é o único sem arquivo de origem: a memória vira o corpo, sem
conteúdo novo. Ela já traz as ligações que importam — o `format_ldg` como
consumidor natural, o MDLCode Wizard para o esqueleto, e a
`0005-trava-de-resolucao-por-ticket.md` como assunto do **outro** plugin.

**Índice** — `docs/ai-plans/README.md` ganha as sete linhas, em ordem
cronológica, mais o parágrafo da Parte 2. Uma correção de fato na linha que já
existe: o desenho do portal está marcado *"implementação não começou"*, e ela
começou e terminou (planos 1 a 5, PRs #66 e #67).

**Uma coluna a mais, que é o pedido por trás disto:** a tabela do índice hoje só
diz "entregue" ou "executado". Passa a marcar **`inacabado`** e **`pendente`**,
para que a pergunta *"o que ainda falta?"* se responda lendo o índice — hoje ela
exige abrir cada arquivo. Os três de agora: Pagar.me travado no CNPJ, os dois
"Em aberto" do Mercado Pago, e o `mod_video`.

## Parte 4 — Os 17 arquivos soltos

Dois commits separados, no mesmo ramo:

**Commit da marca** — os 12 arquivos de identidade já no índice (`docs/brand/*`,
`DESIGN_band_LDG.md`, os PNG/JPEG/SVG), mais a regra que os acompanha: a entrada
`/docs/private/` no `.gitignore` e a seção *"O que não pode ser versionado"* em
`docs/README.md`. Esses dois já estão escritos e explicam o porquê — o `origin` é
público e o deploy faz `rsync` de `docs/`.

**Mover para fora do git**, aplicando essa mesma regra recém-escrita:

- `docs/themes.md` → `docs/private/themes.md`. Tem razão social, CNPJ e a
  estratégia de planos de venda. A pasta já existe e já guarda `planos.md` e
  `rascunho.md`.
- `docs/adr/0005-.md` → a linha útil (*"Apenas ideias de planos faturar com o
  Saas"*) vai para `docs/private/planos.md`, e o arquivo é **apagado**: é
  template em branco e **colide de número** com o
  `0005-trava-de-resolucao-por-ticket.md`, que existe e está citado na memória do
  `mod_video`.
- `docs/brand/band_LDG.icon` — decidir na hora: asset de marca entra no commit;
  lixo de exportação fica de fora.

Depois: `git push -u origin docs/planos-executados-e-mod-video` e **abrir o PR
sem merjear**, esperando você.

## Parte 5 — Remover as worktrees fechadas

Só as limpas e sem risco, como você decidiu. **`cf rm`**, e não `git worktree
remove`: ele derruba o stack, reaponta o symlink do `cf` no PATH e avisa de
trabalho não salvo antes de apagar.

```bash
cf rm gateways-nucleo   # offset 1, :8453 — PRs #53 e #58 merjeados, arvore limpa
cf rm paygw-asaas       # offset 2, :8463 — PR #54 merjeado, arvore limpa
cf rm fix-theme-ldg     # offset 4, :8483 — o unico commit dela entrou pelo #66
```

A `leodg-academy` **não está no registro do `cf`** (worktree git pura, sem
stack). Só depois que os dois documentos dela estiverem commitados na Parte 3:

```bash
git worktree remove leodg-academy
git branch -d feature/leodg-academy-theme   # local, sem remota, zero commits
```

**Ficam de pé:** `dev` (worktree de repouso, e o `cf` do PATH aponta para ela),
`format-course-ldg` (stack offset 0, `:8443`, com o banco do curso de teste),
`docs-organize-and-improve` (é onde o PR está sendo feito) e `paygw-pagarme`
(travada no CNPJ, não fechada).

---

## Entrega: a lista de links

Ao terminar, entrego uma lista com o caminho de **cada arquivo salvo e
recuperado** — os 7 planos novos, o `README.md` do índice, os dois
`settings.json`, os arquivos movidos para `docs/private/` e os 12 da marca —
mais o link do PR. Caminho de arquivo aparece clicável no terminal.

## Verificação

| O quê | Como |
|---|---|
| `dev` sincronizada | `git -C dev log origin/dev..HEAD` e `git -C dev log HEAD..origin/dev` ambos vazios |
| Planos no git | `git -C docs-organize-and-improve ls-files docs/ai-plans/` lista os 7 arquivos novos |
| O inacabado está legível | a Fase 2 do `paygw_pagarme` aparece inteira em `2026-08-27-plano-original-asaas-e-pagarme.md`, e o índice a marca `inacabado` |
| Índice sem link quebrado | cada `](…)` do `docs/ai-plans/README.md` aponta para arquivo existente |
| Cópia é cópia | `diff <(tail -n +5 docs/ai-plans/2026-09-03-cf-new-guarda-de-versao.md) ~/.claude/plans/optimized-napping-pike.md` só acusa o cabeçalho |
| `plansDirectory` válido | `jq -e .plansDirectory` nos dois `settings.json` responde o caminho; JSON quebrado desliga **todas** as configurações do arquivo |
| `plansDirectory` de fato ativo | só vale em sessão nova — você abre uma, entra em plano, e o arquivo aparece em `docs/ai-plans/`. Não dá para provar dentro desta sessão |
| Nada sigiloso versionado | `git check-ignore -v docs/private/themes.md` responde com a regra; `git log -p` do ramo não mostra CNPJ nem `0005-.md` |
| Worktrees | `cf ls` mostra só `dev`, `format-course-ldg`, `docs-organize-and-improve` e `paygw-pagarme`; `git worktree list` sem `leodg-academy` |
| Ambiente vivo | `curl -sk -o /dev/null -w '%{http_code}' https://localhost:8443` responde 200 — o stack offset 0 não foi tocado |

## Em aberto, de propósito

- **PRs #61, #62 e #63** (tema, documentação, Behat) não têm arquivo de plano em
  lugar nenhum — foram feitos sem plano em arquivo. Não entram no índice: não há
  o que copiar, e inventar o registro seria a reescrita que você vetou.
- **`upstream/MOODLE_502_STABLE`** foi buscado pela última vez há duas semanas.
  Não sincronizo agora porque nenhuma feature nova nasce aqui — mas vira
  pré-requisito no dia em que o `mod_video` sair do papel.
- O PR fica **aberto, sem merge**, esperando você.
