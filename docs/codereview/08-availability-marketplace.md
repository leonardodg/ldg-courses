# availability/condition/marketplace

[Voltar ao índice](README.md)

## 1. Matching de empresa por igualdade exata de categoria, não subárvore
- **Status:** corrigido
- **Arquivo:** `classes/frontend.php:84`
- **Achado:** `get_company_for_course()` casa a empresa por igualdade exata
  em `course->category`, não por subárvore de categoria, então um curso numa
  subcategoria abaixo da categoria raiz da empresa é tratado como sem empresa
  nenhuma.
- **Cenário de falha:** uma empresa organiza seus cursos em subcategorias sob
  sua categoria raiz. Para qualquer curso numa dessas subcategorias,
  `allow_add()` retorna falso — o editor do curso não consegue adicionar a
  restrição de disponibilidade do marketplace em nenhuma seção/atividade
  desse curso, e `get_javascript_init_params()` mostraria um seletor de
  oferta vazio. O paywall que este plugin existe para oferecer fica
  silenciosamente indisponível para qualquer curso fora da categoria de
  topo da empresa.
- **Correção:** o mesmo problema existia em duplicata em
  `local_marketplace\offer::add_course()` (achado 1 do checkpoint 1, corrigido
  com um método privado `course_in_company_category()`). Consolidado num
  único lugar: `local_marketplace\company` ganhou dois métodos públicos —
  `owns_course(int $courseid): bool` (sobe a árvore de categorias comparando
  contra `path`) e `for_course(int $courseid): ?company` (acha a empresa dona
  de um curso, subindo da categoria do curso até a raiz). `offer::add_course()`
  e `frontend::get_company_for_course()` agora chamam esses métodos em vez de
  reimplementar a lógica cada um à sua maneira.

## Verificação

```
phpcs --standard=moodle -p --report=summary public/local/marketplace public/availability/condition/marketplace   # limpo
php vendor/bin/phpunit --testsuite local_marketplace_testsuite,availability_marketplace_testsuite                 # OK (177 tests, 591 assertions)
```

Novo arquivo de teste `local_marketplace/tests/company_course_test.php` cobre
`owns_course()`/`for_course()` com curso na própria categoria, curso em
subcategoria, e curso sem relação com a empresa. Sem mudança de schema.
