#!/usr/bin/env bash
set -euo pipefail

# Install the demo Trading Service (httpd + PHP app) on all patching-full hosts.
# Also ensures the demo security group allows HTTP/80 from your current public IP.
#
# Usage: ./scripts/install-trading-service.sh

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
APP_DIR="${REPO_ROOT}/files/trading-service"

if [[ -f "${REPO_ROOT}/.env" ]]; then
  set -a
  # shellcheck disable=SC1091
  source "${REPO_ROOT}/.env"
  set +a
fi

REGION="${AWS_REGION:-eu-west-1}"
SSH_KEY="${PROBE_SSH_KEY_PATH:?Set PROBE_SSH_KEY_PATH in .env}"
SSH_USER="ec2-user"
SSH_OPTS="-o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes"
SG="${DEMO_SECURITY_GROUP_ID:-}"

if [[ ! -f "${APP_DIR}/index.php" ]]; then
  echo "ERROR: missing ${APP_DIR}/index.php"
  exit 1
fi

# Open HTTP from current IP if SG known
if [[ -n "$SG" ]]; then
  MY_IP=$(curl -s --connect-timeout 5 https://checkip.amazonaws.com || true)
  if [[ -n "$MY_IP" ]]; then
    if aws ec2 authorize-security-group-ingress \
      --region "$REGION" --group-id "$SG" \
      --protocol tcp --port 80 --cidr "${MY_IP}/32" 2>/dev/null; then
      echo "  [OK] Opened TCP/80 from ${MY_IP}/32 on ${SG}"
    else
      echo "  [OK] TCP/80 from ${MY_IP}/32 already allowed (or rule exists)"
    fi
  fi
fi

HOSTS=$(aws ec2 describe-instances \
  --region "$REGION" \
  --filters "Name=tag:demo,Values=patching-full" "Name=instance-state-name,Values=running" \
  --query "Reservations[].Instances[].{Name:Tags[?Key=='Name']|[0].Value,IP:PublicIpAddress}" \
  --output json)

COUNT=$(echo "$HOSTS" | python3 -c "import json,sys; print(len(json.load(sys.stdin)))")
if [[ "$COUNT" -eq 0 ]]; then
  echo "ERROR: no running demo=patching-full instances"
  exit 1
fi

install_one() {
  local name="$1" ip="$2"
  echo "  Installing Trading Service on ${name} (${ip})..."
  scp $SSH_OPTS -i "$SSH_KEY" \
    "${APP_DIR}/index.php" "${APP_DIR}/health.php" \
    "${SSH_USER}@${ip}:/tmp/" >/dev/null
  ssh $SSH_OPTS -i "$SSH_KEY" "${SSH_USER}@${ip}" bash -s <<'EOS'
set -euo pipefail
# Install httpd only — do NOT `dnf install php` here or it upgrades past the
# vulnerable demo NVR seeded by reset-demo.sh.
sudo dnf install -y httpd 2>&1 | tail -3
# Keep / ensure vulnerable PHP for the demo CVE story
if ! rpm -q php >/dev/null 2>&1; then
  sudo dnf install -y php php-cli php-common 2>&1 | tail -3
fi
if ! rpm -q php | grep -q '8.0.30-6.el9_8'; then
  sudo dnf downgrade -y php-8.0.30-6.el9_8 php-cli-8.0.30-6.el9_8 \
    php-common-8.0.30-6.el9_8 php-fpm-8.0.30-6.el9_8 2>&1 | tail -8 || true
fi
sudo mkdir -p /var/www/html
sudo install -m 0644 /tmp/index.php /tmp/health.php /var/www/html/
rm -f /tmp/index.php /tmp/health.php
# scp lands as user_tmp_t — httpd needs httpd_sys_content_t or PHP returns "Access denied."
sudo restorecon -Rv /var/www/html >/dev/null
sudo tee /etc/httpd/conf.d/trading-service.conf >/dev/null <<'CONF'
<VirtualHost *:80>
    ServerName trading-service
    DocumentRoot /var/www/html
    Alias /health /var/www/html/health.php
    <Directory /var/www/html>
        AllowOverride None
        Require all granted
    </Directory>
</VirtualHost>
CONF
sudo systemctl enable --now httpd
sudo systemctl restart httpd
curl -sf http://127.0.0.1/health | head -c 200
echo
hostname
rpm -q php httpd
EOS
  echo "  [OK] ${name} → http://${ip}/"
}

echo ""
echo "Installing Trading Service on ${COUNT} hosts..."
echo ""

while IFS=: read -r name ip; do
  [[ -z "$ip" ]] && continue
  install_one "$name" "$ip" &
done < <(echo "$HOSTS" | python3 -c "
import json, sys
for i in json.load(sys.stdin):
    if i.get('Name') and i.get('IP'):
        print(f\"{i['Name']}:{i['IP']}\")
")
wait

echo ""
echo "Done. Open a canary host in the browser, e.g.:"
echo "$HOSTS" | python3 -c "
import json, sys
for i in json.load(sys.stdin):
    if i.get('Name') == 'rhel-dev-01' and i.get('IP'):
        print(f\"  http://{i['IP']}/\")
        print(f\"  http://{i['IP']}/health\")
        break
"
