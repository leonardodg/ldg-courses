# Hotfix: editor_scss sem arquivo nos temas

Revisado em 2026-11-02, antes do PR. Escopo: `theme_ldg`, `theme_moove` e
`theme_ldg\editor_scss_test`.

**Cobertura:** o `/code-review` em nível low viu só o diff de arquivos rastreados e não relatou
achados. O teste novo não estava nesse diff e foi lido à mão.

| # | Achado | Status | Correção |
|---|---|---|---|
| 1 | `theme_moove` é de terceiro: uma atualização do tema traz a declaração de volta | descartado | O teste varre todos os temas e reprova a volta. Criar um `editor.scss` vazio deixaria o editor sem o CSS do Boost |
| 2 | O teste cobre a declaração, não a compilação | descartado | Compilar custa tempo por tema. A compilação foi medida à mão nos três temas, sem aviso, e o CI roda a inicialização real do behat |
