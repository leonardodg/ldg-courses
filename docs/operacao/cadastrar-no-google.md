# Cadastrar a plataforma no Google

O que fazer, na ordem, para a landing pública ser encontrada — e o que **não**
dá para fazer ainda, com o motivo.

> Nada aqui exige deploy. O código já está pronto: o que falta é colar dois
> valores em *Administração do site → Plugins → Plugins locais → Captação de
> parceiros*.

## Antes de tudo: a raiz precisa responder 200

```bash
curl -s -o /dev/null -w '%{http_code} %{redirect_url}\n' https://courses.leodg.dev/
```

`303` apontando para `/login/index.php` significa que **o Painel está
desligado** — *Administração do site → Aparência → Navegação → Painel ativado*.
Com `enablemyhome` vazio, o `index.php` do core manda todo anônimo para o login
e **o `forcelogin` não tem nada a ver com isso**; procurar nas configurações de
login não acha nada, porque não está lá. O porquê, com o trecho do core, está em
[`../dev/seo-canonical-e-hreflang.md`](../dev/seo-canonical-e-hreflang.md).

Enquanto a raiz redirecionar, nada mais nesta página adianta: a canônica, os
`hreflang` e a entrada principal do sitemap apontam todos para lá.

## Depois: o site precisa se declarar indexável

*Administração do site → Segurança → Políticas do site → **Permitir indexação
por buscadores***.

Se estiver em **"em lugar nenhum"**, o Moodle emite `noindex` em toda página e
**nada mais nesta página adianta** — o `index, follow` do plugin nem é emitido,
justamente para não brigar com o do core e perder em silêncio.

## 1. Search Console

1. Entre em `search.google.com/search-console` e escolha **Prefixo de URL**,
   com `https://courses.leodg.dev`.
   > O outro tipo, **Domínio**, verifica por DNS e cobre subdomínios e protocolos
   > de uma vez. É melhor se você tem acesso ao DNS — e aí nada precisa ser
   > configurado no Moodle.
2. Escolha **Tag HTML** como método. O Google mostra algo como
   `<meta name="google-site-verification" content="AbC123...">`.
3. Copie **apenas o valor do `content`**, sem a tag em volta, e cole no campo
   *Código de verificação do Google Search Console*.
4. Volte ao Search Console e clique em **Verificar**.
5. Em *Sitemaps*, envie `sitemap.xml`.

**Se falhar:** confira se o valor foi colado sem a tag, e se a landing está
ligada — a meta só é emitida nas páginas de captação. Veja o código-fonte da
página e procure por `google-site-verification`.

## 2. Google Analytics

1. Crie uma propriedade GA4 e copie o **ID de medição** (`G-XXXXXXXXXX`).
2. Cole no campo *ID de medição do Google Analytics*.

**Duas coisas que o código decidiu por você, e que valem saber:**

- **A tag carrega SÓ nas páginas públicas de captação**, e nunca dentro do LMS.
  Mandar a atividade de um aluno para um terceiro é outra decisão, com outro
  peso; medir visitante que ainda não é cliente é bem menor.
- **Um ID malformado não carrega nada.** O formato é conferido antes: um ID
  errado traria o script do Google para uma página pública sem medir coisa
  alguma.

> **Isto não dispensa aviso de cookies.** A tag grava cookie e envia dados para
> fora, o que sob a LGPD e o GDPR pede base legal e, na prática, banner de
> consentimento. Enquanto o campo estiver vazio, nenhuma tag do Google é
> carregada — e essa é a situação hoje.

## 3. Perfil no Google Negócio — **bloqueado, e por um motivo concreto**

O Google Business Profile exige **um destes dois**:

- um **endereço comercial** que receba clientes, verificado por cartão postal,
  telefone ou vídeo; ou
- uma **área de atendimento** declarada, para negócio que vai até o cliente.

O único endereço que a empresa tem hoje é o do contrato social, e ele é
**residencial** — o apartamento do sócio. Publicá-lo num perfil do Google o
torna público, indexado e permanente, e é por isso que ele também ficou de fora
do `schema.org` (ver `classes/seo.php`).

**Os caminhos, em ordem de preferência:**

1. **Endereço comercial de verdade** — coworking com contrato, escritório
   virtual com comprovante. Resolve o cadastro e destrava também o `address` do
   schema e o `PostalAddress`.
2. **Perfil de área de atendimento, sem endereço visível.** O Google ainda pede
   um endereço na verificação, mas permite ocultá-lo no perfil público. É
   parcial: o endereço fica com o Google, mesmo escondido.
3. **Não fazer.** Google Negócio serve a busca **local** — "curso perto de mim".
   Uma plataforma que atende o Brasil inteiro pela internet ganha pouco com ele,
   e ganha muito mais com o Search Console e com os dados estruturados, que já
   estão no ar.

**Recomendação: 3 por enquanto, 1 quando existir endereço comercial.** Não vale
expor endereço residencial para um canal que não é o canal desta plataforma.

O que ainda falta para o `Organization` ficar completo, quando existir:
endereço comercial, telefone de empresa e e-mail de empresa. Os três estão
vazios em `seo::brand()`, com o motivo escrito ao lado de cada um.

## 4. Velocidade — medido em 10/09/2026

Chrome, viewport de celular (390px), **sem cache**, contra o ambiente local:

| Medida | Valor |
|---|---|
| Tempo até o primeiro byte | 74–94 ms |
| Primeira pintura com conteúdo | 248–264 ms |
| DOM pronto | 387–422 ms |
| Carregamento completo | 513–524 ms |
| Requisições | 27 |
| **Transferido** | **1.805 KB** |

Os tempos são bons. O peso não — e a origem dele importa:

| Recurso | KB | De quem é |
|---|---|---|
| `core/first.js` | 931 | Moodle |
| `yui-moodlesimple.js` | 280 | Moodle |
| CSS do tema | 201 | Moodle + nosso |
| `hero.jpg` | 67 | nosso |
| `react-dom` | 60 | Moodle |
| polyfills | 59 | Moodle |

**1.211 KB são JavaScript do próprio Moodle**, que qualquer página dele carrega
e que não dá para remover. O conteúdo próprio da landing é a imagem do hero e
uma fatia do CSS.

**O que foi feito:** a compressão estava desligada no nginx de produção, e foi
ligada. Texto comprime em torno de 70%, então os 1.211 KB de JavaScript devem
cair para uns 350 — de longe o maior ganho disponível, maior que qualquer
otimização de CSS ou de imagem.

**Confira depois do deploy:**

```bash
curl -sI -H 'Accept-Encoding: gzip' https://courses.leodg.dev/lib/requirejs.php/-1/core/first.js \
  | grep -i content-encoding
```

Sem `content-encoding: gzip` na resposta, a compressão não pegou.

**O que ainda pode ser feito, se o número não bastar:** converter a imagem do
hero para WebP ou AVIF (67 KB → uns 25), e conferir se a Cloudflare está com
Auto Minify e Brotli ligados no painel.

## 5. Palavras-chave

**O Google ignora `<meta name="keywords">` desde 2009**, e nós não escrevemos
nenhuma. A que aparece no código-fonte é do **core do Moodle**, que emite
`<meta name="keywords" content="moodle, {título da página}" />` em toda página
(`lib/classes/output/core_renderer.php:204`). Não é nossa, não carrega estratégia
nenhuma, e não vale brigar com o core para removê-la.

O que carrega a palavra-chave, e onde ela está:

| Onde | Conteúdo |
|---|---|
| `<title>` | "Venda seus cursos online sem construir uma plataforma" |
| `<meta description>` | publicar, vender curso online, sem mensalidade, conta própria, comissão |
| `<h1>` | o mesmo argumento, em duas linhas |
| `<h2>` das seções | por que vender aqui, planos, como funciona, perguntas |
| `FAQPage` (JSON-LD) | as perguntas reais, em forma de dado citável |
| `hreflang` | as três versões de idioma, sem competir entre si |

Para mudar qualquer texto, edite as strings em
`public/local/partners/lang/{en,pt_br,es}/local_partners.php`. Nada de texto de
marketing está escrito dentro de template.
