# Mockups do portal do aluno

As referências que originaram o `format_ldg` e a linguagem visual do `theme_ldg`,
recebidas do usuário em 03/09/2026. É contra estes arquivos que a conferência
visual do [plano 4](../../ai-plans/2026-09-03-portal-plano-4-conferencia-visual.md)
mediu o resultado, e é deles que o [`DESIGN.md`](../DESIGN.md) foi extraído.

Estão versionados porque os planos os citam **pelo nome** e, até agora, quem lesse
o registro não tinha como abrir a referência.

| Arquivo | O que é |
|---|---|
| [`portal-aluno-version-bootstrip-responsive.html`](portal-aluno-version-bootstrip-responsive.html) | **a referência que valeu.** Mockup em Bootstrap 5.3.3 — a mesma versão que o Moodle 5.2 compila em `theme/boost/scss/bootstrap`. Responsivo |
| [`portal-aluno-desktop.png`](portal-aluno-desktop.png) | tela de referência, desktop — 2560×2764 |
| [`portal-aluno-mobile.png`](portal-aluno-mobile.png) | tela de referência, celular — 780×2938 |
| [`portal-aluno-desktop.html`](portal-aluno-desktop.html) | o desenho original em **Tailwind**, antes da tradução para Bootstrap |

## O que ficou fora, e por quê

O `portal-aluno-mobile.html` **não está aqui**. Ele foi para
`docs/private/mockups/`, que o `.gitignore` mantém fora do git.

Ele não é um mockup: é a página do AI Studio **salva com a sessão dentro**, 2,8 MB
de aplicação Google inteira. Uma varredura antes do commit achou lá o endereço de
e-mail da conta Google do dono do projeto, o ID numérico dessa conta, duas chaves
`AIza…` e o que aparenta ser token de sessão. O `origin` é **público**.

Nada se perde com isso: a versão celular do desenho está no
[`portal-aluno-mobile.png`](portal-aluno-mobile.png), e o
`portal-aluno-version-bootstrip-responsive.html` é responsivo — é ele que responde
por celular numa janela estreita, e foi ele que a medição usou.

## Duas armadilhas ao abrir estes arquivos

**Nenhum deles renderiza offline.** Os três HTML puxam CSS de CDN
(`cdn.jsdelivr.net` para o Bootstrap, `cdn.tailwindcss.com` para o Tailwind) e
fontes do Google. Sem rede, abrem sem estilo nenhum e parecem quebrados — não
estão. A medição do plano 4 foi feita com rede, no Chrome, com o mockup e a tela
real no **mesmo** viewport.

**O `.png` não substitui o `.html`.** Comparar a tela contra um PNG mede pixel de
uma captura em escala desconhecida. O que vale é renderizar o mockup no mesmo
navegador e no mesmo viewport da página real, e comparar
`getBoundingClientRect()` com `getBoundingClientRect()`.

## O que não veio

O `leodg-course-portal.zip` — o app React do AI Studio que originou o desenho, e
que os planos citam. Dentro dele estava o `public/bootstrap-portal.html`, que é a
versão mais recente do mockup. O que sobreviveu dele é o
`portal-aluno-version-bootstrip-responsive.html` aqui do lado, e o `DESIGN.md`,
que já traduz os tokens para o tema.
