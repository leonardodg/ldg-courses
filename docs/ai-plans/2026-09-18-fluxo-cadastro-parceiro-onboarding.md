# Plano: salvar fluxo de cadastro de parceiro em worktree própria, revisar contra o desenho comercial do SaaS, e trocar o m3e-canvas por mockup HTML real

## Context

O desenho do fluxo de ativação de empresa parceira (transformar `block_marketplace`
num wizard de onboarding) foi elaborado inteiramente dentro da worktree `dev` —
que é a worktree de repouso, nunca de trabalho (ver `CLAUDE.md` e a memória
[[scaffold-plugin-novo-com-mdlcode]] / [[pr-e-merjeado-antes-de-eu-terminar]]).
Ficou:

- `docs/history/desenhar_fluxo_cadastrar_empresa.txt` modificado (histórico da
  conversa de design, já tracked).
- Untracked: `docs/ai-plans/2026-09-17-fluxo-cadastro-parceiro-m3e-canvas.json`,
  `docs/ai-plans/2026-09-17-fluxo-cadastro-parceiro-screens.md`,
  `docs/ai-plans/generate-docz-link.js`, `docs/ai-plans/m3e-screens/` (11 JSONs
  por tela + README).

Duas explorações confirmaram dois problemas antes de este material virar plano
de implementação:

1. **O desenho comercial diverge do que já está implementado** na worktree
   `saas-planos-start-pro` (planos `start_free`/`start_50`/`start_100`/`pro`).
2. **O m3e-canvas local não é a ferramenta certa** para o que foi gerado — é um
   esboçador de widgets Material 3 (schema `groups`/`frames`, ~35 tipos de
   componente), e o JSON produzido é uma especificação de tela Moodle/Mustache
   com Bootstrap, schema totalmente diferente. Por isso a importação falha na
   validação (`isProject()` rejeita por faltar `groups`/`frames`), e o script
   `generate-docz-link.js` tem ainda um segundo bug (falta compressão
   `deflate-raw` antes do base64url, que o `readShareHash` sempre espera).

Decisão do usuário: abandonar o m3e-canvas para este caso e produzir as 11
telas como **HTML/Mustache real**, revisado no Chrome — que é a prática que já
existe no projeto ([[ui-se-prova-no-navegador]], `padrao-de-implementacao.md`).

## Fase 1 — Mover o trabalho para uma worktree própria

Upstream do Moodle já conferido: `origin/dev` está em dia com
`upstream/MOODLE_502_STABLE` (0 commits de diferença) — pode abrir feature nova
sem sincronizar primeiro.

1. `moodev new parceiro-onboarding --from origin/dev` (worktree +
   branch `feature/parceiro-onboarding`, a partir da `dev` atual).
2. Copiar para a nova worktree, nos mesmos caminhos relativos:
   - `docs/history/desenhar_fluxo_cadastrar_empresa.txt` (versão modificada)
   - `docs/ai-plans/2026-09-17-fluxo-cadastro-parceiro-m3e-canvas.json`
   - `docs/ai-plans/2026-09-17-fluxo-cadastro-parceiro-screens.md`
   - `docs/ai-plans/generate-docz-link.js`
   - `docs/ai-plans/m3e-screens/` (diretório inteiro)
3. Registrar este plano (`typed-moseying-swan.md`) já com nome definitivo
   `docs/ai-plans/2026-09-18-fluxo-cadastro-parceiro-onboarding.md` na nova
   worktree, conforme [[planos-vao-para-docs-ai-plans]] (renomear só ao
   registrar, nunca no meio do processo).
4. Commit único na nova worktree: "docs: registra desenho do fluxo de cadastro
   de empresa parceira (m3e-canvas descartado, HTML real na Fase 3)".
5. Restaurar a worktree `dev`: `git checkout -- docs/history/desenhar_fluxo_cadastrar_empresa.txt`
   e remover os arquivos untracked copiados (`git status` antes e depois para
   confirmar que `dev` volta a "clean" e que nenhum outro untracked alheio foi
   tocado).

## Fase 2 — Revisão do fluxo desenhado contra o modelo de planos já implementado

Registrar (no próprio arquivo de histórico ou num adendo ao plano, na nova
worktree) as quatro divergências encontradas, para corrigir antes de desenhar
o HTML final:

| Onde o rascunho erra | O que existe de verdade | Ação |
|---|---|---|
| "plano 50 para vídeo até **1024**" | `plan_tier::RESOLUTIONS` é `720p/1080p/1440p/4k` — não existe "1024" | Trocar rótulo da tela D4 para 1080p |
| Card "BYOS — em breve, desabilitado" | `pro` (BYOS) já está implementado e testado (119 testes Pagar.me, 127 MP, 69 Asaas) — só a **Fase 5** (hospedagem de conteúdo na própria plataforma) está bloqueada por decisão de negócio, não o plano/cobrança | D4 deve mostrar PRO como plano real selecionável, não "em breve" |
| Etapa "Aprovação — `approvalmode` manual/auto" | Não existe setting nenhum hoje; cadastro de empresa é **só admin**, via candidatura em `local_partners` (sem auto-atendimento, decisão documentada no `CLAUDE.md`) | Ou remover a tela de toggle auto/manual do desenho agora, ou marcar explicitamente como funcionalidade futura fora do escopo desta fase |
| Etapa "Termos de contrato" como campo novo em `company` | `termsaccepted` (carimbo de aceite) já existe em `local_partners\application` (a candidatura), não na empresa | Reaproveitar o campo existente da candidatura em vez de propor coluna nova |
| Documento CNPJ/CPF como etapa obrigatória | `company::cnpj` é opcional (`PARAM_ALPHANUM`, nulo permitido); ADR-0010 registra que vendedor/empresa parceira **não precisa** ser pessoa jurídica | Tela D5 deve deixar claro que a etapa é opcional, não bloqueante |

Fechar esta rodada com o usuário (ele já validou o inventário de 11 telas antes,
mas com essas premissas erradas) antes de desenhar o HTML.

## Fase 3 — Telas em HTML real (substitui o m3e-canvas)

1. Descartar `2026-09-17-fluxo-cadastro-parceiro-m3e-canvas.json`,
   `generate-docz-link.js` e `m3e-screens/` (ou mover para uma pasta
   `descartado/` dentro da worktree nova, não apagar — decisão de manter
   histórico é do usuário).
2. Para cada uma das 11 telas (D1–D7, C1–C4), gerar HTML estático usando
   Bootstrap 5.3 + tokens `--ldg-*` do `theme_ldg`
   (`dev/public/theme/ldg/scss/ldg/_tokens.scss`), já com as correções da
   Fase 2 aplicadas.
3. Abrir cada HTML no Chrome, no mesmo viewport da referência do design system
   (`docs/brand/design_system_leodg.jpeg`), lado a lado — mesma prática de
   [[ui-se-prova-no-navegador]]: contraste, alvo de toque ≥44px, coluna única
   no mobile.
4. Só depois da aprovação tela a tela, estas telas viram os templates Mustache
   reais do plano de implementação do `block_marketplace` (Fase 2 do desenho
   original em `desenhar_fluxo_cadastrar_empresa.txt`), que é trabalho futuro,
   fora deste plano.

## Verificação

- `git -C dev status` limpo depois da Fase 1.
- Nova worktree aparece em `moodev ls` com stack própria.
- `git -C <nova-worktree> log --oneline -1` mostra o commit único da Fase 1.
- As 11 telas HTML abrem direto no Chrome sem servidor/ferramenta externa,
  cada uma comparada com a referência do design system.
