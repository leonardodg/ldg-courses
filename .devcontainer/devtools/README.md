# Ferramentas de desenvolvimento

Manifestos das ferramentas que a **imagem de desenvolvimento** instala. Elas
ficam em `/opt/devtools` dentro do container, e os binários entram no `PATH`.

| Ferramenta | Para quê |
|---|---|
| `moodlehq/moodle-cs` | `phpcs` e `phpcbf` com o padrão `moodle` já registrado |
| `tmuras/moosh` | administração do Moodle por linha de comando |
| `grunt-cli` | build de JS e SCSS do Moodle e dos temas |

## Por que não no `composer.json` da raiz

**Aquele arquivo é do upstream do Moodle, byte a byte.** Conferido em
04/09/2026: `git diff upstream/MOODLE_502_STABLE -- composer.json` sai vazio, e
os últimos commits nele são `MDL-86462` e `MDL-86460`.

Acrescentar dependência ali criaria conflito em **toda** sincronização com o
Moodle — o mesmo problema que o `.gitattributes` da raiz já dá, e que obrigou as
regras de fim de linha do projeto a viverem em `.devcontainer/.gitattributes`.

O `Dockerfile` chegou a trazer um comentário pedindo exatamente isso ("adicionar
ao composer.json: moodlehq/moodle-cs"). Nunca foi feito, e o efeito foi pior do
que não ter a nota: a imagem anunciava um `phpcs` que não existia, e quem
precisava dele instalava à mão em `/tmp/cs` — que sumia no próximo container.

## Por que manifesto, e não `RUN composer require ...`

Versão de ferramenta é dependência, e dependência se declara. No manifesto ela
aparece em code review, dá para fixar faixa, e o `composer.lock` daqui — quando
existir — congela o conjunto. Numa linha `RUN` ela vira texto solto que ninguém
revisa e que só se descobre lendo o `Dockerfile`.

## Por que o moosh é a exceção

Ele **não entra no `composer.json`**, e a versão dele vive sozinha em
`moosh.version`. Três tentativas antes de aceitar isso:

1. `moosh/moosh` **não existe no Packagist**.
2. `https://moosh-online.com/moosh.phar` responde **404** (04/09/2026).
3. Requerer `tmuras/moosh` por `repositories: vcs` chega a
   `Could not authenticate against github.com` (a API do GitHub é limitada por
   IP, e build de imagem não tem token). Com `no-api: true` isso passa — e aí
   esbarra no problema real:

   ```
   tmuras/moosh dev-master requires mudrd8mz/moodle-tool_pluginskel dev-main
     -> could not be found in any version
   ```

   O moosh declara **treze** `repositories` inline para dependências que só
   existem no GitHub, e **o composer ignora `repositories` de pacote
   transitivo** — só lê os do pacote raiz. Requerê-lo daqui exigiria copiar as
   treze para o nosso manifesto e vê-las apodrecer na primeira mudança dele.

O projeto do moosh se trata como raiz. Clonar e rodar o `composer install`
*dentro* dele é a forma suportada, e é o que o `Dockerfile` faz — lendo a ref de
`moosh.version`, para a versão continuar sendo dado revisável e não texto perdido
numa linha `RUN`.

**O `prefer-stable: true` está ali de propósito.** Sem ele, o
`minimum-stability: dev` valeria para *todas* as dependências, e o `moodle-cs`
viria de branch de desenvolvimento junto.

## Mexer aqui exige rebuild

As ferramentas vivem na imagem, não no container:

```bash
moodev build
```
