# Páginas de manual

| Página | Comando |
|---|---|
| [`moodev.1`](moodev.1) | `man moodev` |

## Instalar

```bash
sudo install -m 644 docs/man/moodev.1 /usr/local/share/man/man1/moodev.1
sudo mandb -q
```

Sem `sudo`:

```bash
mkdir -p ~/.local/share/man/man1
install -m 644 docs/man/moodev.1 ~/.local/share/man/man1/moodev.1
mandb -q ~/.local/share/man
```

## Conferir antes de commitar

```bash
groff -man -t -Tascii -ww docs/man/moodev.1 >/dev/null   # sem avisos
MANWIDTH=72 man -l docs/man/moodev.1                     # cabe em terminal estreito
```

A primeira linha do arquivo (`'\" t`) é obrigatória: manda o `man` rodar o
preprocessador `tbl`, sem o qual as tabelas não renderizam.
