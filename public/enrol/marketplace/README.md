# enrol_marketplace

Matrícula derivada do **direito de acesso**, e não da venda.

## Para que serve

Quando o aluno adquire uma oferta, o `local_marketplace` grava um
`local_marketplace_entitlement`. Este plugin é quem transforma esse direito em
matrícula de verdade nos cursos que a oferta inclui.

A separação existe porque **venda e acesso são coisas diferentes**. Uma venda
estornada, uma assinatura vencida e uma renovação antecipada mudam o acesso sem
mudar a venda — e um plugin que lesse a venda para matricular erraria nos três
casos.

```
oferta comprada → entitlement → enrol_marketplace → matrícula
                             ↘ availability_marketplace → libera seção
                             ↘ block_marketplace → mostra o vencimento
```

Os três leem a **mesma** fonte. É o que garante que não divirjam.

## Dependências

| Depende de | Por quê |
|---|---|
| `local_marketplace` (2026082401+) | é dele que vem o direito de acesso |

## O que precisa configurar

**Nada.** A instância é criada sob demanda no curso
(`get_or_create_instance()`), com o papel de estudante padrão do site.

O que existe é uma **tarefa agendada** que expira matrículas cujo direito venceu
(`\enrol_marketplace\task\sync_entitlements`). Ela é a rede de segurança: o
caminho normal expira na hora, mas se um webhook não chegar ou o servidor cair no
meio, é ela que reconcilia.

Confira em `/admin/tool/task/scheduledtasks.php` que a tarefa está habilitada.
Sem ela, aluno com assinatura vencida **continua matriculado**.

## Armadilhas

**Não desmatricule à mão.** O estado vem do direito de acesso; uma
desmatrícula manual é desfeita na próxima execução da tarefa. Para tirar o
acesso, mexa no direito.

**`roles_protected()`** impede que a atribuição de papel feita por aqui seja
alterada por outros meios — é o que mantém a matrícula explicável.
