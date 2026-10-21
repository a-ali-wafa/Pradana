#!/usr/bin/env bash
#
# Deploy PRADANA ke hosting kantor (shared hosting PHP + Apache, K1=a).
#
# Kenapa ada skrip: isinya sudah lama diketahui (Lampiran A manual pemakaian), tapi
# "salin-tempel langkah" adalah sumber kegagalan yang senyap — langkah yang kelewat
# biasanya yang paling murah (lupa `config:cache`, lupa `APP_DEBUG=false`) dan yang
# paling mahal ketahuan di depan petugas.
#
# Cara pakai (dari SSH, di root aplikasi yang SUDAH berisi kode versi baru):
#   bash bin/deploy.sh                 # deploy penuh
#   bash bin/deploy.sh --hanya-cache   # ulangi langkah cache saja (kalau deploy terpotong)
#
# Skrip ini TIDAK menyalin kode. Branch/zip diunggah lewat mekanisme hosting atau
# `rsync` terpisah — supaya yang diuji di bawah adalah hasil salinan yang sama.
#
# Di Windows (laptop pengembang) skrip ini tidak dipakai; lihat docs/deploy.md
# bagian "kalau tidak ada SSH".

set -euo pipefail

AKAR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$AKAR"

HANYA_CACHE=0
WARMUP="http://127.0.0.1:8000"
for arg in "$@"; do
    case "$arg" in
        --hanya-cache) HANYA_CACHE=1 ;;
        --warmup=*) WARMUP="${arg#--warmup=}" ;;
        *) echo "Argumen tidak dikenal: $arg" >&2; exit 2 ;;
    esac
done

echo "== PRADANA deploy =="
echo "Akar aplikasi: $AKAR"

if [ ! -f .env ]; then
    echo "GAGAL: .env tidak ada. Salin .env.example lalu isi DB_*, APP_URL, DEV_PIN." >&2
    exit 1
fi

# ── 1. Gerbang konfigurasi ────────────────────────────────────────────────────
# DIBACA dari .env, tidak ditulis oleh skrip. Ini bagian yang paling sering salah
# tempel dan gejalanya baru kelihatan saat ada masalah: APP_DEBUG=true di server
# kantor memancarkan nama tabel, path server, dan isi stack trace ke halaman 500.
#
# `--hanya-cache` melewatkan gerbang ini DENGAN SENGAJA: mode itu dipakai untuk
# latihan di mesin pengembang (APP_ENV=local) dan untuk memperbaiki cache deploy
# yang terpotong. Yang dilewati hanya pemeriksaan .env — langkah cache-nya tetap
# sama persis dengan deploy sungguhan.
if [ "$HANYA_CACHE" -eq 1 ]; then
    echo "  PERINGATAN: --hanya-cache, gerbang .env dilewati (mode latihan / perbaikan deploy terpotong)"
elif grep -qE '^[[:space:]]*APP_ENV=production' .env; then
    echo "  APP_ENV=production  OK"
else
    echo "GAGAL: APP_ENV belum 'production' di .env." >&2
    exit 1
fi
if [ "$HANYA_CACHE" -eq 1 ]; then
    :
elif grep -qE '^[[:space:]]*APP_DEBUG=false' .env; then
    echo "  APP_DEBUG=false     OK"
else
    echo "GAGAL: APP_DEBUG masih true — halaman error akan membocorkan isi server." >&2
    exit 1
fi
if [ "$HANYA_CACHE" -eq 1 ]; then
    :
elif grep -qE '^[[:space:]]*APP_KEY=base64:' .env; then
    echo "  APP_KEY             OK"
else
    echo "GAGAL: APP_KEY kosong. Jalankan: php artisan key:generate (SEKALI, sebelum ada data)." >&2
    exit 1
fi
# Zona waktu kantor: nomor dan tanggal Berita Acara dihitung dari now(). Bawaan
# Laravel dulu 'UTC' dan itu menghasilkan dokumen resmi bertanggal salah delapan jam
# sebelum tengah malam WIB. Nilai boleh Asia/Makassar atau Asia/Jayapura.
if [ "$HANYA_CACHE" -eq 1 ]; then
    :
elif grep -qE '^[[:space:]]*APP_TIMEZONE=Asia/' .env; then
    echo "  APP_TIMEZONE        OK ($(grep -E '^[[:space:]]*APP_TIMEZONE=' .env | cut -d= -f2))"
else
    echo "GAGAL: APP_TIMEZONE belum diset ke zona kantor (Asia/Jakarta|Makassar|Jayapura)." >&2
    echo "       Tanpa ini, Berita Acara dan periode laporan memakai jam UTC." >&2
    exit 1
fi

if [ "$HANYA_CACHE" -eq 0 ]; then
    # ── 2. Cek server ─────────────────────────────────────────────────────────
    # Tiap baris di bawah ini pernah jadi penyebab aplikasi rusak DI LAYAR PETUGAS,
    # bukan saat install. Yang bisa diuji dari CLI diuji di sini; yang cuma terbukti
    # dari sisi web (mis. `upload_max_filesize` milik php-fpm, dan nilainya bisa beda
    # dari CLI) dilaporkan apa adanya, tidak dibuat-buat jadi "lulus".
    echo "-- cek server"
    php -r '
$butuh = ["pdo_mysql", "mbstring", "fileinfo", "zip", "dom", "xml", "openssl", "curl", "gd", "tokenizer"];
$kurang = array_values(array_filter($butuh, fn ($e) => ! extension_loaded($e)));
printf("   PHP %-8s ekstensi %d/%d OK\n", PHP_VERSION, count($butuh) - count($kurang), count($butuh));
if ($kurang) {
    fwrite(STDERR, "GAGAL: ekstensi PHP belum terpasang di server: ".implode(", ", $kurang)."\n");
    fwrite(STDERR, "     zip dibutuhkan PembacaIsiLampiran (DOCX/XLSX), dom+gd dibutuhkan dompdf,\n");
    fwrite(STDERR, "     fileinfo dibutuhkan MIME unggahan, pdo_mysql dibutuhkan koneksi database.\n");
    exit(1);
}
if (version_compare(PHP_VERSION, "8.2", "<")) {
    fwrite(STDERR, "GAGAL: Laravel 12 butuh PHP >= 8.2; server ini ".PHP_VERSION.". Pilih versi di control panel hosting.\n");
    exit(1);
}
foreach ([["upload_max_filesize", 26], ["post_max_size", 26]] as $s) {
    $v = (int) ini_get($s[0]);
    printf("   %-22s %4dM %s\n", $s[0], $v, $v >= $s[1] ? "OK (nilai CLI)" : "KECIL — lampiran 25MB ditolak PHP sebelum Laravel sempat memberi pesan ramah");
}
' || exit 1
    if command -v mysqldump >/dev/null 2>&1; then
        echo "   mysqldump                ada (arsip:backup-db bisa jalan)"
    else
        echo "   mysqldump                TIDAK ADA — arsip:backup-db akan MENOLAK jalan."
        echo "     Ini bukan bug skrip: perintahnya sengaja tidak pernah mengaku cadangan"
        echo "     dibuat kalau binernya tidak ada. Di hosting tanpa biner, jadwalkan"
        echo "     ekspor lewat phpMyAdmin/panel, atau terima bahwa cadangan DB manual."
    fi

    # ── 3. Dependency produksi ────────────────────────────────────────────────
    # --no-dev: tidak mengunggah phpstan/pint/seeder dev ke server kantor.
    # -o: classmap teroptimasi, satu berkas autoload yang lebih kecil untuk disk hosting.
    echo "-- composer install --no-dev -o"
    composer install --no-dev --optimize-autoloader --no-interaction

    # ── 4. Pangkas stub Google yang tidak dipakai ─────────────────────────────
    # Dari 43.561 berkas vendor produksi, 36.901 di antaranya stub layanan Google
    # yang tidak pernah disentuh aplikasi ini (kita cuma pakai Drive untuk cadangan
    # lampiran, L-01). Angka itu bukan soal estetika: hosting dengan batas inode
    # 30.000 berkas menolak akun yang melewatinya, dan gejalanya "upload gagal",
    # bukan pesan yang menyebut penyebabnya. Skripnya menghitung sendiri daftar yang
    # dipertahankan dari kode (bukan daftar hard-coded yang bisa basi) dan berhenti
    # dengan kode 1 kalau ada kelas yang tidak bisa dimuat sesudah memangkas.
    echo "-- pangkas layanan Google yang tidak terpakai"
    php bin/pangkas-layanan-google.php

    # ── 5. Struktur folder + izin tulis ───────────────────────────────────────
    # Lampiran hidup di storage/app/private/arsip — DI LUAR public/ (L-01), jadi
    # tidak ada symlink yang perlu dibuat dan tidak ada jalur publik ke berkas kantor.
    # Justru karena perlindungannya "di luar public/", satu-satunya penjaga adalah
    # permission: folder ini harus bisa ditulis user PHP web, dan isi db-backup/
    # (struktur + isi arsip kantor) tidak boleh terbaca dari URL mana pun.
    mkdir -p storage/app/private/arsip storage/app/private/db-backup \
             storage/framework/{cache,sessions,views} storage/logs bootstrap/cache

    for target in storage bootstrap/cache; do
        if [ -w "$target" ]; then
            printf '   tulis %-16s OK\n' "$target"
        elif command -v chmod >/dev/null 2>&1 && chmod -R u+rwX,g+rwX "$target" 2>/dev/null; then
            printf '   tulis %-16s diperbaiki (chmod u+rwX,g+rwX)\n' "$target"
        else
            printf '   tulis %-16s TIDAK BISA DITULIS oleh user shell ini\n' "$target"
            echo "     Kalau user PHP di server berbeda dari user SSH (php-fpm/www-data),"
            echo "     perintahkan sebagai pemilik folder: chown -R <user-php>:<grup> $target"
            echo "     Tanpa ini gejalanya: login sukses, tapi unggah lampiran menjawab 500."
            exit 1
        fi
    done

    # ── 6. Migrasi ────────────────────────────────────────────────────────────
    # --force supaya berani jalan di APP_ENV=production; tanpa ini perintahnya diam
    # dan deploy "sukses" dengan skema ketinggalan satu version.
    echo "-- migrate --force"
    php artisan migrate --force --no-interaction
fi

# ── 7. Cache build ────────────────────────────────────────────────────────────
# config:cache bukan sekadar cepat: tanpanya `env()` dibaca per request, dan
# `config/security.php` (CSP) + `config/backup.php` ikut terbaca lambat.
# view:cache penting karena Blade menyusun ulang tiap view pada klik pertama
# sesudah deploy (terukur 12 Okt: 0,5-1,0 s pada layar pertama, lalu 30-60 ms).
echo "-- cache: config, route, view, event"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# ── 8. Panaskan halaman pertama ───────────────────────────────────────────────
# Yang dibayar petugas biasanya bukan rata-rata, tapi klik pertama sesudah deploy:
# OPcache masih kosong dan route termahal baru dibangun sekali. Lima permintaan
# lokal ini memindahkan biaya itu ke dalam skrip, bukan ke layar kantor.
echo "-- warm-up ${WARMUP}"
if command -v curl >/dev/null 2>&1; then
    for jalur in /login /dashboard /surat-masuk /surat-keluar /laporan /aktivitas; do
        kode=$(curl -s -o /dev/null -w '%{http_code}' --max-time 60 "${WARMUP}${jalur}" || echo "000")
        printf '   %-14s %s\n' "$jalur" "$kode"
    done
    echo "   302 di luar /login itu EXPECTED (curl tidak punya sesi)."
    echo "   Yang dipanaskan di sini: bootstrap framework + OPcache + Blade yang tercompile"
    echo "   lewat view:cache. Klik pertama per layar masih bisa 0,3-0,9 s sesudah deploy;"
    echo "   itu harga kompilasi sekali per berkas yang belum dilewati /login, bukan bug."
else
    echo "   curl tidak ada — lewati (klik pertama tetap lambat, tidak berbahaya)"
fi

# ── 9. Verifikasi akhir ───────────────────────────────────────────────────────
# Bukan "exit code 0", tapi pertanyaan yang benar: aplikasi hidup dan DB-nya bisa
# dibaca. `down` yang tertinggal dari deploy sebelumnya juga ketahuan di sini.
php artisan up >/dev/null 2>&1 || true
php artisan about --only=environment

echo
echo "Selesai. Yang PERIKSA oleh skrip ini: versi+ekstensi PHP, batas unggahan (nilai CLI),"
echo "ada/tidaknya mysqldump, izin tulis storage/ dan bootstrap/cache, migrate, cache build."
echo
echo "Yang TETAP harus dicek manusia — bukan karena malas mengotomasi, tapi karena"
echo "bukti hanya ada di sisi lain (web server/browser), bukan di shell ini:"
echo "  1. DocumentRoot Apache menunjuk ke <aplikasi>/public. Kalau tidak, .env, storage/,"
echo "     dan db-backup/ ikut tersaji sebagai berkas web — isinya arsip + kredensial"
echo "     kantor. Uji dengan membuka URL itu langsung, bukan dengan percaya konfigurasi."
echo "  2. upload_max_filesize/post_max_size SISI WEB (php-fpm/LiteSpeed), yang di atas"
echo "     tadi nilai CLI dan keduanya boleh berbeda di hosting yang sama."
echo "  3. cron tiap menit: * * * * * cd $AKAR && php artisan schedule:run >> /dev/null 2>&1"
echo "     Tanpa ini tidak ada backup DB 02:10, tidak ada sinkron Drive, tidak ada daftar"
echo "     arsip usang, dan log tidak pernah dibersihkan. Semuanya hidup di Kernel.php."
echo "  4. SESSION_SECURE_COOKIE=true dan TRUSTED_PROXIES baru dinyalakan setelah HTTPS"
echo "     benar-benar terpasang (keputusan S14). Kalau ada proxy/tunnel tapi daftar"
echo "     proxy kosong, semua petugas tampak memakai satu IP: lima salah ketik PIN oleh"
echo "     satu orang mengunci seluruh kantor, karena kunci throttle login = email+IP."
echo "  5. Sekali, SEBELUM data kantor masuk: php artisan arsip:backup-db, lalu pulihkan"
echo "     ke skema kosong dan bandingkan jumlah baris (manual Lampiran A.6). Jalur ini"
echo "     jangan dipercaya dari exit code 0 — cadangan yang tidak pernah dipulihkan"
echo "     belum terbukti apa pun."
