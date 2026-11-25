# availability_marketplace

Condição de acesso que libera seção ou atividade conforme o **direito de acesso**
do aluno.

## Para que serve

É o que permite um curso ter tópico grátis e tópico pago no mesmo lugar.

O tópico livre não recebe condição; os pagos recebem esta. O aluno enxerga o
curso, entende o que vai receber, e só o conteúdo pago fica fechado — **converte
melhor do que esconder o curso inteiro atrás de um paywall**.

## Dependências

| Depende de | Por quê |
|---|---|
| `local_marketplace` (2026082401+) | lê `local_marketplace_entitlement` |

## O que precisa configurar

**Nada no admin.** A condição aparece na tela de restrição de acesso da seção ou
atividade, junto com as do core.

Duas formas de uso:

| `offerid` | Significado |
|---|---|
| `0` | qualquer direito vigente que inclua este curso |
| `> 0` | uma oferta específica |

O segundo caso serve a conteúdo que só vem no pacote mais caro — a mentoria do
"Completo com mentoria", por exemplo. Quem comprou o pacote básico vê que existe,
e não entra.

## Armadilhas

**A condição lê o direito, nunca a venda.** Se o aluno pagou mas não tem direito
vigente, o problema está no `local_marketplace` — não adiante mexer aqui.

**Restrição não é segurança de arquivo.** Como toda condição de disponibilidade
do Moodle, ela controla o que aparece no curso. Arquivo cujo link vazou continua
servido pelo `pluginfile.php` conforme as permissões dele.
