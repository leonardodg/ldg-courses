# Diagramas dos plugins LeoDG

Nove diagramas do desenho de dados e da arquitetura dos doze plugins
desenvolvidos para a plataforma. Gerados a partir do **schema real** — todo
campo, tipo e índice sai dos `db/install.xml`, lidos em **14/09/2026**
(`02-er-comercial` e `06-arquitetura` atualizados em **26/09/2026** para
cobrir `local_marketplace_library` e o `mod_bunnystream`).

Cada diagrama vem em três arquivos: `.html` (a fonte, que reabre para edição),
`.svg` (para embutir em documento e escalar) e `.png` @2 (para colar em
apresentação ou issue).

## Dados

| Arquivo | Responde |
|---|---|
| `01-er-mestre` | O mapa inteiro: as 16 tabelas próprias e as 8 do core que elas tocam, em quatro clusters |
| `02-er-comercial` | Empresa, plano, faixas de resolução, conta por país e vendedores |
| `03-er-venda-acesso` | Oferta, cursos que ela libera, direito de acesso, venda e o pagamento do core |
| `04-er-gateways` | As três tabelas de transação e como cada uma se liga a `{payments}` |
| `05-er-conteudo` | Aula em vídeo, duração por módulo, candidatura e política de curso |

## Arquitetura

| Arquivo | Responde |
|---|---|
| `06-arquitetura` | Páginas agrupadas por permissão, endpoints, cron, banco e APIs externas |
| `07-fluxo-parceiro` | Cadastro de empresa: da landing pública à empresa provisionada |
| `08-fluxo-aluno` | Compra: da vitrine ao acesso liberado, com o caminho da oferta grátis |
| `09-configuracoes` | Onde mora cada ajuste dos plugins, e a cadeia que resolve a comissão |
| `10-fluxo-ativacao-empresa` | Continuação do `07`: da empresa provisionada ao checklist de ativação completo no `block_marketplace` |

## O que os diagramas mostram e o `install.xml` não

**A diferença entre FK declarada e vínculo garantido só por código.** Linha
sólida é `<KEY TYPE="foreign">` no XMLDB — são 21. Linha tracejada é ligação
real que nunca virou chave estrangeira, e **cada omissão tem motivo registrado
no próprio XMLDB**: `format_ldg_lesson.cmid` não tem FK porque a atividade pode
ser apagada e a linha órfã não pode impedir a troca de formato;
`local_partners_application.planid` não tem porque apontaria para tabela de
outro plugin e o `check_database_schema` reprovaria.

**O Moodle não declara `ON DELETE` no XMLDB.** A cascata é de aplicação. A única
que existe está no privacy provider: apagar um usuário apaga `_member` e
`_entitlement` — e **não** apaga `_sale`.

**Uma divergência entre os três gateways**, no `04`: o `paygw_mercadopago`
APAGA a linha de transação na exclusão por privacidade; o `paygw_asaas` e o
`paygw_pagarme` a RETÊM, e o comentário de classe dos dois justifica — registro
de pagamento é obrigação fiscal do vendedor. Os três têm o mesmo papel e
comportamentos opostos; um dos lados está errado.

**A referência que o banco não enxerga**, no `05`: o `availability_marketplace`
guarda `{"type":"marketplace","offerid":N}` dentro da coluna de texto
`course_modules.availability`.

## Regerar e conferir

Os diagramas **não são escritos à mão**: `gerar/` tem um script por diagrama e
o `gerar/ddgen.py` com as primitivas. Gerar por código é o que garante que todo
campo venha do schema, que a grade de 4px feche, e que as regras de conector do
Diagram Design valham por construção — o `ddgen` recusa segmento diagonal e
recusa texto que não cabe na caixa, medindo a fonte de verdade em vez de
estimar.

```bash
# regerar os nove .html
for f in docs/diagram/gerar/d0*.py; do python3 "$f"; done

# exportar .svg e .png @2
python3 docs/diagram/gerar/exportar.py docs/diagram/0*.html

# conferir contra o schema — sai 1 se algo divergir
python3 docs/diagram/conferir-diagramas.py
```

O `conferir-diagramas.py` compara o que cada diagrama declarou ter desenhado
(os `*.manifesto.json`, emitidos pelo gerador) contra os `install.xml`, e
**reprova** se algum citar coluna, índice ou tabela que não existe. Rode depois
de mexer em qualquer `db/install.xml`: diagrama não quebra teste nenhum
sozinho — ele envelhece em silêncio enquanto o schema anda.

O `exportar.py` precisa do Playwright com Chromium
(`pip install playwright && playwright install chromium`). A geração dos
`.html` e a conferência não precisam de nada além do Python 3.10+.

Os diagramas usam o perfil de marca `leodg` do Diagram Design
(`~/.diagram-design/profiles/leodg.md`), derivado de
[`../brand/design_system_leodg.md`](../brand/design_system_leodg.md). O marcador
`.diagram-design` na raiz da worktree faz qualquer diagrama novo deste repo
resolver esse perfil sozinho.

> O modo escuro é o canônico da marca. O `accent` do dark é `#3394ff` e não
> `#007aff`: o segundo dá 4,15:1 sobre a superfície `#1e1e1e`, abaixo do AA para
> texto pequeno, e o acento carrega rótulos de 8px. O `#3394ff` é o Primary
> Hover que o design system já nomeia, e dá 5,41:1.

## O que não está aqui

Os diagramas não substituem [`../data-model/marketplace.md`](../data-model/marketplace.md),
que descreve o `local_marketplace` em prosa, nem
[`../architecture/arquitetura-plataforma.svg`](../architecture/arquitetura-plataforma.svg),
que mostra o fluxo do split. Consolidar os três é decisão em aberto.

No `01`, nem toda relação virou linha: com 24 entidades e mais de 40
referências, desenhar uma seta por FK é o anti-padrão que o `type-er.md` do
Diagram Design nomeia. As linhas carregam a estrutura; as referências de fan-in
(`user`, com 9, e `payment_accounts`, com 5) estão listadas dentro da própria
caixa, e o detalhe coluna a coluna está nos diagramas 02 a 05.
