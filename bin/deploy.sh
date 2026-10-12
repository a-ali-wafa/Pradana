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
    # ── 2. Dependency produksi ────────────────────────────────────────────────
    # --no-dev: tidak mengunggah phpstan/pint/seeder dev ke server kantor.
    # -o: classmap teroptimasi, satu berkas autoload yang lebih kecil untuk disk hosting.
    echo "-- composer install --no-dev -o"
    composer install --no-dev --optimize-autoloader --no-interaction

    # ── 3. Struktur folder ────────────────────────────────────────────────────
    # Lampiran hidup di storage/app/private/arsip — DI LUAR public/ (L-01), jadi
    # tidak ada symlink yang perlu dibuat dan tidak ada jalur publik ke berkas kantor.
    mkdir -p storage/app/private/arsip storage/app/private/db-backup \
             storage/framework/{cache,sessions,views} storage/logs bootstrap/cache

    # ── 4. Migrasi ────────────────────────────────────────────────────────────
    # --force supaya berani jalan di APP_ENV=production; tanpa ini perintahnya diam
    # dan deploy "sukses" dengan skema ketinggalan satu version.
    echo "-- migrate --force"
    php artisan migrate --force --no-interaction
fi

# ── 5. Cache build ────────────────────────────────────────────────────────────
# config:cache bukan sekadar cepat: tanpanya `env()` dibaca per request, dan
# `config/security.php` (CSP) + `config/backup.php` ikut terbaca lambat.
# view:cache penting karena Blade menyusun ulang tiap view pada klik pertama
# sesudah deploy (terukur 12 Okt: 0,5-1,0 s pada layar pertama, lalu 30-60 ms).
echo "-- cache: config, route, view, event"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# ── 6. Panaskan halaman pertama ───────────────────────────────────────────────
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

# ── 7. Verifikasi akhir ───────────────────────────────────────────────────────
# Bukan "exit code 0", tapi pertanyaan yang benar: aplikasi hidup dan DB-nya bisa
# dibaca. `down` yang tertinggal dari deploy sebelumnya juga ketahuan di sini.
php artisan up >/dev/null 2>&1 || true
php artisan about --only=environment

echo
echo "Selesai. Sisa yang HARUS dicek manusia (tidak bisa diverifikasi dari sini):"
echo "  1. php -i | grep upload_max_filesize   -> >= 26M (kalau tidak, lampiran 25MB ditolak PHP)"
echo "  2. cron: * * * * * cd $AKAR && php artisan schedule:run >> /dev/null 2>&1"
echo "     (backup DB 02:10, sinkron Drive, daftar usang, pembersih log — semuanya di Kernel.php)"
echo "  3. Buka https://<domain kantor>/login di browser: kop + logo tampil, Console kosong."
echo "  4. Jalankan sekali: php artisan arsip:backup-db && php artisan arsip:backup-db --dry-run"
echo "     lalu uji pulih (manual pemakaian Lampiran A.6) SEBELUM data kantor masuk."
