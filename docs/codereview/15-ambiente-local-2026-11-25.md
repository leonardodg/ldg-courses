# Ambiente local espelho da produção, 25 de novembro de 2026

Revisado em 2026-11-25, antes do PR. Escopo: `moodev` (`.moodev/host`), o segundo
`wwwroot` do `config-docker.php` e `.devcontainer/devtools/ambiente-local.php`.
O merge do upstream 5.2.5+ (Build 20261124) é código do core e fica fora desta
revisão; os 11 testsuites dos plugins passaram em cima dele.

| # | Achado | Status | Correção |
|---|---|---|---|
| 1 | O conteúdo de `.moodev/host` entrava sem validação num `sed "s#...#...#"` e no `MOODLE_URL`. Um `#`, espaço ou `/` corrompia o `dev.env` em silêncio, e o sintoma seria login em laço | corrigido | `main_host()` aceita só `[a-z0-9.-]` e morre com mensagem. Provado com `x#y` no arquivo |
| 2 | Restore que falhasse depois de `create_new_course()` deixava o curso sem `idnumber`: o `--apply` seguinte não o reconhecia e restaurava outra cópia | corrigido | O restore foi para `restore_into()`, e qualquer falha apaga o curso parcial antes de propagar. Provado com `moodle_backup.xml` inválido: erro dentro do `restore_into()`, contagem de cursos igual antes e depois |
| 3 | O aluno era matriculado no primeiro curso restaurado, que veio **oculto** do backup. A matrícula existia, o `--check` passava e o painel dizia *No courses* | corrigido | Escolha só entre cursos visíveis, e o `--check` confere `can_access_course()`. Achado no navegador, não no teste: vermelho antes, verde depois |
| 4 | Os backups da Ivana trazem as alunas reais (`users=1`, `anonymize=0`), e o SMTP local é o Gmail de produção | corrigido | `users`, `role_assignments` e afins desligados antes do restore, com exceção se a trava não pegar; o `--check` falha com qualquer e-mail fora de `example.com`/`exemplo.local` |
| 5 | O segundo `wwwroot` podia ser lido como "qualquer Host vira wwwroot" | descartado | Comparação exata com o host de `MOODLE_TUNNEL_URL`, só fora de CLI, e a variável não existe na VPS. Provado: `Host: evil.leodg.dev` recebe 303 para o `MOODLE_URL` |
| 6 | `allowindexing`/`opentowebcrawlers` diferem da produção | descartado | Decisão: o túnel é público, e indexar `mp.leodg.dev` duplicaria `courses.leodg.dev`. Registrado no script e no guia |
| 7 | O `config-docker.php` tem 20 apontamentos de `phpcs` | descartado | Todos anteriores a esta mudança; nenhum nas linhas acrescentadas. O script novo está limpo em `moodle-extra` |
