#!/usr/bin/env bash
#
# Pasang HTTPS untuk PRADANA di server Ubuntu + Apache.
#
# Kenapa skrip ini ada: sertifikat TLS tidak butuh domain yang dibeli atau layanan
# nama gratis. Let's Encrypt memvalidasi lewat HTTP ke alamat publik apa pun —
# termasuk FQDN bawaan Azure Cloud Services (`<nama>.<region>.cloudapp.azure.com`)
# yang sudah ada begitu saja begitu IP-nya diberi label DNS.
#
# Kenapa BUKAN layanan domain gratis (DuckDNS dan sejenisnya) — dua alasan yang
# keduanya benar-benar terjadi:
#   1. Situsnya bisa tidak terjangkau dari jaringan tempat kantor berada, dan itu
#      mematikan seluruh rencana (memperbarui nama perlu masuk ke dashboard mereka).
#   2. Nama yang mereka berikan berbagi SATU registered domain dengan seluruh
#      penggunanya di dunia. Kuota sertifikat Let's Encrypt dihitung per registered
#      domain, jadi antrean sertifikat itu bisa habis sebelum giliran kita.
#
# Yang dilakukan: minta sertifikat (HTTP-01, webroot `public/`), pasang vhost 443,
# naikkan sisanya ke HTTPS, dan sisakan port 80 hanya untuk tantangan ACME supaya
# perpanjangan otomatis tetap jalan.
#
# Cara pakai (root, di server, dari akar aplikasi):
#   bash bin/pasang-https.sh pradana-demo.indiasouthcentral.cloudapp.azure.com
#   EMAIL=petugas@kantor.desa bash bin/pasang-https.sh <nama-host>   # surat peringatan kedaluwarsa
#
# Skrip ini idempoten: dijalankan ulang pada server yang sudah HTTPS tidak mengubah
# apa pun selain memeriksa ulang.
#
# BATAS: skrip ini menulis dua berkas vhost yang ia rawut sendiri
# (`pradana.conf` dan `pradana-le-ssl.conf`). Kalau vhost 80 yang ada berisi
# konfigurasi khusus (alias, mod_proxy, dokumen lain), periksa dulu
# `Apache` + `DocumentRoot`-nya — skrip menolak jalan kalau docroot-nya bukan
# aplikasi ini, tapi tidak mencoba menggabungkan konfigurasi orang lain.

set -euo pipefail

AKAR="${AKAR:-/var/www/pradana}"
PORT=443

if [ "$(id -u)" != "0" ]; then
    echo "Jalankan sebagai root: sudo bash bin/pasang-https.sh <nama-host>" >&2
    exit 1
fi

FQDN="${1:-}"
if [ -z "$FQDN" ]; then
    echo "Pemakaian: bash bin/pasang-https.sh <nama-host-publik>" >&2
    echo "  contoh  : bash bin/pasang-https.sh pradana-demo.indiasouthcentral.cloudapp.azure.com" >&2
    exit 2
fi

if [ ! -f "$AKAR/public/index.php" ]; then
    echo "GAGAL: $AKAR/public/index.php tidak ada — ini bukan akar aplikasi PRADANA." >&2
    echo "       Set AKAR=/jalan/ke/aplikasi kalau letaknya berbeda." >&2
    exit 1
fi

DOKROOT="$AKAR/public"
VHOST80=/etc/apache2/sites-available/pradana.conf
VHOST443=/etc/apache2/sites-available/pradana-le-ssl.conf

echo "== PRADANA pasang HTTPS =="
echo "Nama host : $FQDN"
echo "Docroot   : $DOKROOT"

# ── 0. Yang harus sudah benar sebelum sertifikat bisa diminta ─────────────────
# HTTP-01 berarti Let's Encrypt MENGINBOUND ke port 80. Kalau port 80 tertutup
# (firewall cloud atau ufw), permintaannya gagal dengan pesan yang terdengar
# seperti masalah sertifikat padahal bukan.
if ! command -v apache2ctl >/dev/null 2>&1; then
    echo "GAGAL: apache2ctl tidak ada — skrip ini untuk Apache, bukan nginx/LiteSpeed." >&2
    exit 1
fi

if command -v curl >/dev/null 2>&1; then
    KODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 20 "http://127.0.0.1/login" || echo 000)
    echo "-- port 80 menjawab: $KODE (200 = belum ada redirect, 301 = server ini sudah HTTPS)"
fi

echo "-- pasang certbot"
if ! command -v certbot >/dev/null 2>&1; then
    export DEBIAN_FRONTEND=noninteractive
    apt-get update -qq
    apt-get install -y certbot python3-certbot-apache
fi

# ── 1. Sertifikat ─────────────────────────────────────────────────────────────
# `--webroot` menulis berkas tantangan ke public/.well-known/acme-challenge/ dan
# Apache melayaninya sebagai berkas asli — .htaccess Laravel hanya menuliskan
# permintaan yang BUKAN berkas, jadi jalur ini tetap lolos tanpa menyentuh kode.
#
# Tanpa EMAIL: akun Let's Encrypt didaftarkan tanpa alamat, jadi tidak ada surat
# peringatan sebelum kedaluwarsa. Perpanjangan otomatis (certbot.timer) biasanya
# menutup itu, tapi kalau suatu saat perpanjangan gagal diam-diam, yang tahu
# pertama kali adalah browser petugas. Isi EMAIL= untuk menghindari itu.
if [ -d "/etc/letsencrypt/live/$FQDN" ]; then
    echo "-- sertifikat untuk $FQDN sudah ada, dilewati"
else
    echo "-- minta sertifikat Let's Encrypt (HTTP-01)"
    if [ -n "${EMAIL:-}" ]; then
        EMAIL_ARG=(--email "$EMAIL" --no-eff-email)
    else
        EMAIL_ARG=(--register-unsafely-without-email)
        echo "   catatan: tanpa EMAIL=..., tidak ada surat peringatan kedaluwarsa dari Let's Encrypt."
    fi

    certbot certonly --webroot -w "$DOKROOT" -d "$FQDN" \
        --non-interactive --agree-tos "${EMAIL_ARG[@]}"
fi

# ── 2. Vhost 443 ──────────────────────────────────────────────────────────────
# Blok <DirectoryMatch> sengaja disalin dari vhost 80: kalau hanya dipasang di 80,
# orang masih bisa mengambil /vendor/ atau /.env lewat https:// — docroot-nya sama.
a2enmod ssl >/dev/null

if [ ! -f "$VHOST80" ]; then
    echo "GAGAL: $VHOST80 tidak ada; skrip ini mengharapkan vhost PRADANA yang dibuat" >&2
    echo "       bin/sediakan-server.sh." >&2
    exit 1
fi

if grep -q "DocumentRoot" "$VHOST80" && ! grep -q "DocumentRoot $DOKROOT" "$VHOST80"; then
    echo "GAGAL: DocumentRoot di $VHOST80 bukan $DOKROOT — periksa dulu, tidak ditimpa." >&2
    exit 1
fi

[ -f "$VHOST80.belum-https" ] || cp "$VHOST80" "$VHOST80.belum-https"

cat > "$VHOST80" <<VHOST
<VirtualHost *:80>
    ServerName $FQDN
    DocumentRoot $DOKROOT
    <Directory $DOKROOT>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    # Berkas di luar public/ tidak boleh tersaji meski ada yang salah arah.
    <DirectoryMatch "^$AKAR/(app|bootstrap|config|database|resources|routes|storage|tests|vendor)/">
        Require all denied
    </DirectoryMatch>

    # Port 80 tetap hidup HANYA untuk tantangan ACME (perpanjangan sertifikat).
    # Sisanya naik ke HTTPS — termasuk orang yang mengetik alamat tanpa "https://",
    # yang selama ini gagal karena browser mencoba HTTPS lebih dulu.
    RewriteEngine On
    RewriteCond %{REQUEST_URI} !^/\\.well-known/acme-challenge/
    RewriteRule ^/(.*)\$ https://$FQDN/\$1 [R=301,L]

    ErrorLog \${APACHE_LOG_DIR}/pradana-error.log
    CustomLog \${APACHE_LOG_DIR}/pradana-access.log combined
</VirtualHost>
VHOST

cat > "$VHOST443" <<VHOST
<IfModule mod_ssl.c>
<VirtualHost *:$PORT>
    ServerName $FQDN
    DocumentRoot $DOKROOT

    SSLEngine on
    SSLCertificateFile    /etc/letsencrypt/live/$FQDN/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/$FQDN/privkey.pem

    <Directory $DOKROOT>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <DirectoryMatch "^$AKAR/(app|bootstrap|config|database|resources|routes|storage|tests|vendor)/">
        Require all denied
    </DirectoryMatch>

    ErrorLog \${APACHE_LOG_DIR}/pradana-error.log
    CustomLog \${APACHE_LOG_DIR}/pradana-access.log combined
</VirtualHost>
</IfModule>
VHOST

a2ensite pradana-le-ssl >/dev/null
# 644, bukan 600: Apache membacanya sebagai root jadi tetap jalan, tapi berkas
# konfigurasi server yang hanya bisa dibaca root membuat orang berikutnya mengira
# ada rahasia di dalamnya.
chmod 644 "$VHOST80" "$VHOST443"
apache2ctl configtest
systemctl reload apache2

# ── 3. Perpanjangan ───────────────────────────────────────────────────────────
# certbot memasang timer-nya sendiri; yang TIDAK bisa ditebak dari situ adalah
# Apache tetap memegang sertifikat lama di memorinya sampai dimuat ulang. Hook di
# folder deploy/ dijalankan setiap kali ada sertifikat baru.
mkdir -p /etc/letsencrypt/renewal-hooks/deploy
if [ ! -x /etc/letsencrypt/renewal-hooks/deploy/reload-apache.sh ]; then
    printf '#!/bin/sh\n# Sertifikat baru belum dipakai Apache sampai prosesnya dimuat ulang.\nsystemctl reload apache2\n' \
        > /etc/letsencrypt/renewal-hooks/deploy/reload-apache.sh
    chmod 755 /etc/letsencrypt/renewal-hooks/deploy/reload-apache.sh
fi

# ── 4. Bukti, bukan perasaan ──────────────────────────────────────────────────
echo
echo "-- periksa sendiri (dari server):"
curl -s -o /dev/null -w "   http  /login            = %{http_code} -> %{redirect_url}\n" http://127.0.0.1/login
# Tanpa -k: kalau rantai sertifikat tidak dipercaya, baris ini yang memberitahu.
curl -s -o /dev/null -w "   https /login            = %{http_code}\n" --max-time 30 "https://$FQDN/login"
echo
echo "-- yang masih harus dikerjakan di luar server ini:"
echo "   1. Buka port $PORT di firewall cloud (Azure: NSG rule, priority di bawah 65000"
echo "      dan tidak bentrok dengan rule yang sudah ada) — ufw 'Apache Full' saja tidak cukup."
echo "   2. .env: APP_URL=https://$FQDN dan SESSION_SECURE_COOKIE=true, lalu"
echo "      sudo -u www-data env HOME=/tmp bash -c 'cd $AKAR && php artisan config:cache'"
echo "   3. Uji dari perangkat lain dengan alamat https://$FQDN — bukan http://, bukan IP."
echo
echo "Selesai."
