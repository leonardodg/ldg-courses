# Endurecimento de infraestrutura, novembro de 2026

Revisado em 2026-11-02, antes do PR. Escopo: compose, imagem, `deploy.yml`, nginx e `config-docker.php`.

| # | Achado | Status | Correção |
|---|---|---|---|
| 1 | `directorypermissions` em `02775` quebraria a escrita do `www-data` em diretório criado pelo root nos `exec` do deploy | corrigido | Mudança retirada deste PR. Só depois de o deploy rodar como `www-data` |
| 2 | A primeira versão do IP real mexia no nginx sem necessidade | corrigido | O core usa o último valor do `X-Forwarded-For`; bastou o `getremoteaddrconf` |
| 3 | Linhas mortas após `exit 0` no smoke test | corrigido | Removidas |
| 4 | `MARIADB_ROOT_HOST` só vale em banco novo | descartado | Comportamento esperado; a conta existente muda à parte |
