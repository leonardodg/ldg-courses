#!/usr/bin/env bash
# =============================================================================
# Provisiona o dominio de um vendedor na VPS.
#
#   sudo ./provisiona-dominio.sh meuscursos.joao.com [email@para.o.certbot]
#
# Faz, nesta ordem:
#   1. confere que o DNS ja aponta para esta maquina
#   2. instala o server block a partir do template
#   3. valida a config e recarrega o nginx
#   4. emite o certificado com o certbot
#
# A ordem importa. O certbot precisa do bloco HTTP no ar para responder ao
# desafio ACME, e precisa do DNS resolvendo - por isso a checagem vem antes de
# qualquer alteracao.
#
# O QUE ESTE SCRIPT NAO FAZ: cadastrar o dominio no marketplace. Sem o cadastro
# a requisicao chega mas o config.php nao encontra o host no mapa, e o visitante
# ve o site principal. Cadastre a empresa antes, ou logo depois:
#
#   Administracao do site -> Marketplace -> Empresas -> Editar -> Dominio proprio
# =============================================================================
set -euo pipefail

DOMINIO="${1:-}"
EMAIL="${2:-}"

if [ -z "$DOMINIO" ]; then
    echo "uso: $0 <dominio> [email]" >&2
    exit 1
fi

if [ "$(id -u)" -ne 0 ]; then
    echo "erro: precisa de root para escrever em /etc/nginx e rodar o certbot" >&2
    exit 1
fi

AQUI="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
TEMPLATE="$AQUI/vendor-domain.conf.template"
DESTINO="/etc/nginx/sites-available/$DOMINIO.conf"

[ -f "$TEMPLATE" ] || { echo "erro: template nao encontrado em $TEMPLATE" >&2; exit 1; }

# --- 1. DNS ------------------------------------------------------------------
# Falhar aqui e barato; falhar no certbot gasta uma tentativa do limite da
# Let's Encrypt, que e por dominio e por semana.
echo ">> conferindo DNS de $DOMINIO"
IP_LOCAL="$(curl -fsS --max-time 10 https://api.ipify.org || true)"
IP_DOMINIO="$(getent ahostsv4 "$DOMINIO" 2>/dev/null | awk 'NR==1{print $1}' || true)"

if [ -z "$IP_DOMINIO" ]; then
    echo "erro: $DOMINIO nao resolve. Configure o DNS antes." >&2
    exit 1
fi

if [ -n "$IP_LOCAL" ] && [ "$IP_DOMINIO" != "$IP_LOCAL" ]; then
    echo "   aviso: $DOMINIO resolve para $IP_DOMINIO, e esta maquina e $IP_LOCAL"
    echo "   Se o dominio esta atras da Cloudflare em modo proxy, isto e esperado."
    read -r -p "   continuar? [s/N] " RESP
    [ "$RESP" = "s" ] || [ "$RESP" = "S" ] || exit 1
else
    echo "   ok: $IP_DOMINIO"
fi

# --- 2. server block ---------------------------------------------------------
echo ">> instalando $DESTINO"
if [ -f "$DESTINO" ]; then
    cp "$DESTINO" "$DESTINO.bak-$(date +%Y%m%d-%H%M%S)"
    echo "   config anterior guardada"
fi

# O certificado ainda nao existe, entao o bloco HTTPS quebraria o nginx -t.
# Instala so a parte HTTP agora; o certbot acrescenta o resto e recarrega.
sed "s/__DOMINIO__/$DOMINIO/g" "$TEMPLATE" \
  | awk '/^# --- HTTPS/{exit} {print}' > "$DESTINO"

ln -sfn "$DESTINO" "/etc/nginx/sites-enabled/$DOMINIO.conf"
mkdir -p /var/www/certbot

# --- 3. valida e recarrega ---------------------------------------------------
echo ">> validando a configuracao do nginx"
if ! nginx -t; then
    echo "erro: nginx -t falhou; removendo o bloco novo" >&2
    rm -f "/etc/nginx/sites-enabled/$DOMINIO.conf"
    exit 1
fi
systemctl reload nginx
echo "   nginx recarregado"

# --- 4. certificado ----------------------------------------------------------
echo ">> emitindo certificado para $DOMINIO"
CERTBOT_ARGS=(--nginx -d "$DOMINIO" --agree-tos --non-interactive --redirect)
if [ -n "$EMAIL" ]; then
    CERTBOT_ARGS+=(-m "$EMAIL")
else
    CERTBOT_ARGS+=(--register-unsafely-without-email)
fi

if certbot "${CERTBOT_ARGS[@]}"; then
    echo "   certificado emitido"
else
    echo "erro: o certbot falhou. O bloco HTTP continua no ar para nova tentativa." >&2
    exit 1
fi

# --- 5. o que falta ----------------------------------------------------------
cat <<FIM

Dominio $DOMINIO provisionado no nginx.

FALTA o cadastro no marketplace, sem o qual a requisicao chega mas o visitante
ve o site principal:

  Administracao do site -> Marketplace -> Empresas -> Editar -> Dominio proprio

O mapa e regenerado sozinho ao salvar. Para conferir:

  docker compose exec -T moodle \\
    php /var/www/html/public/local/marketplace/cli/domains.php < /dev/null

FIM
