#!/usr/bin/env bash
#
# Provisi server untuk PRADANA di Ubuntu 24.04 (dipakai untuk Oracle Cloud Always Free,
# tapi tidak terikat ke Oracle — VM mana pun dengan Ubuntu 24.04 bisa).
#
# Kenapa skrip terpisah dari bin/deploy.sh: deploy.sh memasang APLIKASI (composer,
# migrasi, cache) dan sengaja menolak jalan kalau servernya belum benar. Skrip ini
# menyiapkan SERVER-nya: Apache dengan DocumentRoot `public/`, PHP + ekstensi + batas
# unggah, MariaDB + user database khusus, cron untuk `schedule:run`, dan Composer.
#
# Jujur tentang statusnya: skrip ini TIDAK PERNAH dijalankan di Linux. Yang bisa diuji
# di mesin pengembang sudah (sintaks bash, bentuk konfigurasi, urutan paket), sisanya
# baru terbukti saat dijalankan sekali di VM — dan checklist pembuktiannya ada di
# docs/kesiapan-deploy.md bagian 7. Jangan mengutip file ini sebagai "sudah teruji".
#
# Cara pakai (lewat SSH sebagai user biasa, dengan sudo):
#   sudo bash bin/sediakan-server.sh --cek      # lihat apa yang akan dilakukan
#   sudo bash bin/sediakan-server.sh            # jalankan
#
# Yang TIDAK dilakukan skrip ini, dan sengaja begitu:
#   - Tidak menulis `.env`. Kredensial dan APP_URL adalah keputusan manusia, dan skrip
#     yang mengarang `.env` akan menghasilkan server yang "kelihatan" jalan dengan isi
#     yang salah (APP_KEY daur ulang, zona waktu bawaan, DB root).
#   - Tidak menghapus apa pun. Kalau `$APP_DIR` sudah berisi — dan urutan di
#     docs/deploy-vm.md memang `git clone` dulu, baru skrip ini — isinya dibiarkan,
#     hanya kepemilikannya disetel ke `www-data:www-data`.
#   - Tidak menghapus dump/arsip yang sudah ada di `storage/app/private/`.

set -euo pipefail

AKAR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

APP_DIR="${APP_DIR:-/var/www/pradana}"
PHP_VERSI="8.2"
DB_NAMA="${DB_NAMA:-pradana}"
DB_USER="${DB_USER:-pradana}"
ZONA="${ZONA:-Asia/Jakarta}"
PORT_SSH_TETAP=22
CEK=0
for arg in "$@"; do
    case "$arg" in
        --cek) CEK=1 ;;
        --php83) PHP_VERSI="8.3" ;;
        --app-dir=*) APP_DIR="${arg#--app-dir=}" ;;
        --zona=*) ZONA="${arg#--zona=}" ;;
        *) echo "Argumen tidak dikenal: $arg (yang ada: --cek --php83 --app-dir= --zona=)" >&2; exit 2 ;;
    esac
done

echo "== Provisi server PRADANA (Ubuntu 24.04) =="
echo "Aplikasi akan dipasang di : $APP_DIR"
echo "PHP                       : $PHP_VERSI"
echo "Zona waktu server         : $ZONA"
echo "Database                  : $DB_NAMA (user $DB_USER)"
echo "Mode                      : $([ "$CEK" -eq 1 ] && echo 'CEK SAJA — tidak ada yang ditulis' || echo 'JALAN')"
echo

if [ "$(id -u)" -ne 0 ] && [ "$CEK" -eq 0 ]; then
    # --cek tidak menulis apa pun, jadi ia boleh (dan harus) bisa dijalankan tanpa sudo:
    # itu satu-satunya cara orang bisa memeriksa rencananya sebelum menyentuh server.
    echo "GAGAL: jalankan dengan sudo (skrip ini memasang paket dan menulis /etc/apache2)." >&2
    exit 1
fi

if ! grep -q 'VERSION_ID="24.04"' /etc/os-release 2>/dev/null; then
    echo "PERINGATAN: distribusi ini bukan Ubuntu 24.04 terbaca dari /etc/os-release."
    echo "            Nama paket bisa berbeda. Tidak ada yang ditulis sebelum kamu yakin:"
    grep -E '^(ID|VERSION)' /etc/os-release || true
    if [ "$CEK" -eq 0 ]; then
        echo "            (hentikan dengan Ctrl+C, atau jalankan ulang dengan --cek)"
        sleep 8
    fi
fi

jalan() {
    if [ "$CEK" -eq 1 ]; then
        echo "   [cek] $*"
    else
        echo "   ..    $*"
        "$@"
    fi
}

tulis() {
    # tulis <isi> <path> — di mode --cek hanya nama file yang ditampilkan.
    local isi="$1" path="$2"
    if [ "$CEK" -eq 1 ]; then
        echo "   [cek] menulis $path"
        return 0
    fi
    printf '%s\n' "$isi" > "$path"
}

# ── 1. Jangan menimpa kerja orang ───────────────────────────────────────────────────
# Urutan yang dipakai docs/deploy-vm.md: `git clone` DULU, baru skrip ini — karena
# kepemilikan folder disetel di langkah 4/6, kloning belakangan akan meninggalkan berkas
# milik user SSH dan Apache (www-data) tidak bisa menulis `storage/`. Jadi folder yang
# berisi HASIL CHECKOUT PRADANA bukan alasan untuk berhenti; yang harus ditolak adalah
# folder berisi sesuatu yang bukan aplikasi ini.
if [ -e "$APP_DIR" ] && [ "$(ls -A "$APP_DIR" 2>/dev/null)" != "" ]; then
    if [ -f "$APP_DIR/artisan" ] && [ -f "$APP_DIR/composer.json" ]; then
        echo "   $APP_DIR berisi checkout PRADANA (ada artisan + composer.json) — lanjut,"
        echo "   kepemilikan foldernya nanti disetel ke www-data di langkah 4/6."
    else
        echo "GAGAL: $APP_DIR sudah berisi sesuatu yang bukan checkout PRADANA." >&2
        echo "       Skrip ini tidak akan menimpanya. Pindahkan dulu, atau pakai --app-dir= lain." >&2
        exit 1
    fi
fi

# ── 2. Paket dasar ──────────────────────────────────────────────────────────────────
echo "-- 1/6 paket dasar"
DEBIAN_FRONTEND=noninteractive jalan apt-get update -qq
# software-properties-common: perlu untuk add-apt-repository (PPA PHP).
DEBIAN_FRONTEND=noninteractive jalan apt-get install -y -qq \
    ca-certificates curl unzip git software-properties-common

# ── 3. PHP 8.2 ──────────────────────────────────────────────────────────────────────
# Kenapa 8.2 dan bukan 8.3 bawaan Ubuntu 24.04: 8.2.12 adalah versi yang diuji di mesin
# pengembang (XAMPP) dan di CI (shivammathur/setup-php php-version 8.2). Menaiki ke 8.3
# bukan masalah yang diketahui, tapi juga bukan sesuatu yang sudah dibuktikan — dan
# perbedaan versi PHP paling sering muncul sebagai error yang baru kelihatan saat ada
# orang menekan tombol. Pakai `--php83` kalau memang mau ikut bawaan distribusi.
echo "-- 2/6 PHP $PHP_VERSI"
if [ "$PHP_VERSI" = "8.2" ]; then
    if ! apt-cache policy "php${PHP_VERSI}-cli" 2>/dev/null | grep -q "Candidate: 8.2"; then
        jalan add-apt-repository -y ppa:ondrej/php
        DEBIAN_FRONTEND=noninteractive jalan apt-get update -qq
    fi
fi
DEBIAN_FRONTEND=noninteractive jalan apt-get install -y -qq \
    "libapache2-mod-php${PHP_VERSI}" "php${PHP_VERSI}-cli" \
    "php${PHP_VERSI}-mysql" "php${PHP_VERSI}-xml" "php${PHP_VERSI}-curl" \
    "php${PHP_VERSI}-mbstring" "php${PHP_VERSI}-zip" "php${PHP_VERSI}-gd" \
    "php${PHP_VERSI}-intl" "php${PHP_VERSI}-bcmath" "php${PHP_VERSI}-opcache"

# ── 4. MariaDB ──────────────────────────────────────────────────────────────────────
# engine-nya sengaja SAMA dengan mesin pengembang (MariaDB, bukan MySQL 8): SQL yang
# dipakai aplikasi ini (`LIKE ? ESCAPE '!'`, `CASE` di ORDER BY, dan perilaku
# restrictOnDelete yang di MariaDB jadi NO ACTION) sudah diuji di MariaDB sungguhan,
# bukan di SQLite saja. Rinciannya ada di docs/kesiapan-deploy.md H8.
echo "-- 3/6 MariaDB"
DEBIAN_FRONTEND=noninteractive jalan apt-get install -y -qq mariadb-server mariadb-client
jalan systemctl enable --now mariadb

# Kredensial user aplikasi: dibuat acak, ditulis ke berkas 0600 milik root, dan TIDAK
# pernah lewat baris perintah. Alasannya sama dengan `arsip:backup-db`: argumen proses
# terlihat di `ps aux` / Task Manager siapa pun yang punya akses ke mesin itu.
KREDENSIAL="/root/pradana-db-kredensial.txt"
if [ "$CEK" -eq 0 ]; then
    if [ -f "$KREDENSIAL" ]; then
        echo "   kredensial lama sudah ada di $KREDENSIAL — tidak ditimpa (user DB tidak dibuat ulang)"
    else
        DB_PASS="$(tr -dc 'A-Za-z0-9' < /dev/urandom | head -c 28)"
        umask 077
        {
            echo "# Dipakai di .env server PRADANA. Jangan salin ke repo, chat, atau laporan."
            echo "DB_DATABASE=$DB_NAMA"
            echo "DB_USERNAME=$DB_USER"
            echo "DB_PASSWORD=$DB_PASS"
        } > "$KREDENSIAL"
        chmod 600 "$KREDENSIAL"

        TMP_CNF="$(mktemp)"
        chmod 600 "$TMP_CNF"
        printf '[client]\nuser=root\nsocket=/run/mysqld/mysqld.sock\n' > "$TMP_CNF"
        mysql --defaults-extra-file="$TMP_CNF" <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB_NAMA\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON \`$DB_NAMA\`.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
SQL
        rm -f "$TMP_CNF"
        echo "   user DB dibuat; kredensial ada di $KREDENSIAL (0600, milik root)"
    fi
else
    echo "   [cek] CREATE DATABASE \`$DB_NAMA\` (utf8mb4_unicode_ci) + user '$DB_USER'@'localhost'"
    echo "   [cek] kredensial acak ditulis ke $KREDENSIAL (0600) — tidak lewat baris perintah"
fi

# ── 5. Apache: DocumentRoot harus public/ ───────────────────────────────────────────
# Ini bukan selera. Kalau DocumentRoot menunjuk akar aplikasi, maka `.env`,
# `storage/app/private/arsip` (lampiran kantor) dan `storage/app/private/db-backup`
# (isi database) menjadi berkas web yang bisa diambil siapa pun yang menebak jalurnya.
echo "-- 4/6 Apache"
DEBIAN_FRONTEND=noninteractive jalan apt-get install -y -qq apache2
# Modul ini yang dipakai public/.htaccess. `filter` bukan hiasan: AddOutputFilterByType
# dimiliki mod_filter, dan tanpa modul itu blok kompresi membuat SELURUH situs 500
# (terjadi sungguhan 12 Okt; lihat docs/deploy.md).
jalan a2enmod rewrite filter deflate expires headers mime dir
# Satu penulis, satu pemilik. Semua yang ditulis PHP — `.env`, `storage/logs`,
# `storage/framework/{views,sessions}`, lampiran kantor di `storage/app/private/arsip`,
# dump `db-backup/` — harus dimiliki user yang sama, termasuk saat `php artisan migrate`
# dijalankan dari SSH. Kalau foldernya dibiarkan milik user SSH dengan 755, Apache
# (www-data) tidak bisa menulis log dan unggahan menjawab 500; kalau sebaliknya,
# `bin/deploy.sh` yang dijalankan user SSH mati dengan "Permission denied" justru di
# langkah paling menentukan. www-data yang punya segalanya + perintah artisan dipanggil
# lewat `sudo -u www-data` (dicetak di bagian bawah) adalah bentuk yang paling sedikit
# kejutan — dan cron di langkah 7 juga memakai www-data, jadi ketiga jalur penulis itu
# satu user.
if [ "$CEK" -eq 0 ]; then
    if [ -d "$APP_DIR" ] && [ -n "$(ls -A "$APP_DIR" 2>/dev/null)" ]; then
        echo "   $APP_DIR sudah berisi (hasil git clone?) — tidak ada yang dihapus,"
        echo "   hanya kepemilikannya disetel ke www-data."
    fi
    mkdir -p "$APP_DIR"
fi
jalan chown -R www-data:www-data "$APP_DIR"

# Catatan penulisan: ini TIDAK memakai `$(cat <<VHOST ... VHOST)`. Bentuk itu menjebak
# di sini karena baris penutup heredoc harus SAMA PERSIS dengan kata kuncinya, dan orang
# yang menulis `VHOST)` (dengan kurung penutup perintah) akan mendapat berkas yang
# isinya setengah konfigurasi — bash sendiri sudah memperingatkan ini waktu skripnya
# diperiksa (`warning: here-document delimited by end-of-file`).
VHOST_ISI="<VirtualHost *:80>
    ServerName _
    DocumentRoot $APP_DIR/public
    <Directory $APP_DIR/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    # Berkas di luar public/ tidak boleh tersaji meski ada yang salah arah.
    <DirectoryMatch \"^$APP_DIR/(app|bootstrap|config|database|resources|routes|storage|tests|vendor)/\">
        Require all denied
    </DirectoryMatch>
    ErrorLog \${APACHE_LOG_DIR}/pradana-error.log
    CustomLog \${APACHE_LOG_DIR}/pradana-access.log combined
</VirtualHost>"
tulis "$VHOST_ISI" /etc/apache2/sites-available/pradana.conf

if [ "$CEK" -eq 0 ]; then
    jalan a2dissite 000-default
    jalan a2ensite pradana
else
    echo "   [cek] a2dissite 000-default && a2ensite pradana"
fi

# ── 6. php.ini: batas unggah + zona waktu + OPcache ─────────────────────────────────
# Dua file, bukan satu: mod_php membaca `.../apache2/php.ini`, `php artisan` membaca
# `.../cli/php.ini`. Mengedit yang CLI saja membuat unggah tetap ditolak di web — dan
# gejalanya "tombol Unggah diam" (L-13: lampiran sampai 25 MB, jadi 26 M+ dua-duanya).
echo "-- 5/6 php.ini ($PHP_VERSI)"
for SAPI in apache2 cli; do
    INI="/etc/php/${PHP_VERSI}/${SAPI}/php.ini"
    if [ "$CEK" -eq 1 ]; then
        echo "   [cek] menyetel batas unggah/zona/OPcache di $INI"
        continue
    fi
    if [ ! -f "$INI" ]; then
        echo "   dilewati: $INI tidak ada"
        continue
    fi
    for SET in \
        "upload_max_filesize = 32M" \
        "post_max_size = 40M" \
        "memory_limit = 512M" \
        "max_execution_time = 120" \
        "date.timezone = $ZONA" \
        "opcache.enable = 1" \
        "opcache.memory_consumption = 192" \
        "opcache.max_accelerated_files = 20000" \
        "opcache.validate_timestamps = 1"; do
        KUNCI="${SET%% *}"
        if grep -qE "^[;]?${KUNCI}[[:space:]]*=" "$INI"; then
            sed -i -E "s|^[;]?${KUNCI}[[:space:]]*=.*|${SET}|" "$INI"
        else
            printf '\n%s\n' "$SET" >> "$INI"
        fi
    done
    echo "   $INI disetel"
done

# `opcache.validate_timestamps = 1` dibiarkan NYALA. Sengaja: mematikannya membuat deploy
# berikutnya "tidak kelihatan" sampai PHP-FPM/Apache di-restart, dan itu jebakan yang
# mahal untuk orang yang tidak tahu. Penghematannya kecil (terukur 12 Okt: cache build
# tidak mengubah waktu steady di mesin ini), risikonya tidak sebanding.

# ── 7. Composer ─────────────────────────────────────────────────────────────────────
echo "-- 6/6 Composer + cron"
if command -v composer >/dev/null 2>&1; then
    echo "   sudah ada: $(composer --version 2>/dev/null | head -1)"
elif [ "$CEK" -eq 1 ]; then
    echo "   [cek] unduh installer resmi + verifikasi SHA-384, pasang ke /usr/local/bin/composer"
else
    EXPECTED="$(curl -sS https://composer.github.io/installer.sig)"
    curl -sS https://getcomposer.org/installer -o /tmp/composer-setup.php
    ACTUAL="$(php -r "echo hash('sha384', file_get_contents('/tmp/composer-setup.php'));")"
    if [ "$EXPECTED" != "$ACTUAL" ]; then
        echo "GAGAL: SHA-384 installer Composer tidak cocok. Tidak ada yang diinstal." >&2
        rm -f /tmp/composer-setup.php
        exit 1
    fi
    php /tmp/composer-setup.php --install-dir=/usr/local/bin --filename=composer
    rm -f /tmp/composer-setup.php
    echo "   composer terpasang: $(composer --version | head -1)"
fi

# Jadwal: 4 pekerjaan hidup di app/Console/Kernel.php (backup DB 02:10, sinkron Drive,
# arsip usang, pembersih log). Tanpa baris ini tidak satu pun jalan, dan tidak ada yang
# sadar sampai cadangan dibutuhkan. Format /etc/cron.d menuntut KOLOM USER — www-data,
# karena itu pemilik file di storage/.
tulis "# PRADANA — Laravel scheduler. Satu menit sekali; isi jadwalnya ada di
# app/Console/Kernel.php. Log di /var/log/pradana-cron.log supaya kegagalannya
# kelihatan, bukan hilang ke /dev/null.
* * * * * www-data cd $APP_DIR && /usr/bin/php${PHP_VERSI} artisan schedule:run >> /var/log/pradana-cron.log 2>&1
" /etc/cron.d/pradana
jalan chmod 644 /etc/cron.d/pradana
DEBIAN_FRONTEND=noninteractive jalan apt-get install -y -qq cron
jalan systemctl enable --now cron

# ── 8. Firewall ─────────────────────────────────────────────────────────────────────
# Urutan penting: allow SSH SEBELUM enable. `ufw enable` dengan aturan default menolak
# semua input = kunci diri sendiri di luar VM, dan itu tidak bisa diperbaiki tanpa
# konsol Oracle. Di Oracle juga masih ada security list di level VCN yang harus
# mengizinkan 80/443 — ufw bukan satu-satunya pintu.
echo "-- firewall"
jalan ufw allow "${PORT_SSH_TETAP}/tcp"
jalan ufw allow 'Apache Full'
if [ "$CEK" -eq 1 ]; then
    echo "   [cek] ufw --force enable"
else
    if ufw --force enable; then
        echo "   ufw aktif (22 + 80/443)"
    else
        echo "   ufw tidak tersedia — tidak berbahaya, tapi berarti satu-satunya penjaga"
        echo "   adalah security list Oracle. Cek manual."
    fi
fi

if [ "$CEK" -eq 1 ]; then
    echo
    echo "--cek selesai. Tidak ada yang ditulis. Jalankan tanpa --cek untuk memasang."
    exit 0
fi

# ── 9. Reload + pesan lanjutan ──────────────────────────────────────────────────────
jalan systemctl reload apache2

echo
echo "Server siap. Yang BELUM (dan memang tidak boleh dikerjakan skrip ini):"
echo
echo "  1. Kodenya harus ADA di $APP_DIR. Dua cara yang benar:"
echo "       a) SEBELUM skrip ini (urutan di docs/deploy-vm.md bagian 4): kloning ke folder"
echo "          kosong sebagai user SSH, lalu skrip menyetel kepemilikannya ke www-data;"
echo "       b) SESUDAH skrip ini: kloning sebagai user PHP, jadi pemiliknya langsung benar:"
echo "            sudo -u www-data git clone https://github.com/a-ali-wafa/Pradana.git $APP_DIR"
echo "     Yang salah: kloning sebagai user SSH sesudah skrip jalan — foldernya sudah"
echo "     dimiliki www-data, dan berkas baru milik user SSH membuat Apache tidak bisa"
echo "     menulis storage/. Perbaikinya: sudo chown -R www-data:www-data $APP_DIR"
echo "     (repo ini publik; kalau nanti pindah ke repo privat, pakai kunci milik"
echo "      www-data — bukan kredensial pribadimu)"
echo
echo "  2. Buat .env — JANGAN disalin dari laptop. Semua perintah artisan di bawah"
echo "     berjalan sebagai www-data (lihat alasan kepemilikan di langkah 4/6):"
echo "       sudo -u www-data env HOME=/tmp bash -c 'cd $APP_DIR && cp .env.example .env && php artisan key:generate'"
echo "       sudo -u www-data env HOME=/tmp nano $APP_DIR/.env"
echo "     lalu isi dari $KREDENSIAL :"
echo "       APP_ENV=production  APP_DEBUG=false  APP_TIMEZONE=$ZONA"
echo "       DB_HOST=127.0.0.1  DB_DATABASE=$DB_NAMA  DB_USERNAME=$DB_USER  DB_PASSWORD=<di berkas itu>"
echo "       APP_URL=http://<IP publik VM>   (https begitu ada domain + sertifikat)"
echo "       MYSQLDUMP_PATH=/usr/bin/mysqldump"
echo
echo "  3. Pasang aplikasinya:"
echo "       sudo -u www-data env HOME=/tmp bash -c 'cd $APP_DIR && bash bin/deploy.sh'"
echo "     (env HOME=/tmp bukan hiasan: composer menulis ke \$HOME, dan www-data tidak"
echo "      punya folder home — tanpa ini composer install buang peringatan dan cache-nya"
echo "      tidak ke mana-mana)"
echo
echo "  4. Akun pertama (satu-satunya jalan membuat admin setelah seeder dilucuti):"
echo "       sudo -u www-data env HOME=/tmp bash -c 'cd $APP_DIR && php artisan arsip:akun-pertama <email> --nama=\"<nama>\"'"
echo "     PIN-nya 8 digit dan diminta di terminal. Kalau ini VM demo, jangan pakai"
echo "     PIN yang tertulis di dokumen (nilai dev) karena VM ini punya IP publik."
echo
echo "  5. Bukti, bukan perasaan — jalankan checklist di docs/kesiapan-deploy.md bagian 7."
echo "     Termasuk: buka http://<IP>/.env dan harus 404, lalu"
echo "       sudo -u www-data env HOME=/tmp bash -c 'cd $APP_DIR && php artisan arsip:backup-db'"
echo "     dan uji pulih sekali SEBELUM ada data yang disayangi."
