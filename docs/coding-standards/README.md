# Padrão de código

Hierarquia do que vale, de cima para baixo: **Moodle (template) → `moodle-cs` →
este projeto → PSR-12 → PSR-1**. Quando duas camadas conflitam, a de cima vence.

O que o CI cobra está em
[`.github/workflows/deploy.yml`](../../.github/workflows/deploy.yml)
(`moodle-plugin-ci phpcs --standard moodle-extra --max-warnings 0`). O que não
dá para automatizar está aqui.

## O standard

| Camada | Onde | O que impõe |
|---|---|---|
| Moodle | `docs/dev/padrao-de-implementacao.md`, guia oficial | estrutura de plugin, `classes/`, naming de componente |
| `moodle-cs` (`moodle`) | `phpcs.xml.dist` | o phpcs do Moodle |
| **`moodle-extra` (este projeto)** | `.phpcs.xml` + CI | `moodle` **mais** visibilidade de constante, `dataProvider` e afins |
| PSR-12 / PSR-1 | regras embutidas no `moodle-cs` | layout de arquivo, `namespace`/`use` |

**PHP:** `>= 8.3.0` (declado em `version.php` e no `testVersion` do
`phpcs.xml.dist`). Constantes de classe **sempre** com visibilidade
(`public const` / `private const` / `protected const`) — o
`moodle-extra` reprova sem.

**Limites de linha:** 132 (erro) e 180 (erro absoluto), do `moodle-cs`. Não
"invente" limite próprio.

**Classes** ficam em `classes/`, um namespace por componente, PSR-4.

### Rodar

```bash
# na raiz da worktree — usa .phpcs.xml (moodle + moodle-extra + excludes)
docker exec -u 1000:33 -w /var/www/html ldg-courses-moodle-1 \
  phpcs -p --report=summary public/<caminho>

# ou o standard explícito, como o CI
docker exec -u 1000:33 ldg-courses-moodle-1 \
  phpcs --standard=moodle-extra -p --report=summary public/<caminho>
```

**Leia o total do `phpcs`.** `tail -3` na saída esconde o relatório: já se
reportou "zero violações" com 16 erros presentes, e o CI reprovou.

```bash
… phpcs --standard=moodle-extra --report=summary <caminho> | grep -E "A TOTAL OF"
```

Saída vazia com `-p` = limpo; confirme com o `A TOTAL OF` (ou ausência dele).

`phpcs.xml` na raiz é **gerado** pelo `npx grunt ignorefiles` a partir de
`phpcs.xml.dist` e está no `.gitignore`. A escolha versionada do projeto é
`.phpcs.xml`, que **estende** o `.dist` com `moodle-extra`. Não versione
`phpcs.xml`.

**Nunca rode `phpcbf` global.** Corrija por arquivo e linha exatos.

## O que já custaram tempo

**Strings de idioma têm ordem alfabética obrigatória.** Inserir por âncora quebra
o `phpcs`; reordene o arquivo inteiro depois de acrescentar. Os idiomas cobertos
são `en`, `pt_br` e `es`.

**Comentários e mensagens de commit em português, sem acentos.** Prosa de
documentação leva acentuação normal.

**Identificador de código (variável, propriedade, parâmetro, método, classe) é
sempre em inglês.** Só o comentário ao lado é português. A base inteira foi
convertida em 23/09/2026, plugin por plugin — não há mais identificador em
português conhecido; histórico e método em
`docs/dev/identificadores-em-ingles.md`. Identificador em português que
apareça daqui pra frente é regressão, não decisão pendente. Confuso antes
disto: uma rodada de code review em 23/09/2026 chegou a reverter arquivo já
em inglês para português por interpretar mal esta regra — não repita.

**Nada de regex cego em comentários.** Um padrão que capitaliza `// texto`
também pega a segunda linha de comentários multi-linha — já corrompeu o cabeçalho
GPL de 74 arquivos. Corrija por arquivo e linha exatos.

## Fim de linha

O repositório guarda **61.209 arquivos em LF e 336 em CRLF**. Os 336 são do
Moodle upstream e devem continuar CRLF: convertê-los cria modificações contra o
upstream que conflitam a cada `git merge upstream/MOODLE_502_STABLE`.

**Não use `dos2unix` no projeto.** Ele não sabe a diferença e converteria os 336
junto. Quem sabe é o git:

```bash
git ls-files --eol | awk '{print $1}' | sort | uniq -c    # o que o repo guarda
git ls-files --eol | awk '{print $2}' | sort | uniq -c    # o que está no disco
```

Se as duas contagens divergirem, o conserto é mandar o git reescrever o working
tree a partir do índice — **não** converter arquivo por arquivo:

```bash
git config --global core.autocrlf false   # a causa, se ainda estiver ligada
git rm --cached -r -q . && git reset --hard -q
```

> `git reset --hard` descarta alteração não commitada. Confira `git status`
> antes; arquivos não rastreados não são afetados.

Para os caminhos onde CRLF quebra de verdade — o `ENTRYPOINT` da imagem e a man
page — há `.gitattributes` com `eol=lf` em `.devcontainer/` e em `docs/`. O
atributo tem precedência sobre a configuração da máquina, então vale para
qualquer clone. **O `.gitattributes` da raiz é do Moodle upstream e não deve ser
tocado.**
