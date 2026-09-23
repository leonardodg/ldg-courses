# Padrão de código

O que o CI cobra, e as convenções que não dá para automatizar.

## O que já está em outro lugar

O padrão de código do Moodle e como rodar o `phpcs` estão na seção
"Testes e padrão de código" de [`../dev/guia-desenvolvedor.md`](../dev/guia-desenvolvedor.md).

## Regras que já custaram tempo

**Leia o total do `phpcs`.** `tail -3` na saída esconde o relatório: já se
reportou "zero violações" com 16 erros presentes, e o CI reprovou.

```bash
… phpcs --standard=moodle --report=summary <caminho> | grep -E "A TOTAL OF"
```

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
