# ADR-0009 — Dois papéis de empresa, e a proibição de upload como lista estática

**Situação:** Aceita · **Data:** 2026-09-04

## Contexto

O plano Free dá área de membros e checkout **sem gastar um centavo de
streaming**, porque o vídeo é embed de serviço externo. O que sustenta isso não
é o campo do `mod_ldgvideo` recusar um endereço do próprio site — aquilo protege
contra o engano, não contra a intenção. O que sustenta é o professor **não
conseguir colocar arquivo no `moodledata`**: sem arquivo local, não há vídeo
nosso para servir.

Essa ideia já existia. O `local_marketplace/db/install.php` criava o papel
`marketplaceseller` com `CAP_PROHIBIT` em `repository/upload:view`,
`repository/url:view` e `moodle/course:ignorefilesizelimits`, e o comentário
dele já dizia que a regra "vídeo fica fora da plataforma" **não é uma capability
própria: é a ausência das que colocam arquivo no `moodledata`**.

Estava certo e incompleto. Levantado em 04/09/2026:

- a lista trancava **2 dos 17 repositórios** do site. `dropbox`, `googledocs`, `onedrive`, `nextcloud`, `webdav`, `s3`, `filesystem`, `local` e `user` seguiam abertos
- `moodle/restore:uploadfile` não era proibida — um `.mbz` restaurado leva vídeo dentro, por fora do seletor de arquivos
- o `db/upgrade.php` **nunca tocou no papel**, e o `install.php` só roda em instalação nova: toda mudança na lista valia apenas para quem instalasse do zero
- **nenhum teste.** A regra que sustenta a margem não tinha uma linha verificando que continua valendo
- `owner` e `seller` recebiam o **mesmo** papel. Quem só montava curso também alcançava a credencial financeira da empresa

## Decisão

**São dois papéis, divididos por risco.** `marketplacemanager` para o `owner` —
monta curso **e** responde pela conta de pagamento e pelos membros. O
`marketplaceseller` fica sendo só quem monta curso. Nenhuma capability sai do
conjunto que existia; ela muda de dono. `moodle/role:assign` vai para o gerente.

**A lista de proibição é estática, e cobre todo repositório que traz arquivo
para dentro.** O critério é um só: o repositório devolve um **link externo**, ou
copia o arquivo para o `moodledata`? Todo seletor de arquivos do Moodle copia o
que foi escolhido — inclusive o do Flickr e o do Wikimedia, que parecem externos
e não são. Ficam de fora só o `youtube`, que devolve endereço, e o `areafiles`,
que mostra o que já está embutido no mesmo campo.

**`ensure()` reconcilia, e não só acrescenta.** O que não está na lista **sai**
do papel. O conjunto de capabilities destes papéis é regra de negócio escrita em
código, e não preferência de administrador.

**O `cli/status.php` é o relator.** Ele compara os repositórios habilitados com
a lista e grita o que sobrou. Não conserta nada — quem tranca é a constante.

## Alternativas consideradas

| Alternativa | Por que não |
|---|---|
| `CAP_PREVENT` em vez de `CAP_PROHIBIT` | O vendedor também carrega o papel de usuário autenticado, que **permite** `repository/upload:view` — o archetype `user` é `CAP_ALLOW` no `db/access.php` do próprio repositório. Quando dois papéis se contradizem no mesmo contexto, o `ALLOW` vence o `PREVENT`. Só o `PROHIBIT` não é sobreponível |
| Uma capability própria, `local/marketplace:uploadfiles` | Não existe ponto no core que a consultasse. O seletor de arquivos pergunta pelas capabilities dos repositórios, e é lá que a decisão precisa acontecer |
| Ler os repositórios habilitados e proibir dinamicamente | Pareceria mais completo e seria segurança dependente de a configuração estar certa. Um repositório habilitado entre um `ensure()` e outro ficaria aberto, e nada avisaria |
| Confiar no `maxbytes` | Limita **tamanho**, e não tipo. Um vídeo curto passa |
| Só `ensure()` acrescentando, sem remover | Foi a primeira versão, e ela **passou no banco de teste e não fez nada em produção**: numa base que já tinha o papel, o vendedor seguia com `managepayment`, `managecompany`, `viewreport` e `role:assign`. Não há teste de banco limpo que pegue isso |
| Manter um papel só | Quem monta curso continuaria alcançando a credencial financeira da empresa |

## Consequências

**Fica mais fácil:** a fronteira é uma constante greppável, com teste, e o
`status.php` avisa quando ela envelhece.

**Fica mais difícil, e são três coisas concretas:**

1. **O vendedor não envia imagem de capa** pelo seletor de arquivos — usa URL externa.
2. **No portal do aluno, "Material de apoio" vale só para `mod_url`.** `mod_resource` e `mod_folder` continuam podendo ser criados, e ficam vazios. Isso já era verdade antes desta decisão; agora está escrito.
3. **Capability acrescentada à mão some no upgrade seguinte.** É desejado — um `repository/dropbox:view` colocado no papel é exatamente o que isto existe para fechar — mas surpreende quem não souber.

**O escopo é a categoria da empresa, e não o site.** O papel é atribuído em
`CONTEXT_COURSECAT`, então a proibição vale ali e em tudo dentro: cursos, seções,
atividades — que é onde o conteúdo é montado e servido. Ela **não** alcança o
contexto pessoal do usuário: o vendedor continua podendo subir arquivo nos
próprios *Arquivos privados*. Isso não abre a margem, porque aquele arquivo não
chega ao aluno — para servi-lo num curso seria preciso passar pelo seletor
dentro da categoria, e ali a proibição vale. O que sobra é um pouco de disco,
limitado pela cota do usuário. O escopo está fixado em
`roles_test::test_a_proibicao_vale_na_categoria_e_nao_no_usuario`, para a
próxima sessão não "consertar" o que é de propósito.

## Como saber que erramos

O sinal direto é o `cli/status.php` reportar repositório sem proibição — quer
dizer que alguém habilitou um caminho novo e a lista ficou para trás.

O segundo é o custo de **armazenamento** crescer sem mudança de plano: alguma
via para o `moodledata` foi reaberta.

O terceiro é comercial, e o mais fácil de ignorar: se produtores desistirem no
cadastro porque não conseguem subir a imagem de capa, a fronteira ficou apertada
no lugar errado — ela existe para barrar **vídeo**, e está barrando um JPEG de
80 KB. Nesse caso a saída não é afrouxar a lista, e sim dar um caminho próprio
para imagem que não passe por repositório.
