#!/usr/bin/env bash
#
# Buat VM Azure untuk PRADANA (Ubuntu 24.04 + Standard_B1s + IP publik Standard).
#
# Kenapa skrip terpisah dari bin/sediakan-server.sh: yang itu menyiapkan isi DI DALAM
# server (Apache, PHP, MariaDB, cron, swap); yang ini mencetak SERVER-nya. Dipisah
# karena keduanya gagal dengan cara yang berbeda, dan yang ini bisa gagal di tengah —
# sebagian resource sudah terlanjur dibuat — jadi setiap langkah di sini diperiksa
# dulu dan skripnya layak dijalankan ulang.
#
# Kenapa region dicek lebih dulu: langganan mahasiswa berada di belakang kebijakan
# pembatas region, dan penolakannya tidak pernah muncul sebagai pesan kebijakan. Di
# region yang tidak diizinkan `az vm list-usage` dan `az vm list-skus` mengembalikan
# daftar KOSONG — terbaca persis seperti "kuota VM nol" — sementara pesan aslinya
# ("RequestDisallowedByAzure") baru keluar saat resource pertama benar-benar dibuat,
# yaitu setelah resource group ada. Cek di depan mengubah kegagalan setengah jadi
# menjadi satu baris pesan.
#
# Kenapa daftar kosong tidak pernah dipercaya sebagai "tidak tersedia": kedua perintah
# itu mengukur visibilitas, bukan izin. Yang membuktikan ukuran bisa dipakai hanya
# `az vm create`, dan angka kuota yang sebenarnya muncul begitu lokasinya legal.
#
# Skrip ini tidak pernah menghapus apa pun, dan itu penting untuk diingat saat membaca
# kegagalan region: jalan keluar dari "ukuran ini ditolak di sini" adalah membuat group di
# region lain, BUKAN `az group delete` pada group yang lama — di dalamnya bisa ada VM yang
# sedang dikerjakan, dan delete grup menghapus disk-nya sekalian, tanpa konfirmasi. Kalau
# memang harus pindah: `az resource list -g <grup>` dulu, dan kalau ada VM-nya, pindahkan
# disknya (snapshot) atau selesaikan dulu pekerjaan di atasnya.
#
# Cara pakai:
#   az login                              # buka browser, pakai akun mahasiswa
#   bash bin/buat-vm-azure.sh --cek       # lihat rencana, tidak membuat apa pun
#   bash bin/buat-vm-azure.sh             # jalankan
#
# Yang TIDAK dilakukan skrip ini: membuka port 80. NSG-nya cuma SSH. Port web sengaja
# ditunggu sampai `curl http://<IP>/.env` terbukti kosong — membuka 80 sebelum docroot
# benar sama dengan memajang folder `.env` dan arsip lampiran ke internet.

set -euo pipefail

RG="${RG:-pradana-rg}"
# Default ini bukan tebakan dan bukan selera: `Standard_B1s` ditolak `SkuNotAvailable` di
# `indonesiacentral`, `malaysiawest`, dan `eastasia` (dengan kedua tipe keamanan), sementara
# di `indiasouthcentral` ukuran yang sama diterima dan VM-nya benar-benar dibuat. Region
# sendiri sudah dibatasi kebijakan langganan, jadi pilihan yang tersisa cuma di dalam daftar
# itu — dan yang bertahan adalah yang paling murah per jam.
LOKASI="${LOKASI:-indiasouthcentral}"
NAMA="${NAMA:-pradana-vm}"
UKURAN="${UKURAN:-Standard_B1s}"
IP="${IP:-pradana-ip}"
USER_VM="${USER_VM:-pradana}"
# Tipe keamanan VM diuji terhadap kenyataan, bukan diambil dari forum: sebagian
# kombinasi region + ukuran hanya mau salah satunya, dan galatnya (SkuNotAvailable) tidak
# menyebut hal itu. Ditulis sebagai variabel supaya percobaan dilakukan tanpa menyunting
# skrip: `TIPE_KEAMANAN=TrustedLaunch bash bin/buat-vm-azure.sh --cek`.
TIPE_KEAMANAN="${TIPE_KEAMANAN:-Standard}"
KUNCI="${KUNCI:-$HOME/.ssh/pradana_azure.pub}"
APP_DIR="/var/www/pradana"
REPO="${REPO:-https://github.com/a-ali-wafa/Pradana.git}"
CEK=0
CEK_UKURAN=0

for arg in "$@"; do
    case "$arg" in
        --cek) CEK=1 ;;
        --cek-ukuran) CEK_UKURAN=1 ;;
        --lokasi=*) LOKASI="${arg#--lokasi=}" ;;
        --ukuran=*) UKURAN="${arg#--ukuran=}" ;;
        --nama=*) NAMA="${arg#--nama=}" ;;
        --dns=*) DNS="${arg#--dns=}" ;;
        *) echo "Argumen tidak dikenal: $arg (yang ada: --cek --cek-ukuran --lokasi= --ukuran= --nama= --dns=)" >&2; exit 2 ;;
    esac
done

echo "== Buat VM Azure untuk PRADANA =="
echo "Resource group : $RG"
echo "Lokasi         : $LOKASI"
echo "Ukuran VM      : $UKURAN"
echo "Nama VM / IP   : $NAMA / $IP (Standard, statis)"
echo "User SSH       : $USER_VM"
echo "Kunci publik   : $KUNCI"
echo "Mode           : $([ "$CEK" -eq 1 ] && echo 'CEK SAJA — tidak ada resource yang dibuat' || echo 'JALAN')"
echo

if ! command -v az >/dev/null 2>&1; then
    echo 'GAGAL: `az` tidak ada di PATH. Pasang dulu: winget install --id Microsoft.AzureCLI' >&2
    echo "       (setelah pasang, buka terminal BARU — PATH lama tidak ikut terisi)" >&2
    exit 1
fi

if [ ! -f "$KUNCI" ]; then
    echo "GAGAL: kunci publik tidak ada di $KUNCI" >&2
    echo "       Buat dulu: ssh-keygen -t ed25519 -C pradana-azure-deploy -f ~/.ssh/pradana_azure -N \"\"" >&2
    exit 1
fi

# ── 1. Akun & region yang diizinkan ─────────────────────────────────────────────────
LANGGANAN="$(az account show --query 'name' -o tsv 2>/dev/null || true)"
if [ -z "$LANGGANAN" ]; then
    echo 'GAGAL: belum login ke Azure. Jalankan `az login` lebih dulu.' >&2
    exit 1
fi
echo "Langganan     : $LANGGANAN"

# Kebijakan pembatas region dibaca dari penugasan kebijakan di skala langganan, bukan
# dari perkiraan. Parameter yang dicari bernama sama di semua kebijakan semacam itu,
# jadi skrip ini tidak perlu tahu nama penugasannya — cukup ambil nilainya kalau ada.
IZIN=""
for PENUGASAN in $(az policy assignment list --query '[].name' -o tsv 2>/dev/null || true); do
    NILAI="$(az policy assignment show -n "$PENUGASAN" \
        --query 'parameters.listOfAllowedLocations.value' -o tsv 2>/dev/null || true)"
    if [ -n "$NILAI" ]; then
        IZIN="$(printf '%s' "$NILAI" | tr -d '\r')"
    fi
done
if [ -n "$IZIN" ]; then
    echo "Region diizinkan: $(printf '%s\n' "$IZIN" | tr '\n' ' ')"
    if ! printf '%s\n' "$IZIN" | grep -qx "$LOKASI"; then
        echo "GAGAL: $LOKASI tidak diizinkan langganan ini." >&2
        echo "       Yang terlihat di region begitu bukan pesan kebijakan, tapi daftar SKU" >&2
        echo "       dan kuota yang KOSONG — jadi jangan dicari penyebabnya di kuota." >&2
        echo "       Ulangi dengan salah satu nama di atas, misal --lokasi=$(printf '%s\n' "$IZIN" | head -1)" >&2
        exit 1
    fi
    echo "Lokasi $LOKASI ada di daftar itu."
else
    echo "Tidak ada kebijakan pembatas region di langganan ini — $LOKASI dipakai apa adanya."
fi
echo

# ── 2. Resource provider ────────────────────────────────────────────────────────────
# Langganan baru — termasuk Azure for Students — sering datang dengan Microsoft.Compute,
# Microsoft.Network dan Microsoft.Storage BELUM ter-register, dan gejalanya menyesatkan:
# `az vm list-skus` mengembalikan daftar kosong, `az vm list-usage` mengembalikan [],
# `az vm create` gagal tanpa mengirim deployment. Semua itu terbaca seperti "kuota VM
# nol" padahal belum diuji. Register itu idempoten dan tidak berbiaya.
for PROVIDER in Microsoft.Compute Microsoft.Network Microsoft.Storage; do
    STATE="$(az provider show -n "$PROVIDER" --query registrationState -o tsv 2>/dev/null || echo TidakDiketahui)"
    if [ "$STATE" != "Registered" ]; then
        if [ "$CEK" -eq 1 ]; then
            echo "   [cek] az provider register -n $PROVIDER   (status sekarang: $STATE)"
        else
            echo "Meregsitrasi $PROVIDER (status sekarang: $STATE) ..."
            az provider register -n "$PROVIDER" --query registrationState -o tsv
        fi
    else
        echo "$PROVIDER: sudah ter-register."
    fi
done
echo

# ── 3. Kuota & ukuran ────────────────────────────────────────────────────────────────
# Kuota regional. Baris yang dipakai adalah keluarga vCPU sesuai ukuran VM (B1s = BS
# Family), bukan baris "Virtual Machines" yang batasnya 25.000 dan tidak pernah menahan
# siapa pun. LABEL-nya ada di `localName`; `name.value` berisi kode internal
# ("standardBSFamily", "cores") — jadi menyaring pada name.value menghasilkan NOL BARIS,
# dan daftar kosong itu terbaca seperti "langganan ini tidak melaporkan kuota". Penyebab
# kedua daftar kosong adalah region yang tidak diizinkan kebijakan. Yang tidak boleh
# disimpulkan dari daftar kosong adalah "kuota habis".
KUOTA="$(az vm list-usage --location "$LOKASI" \
    --query "[?contains(localName, 'Virtual Machines') || contains(localName, 'BS Family')].{nama:localName, dipakai:currentValue, batas:limit}" \
    -o table 2>/dev/null || true)"
if [ -z "$(printf '%s' "$KUOTA" | tr -d '[:space:]')" ]; then
    echo "Kuota VM $LOKASI: daftar kosong. Provider yang belum ter-register juga menghasilkan"
    echo "  ini — dan kosong bukan berarti kuota habis, jadi VM-nya tetap dicoba."
else
    echo "Kuota VM $LOKASI:"
    printf '%s\n' "$KUOTA" | sed 's/^/  /'
fi

# Ukuran yang terlihat tersedia di region ini. Pemeriksaan ini TIDAK dijalankan kecuali
# diminta: daftar SKU regional menarik ribuan baris (menit), dan punya riwayat membohongi
# — provider yang baru ter-register membuatnya kosong untuk semua ukuran. Yang berhak
# memutuskan tersedia atau tidak adalah `az vm create` itu sendiri, dan galatnya sudah
# dibaca dari sana.
# Galat ARM asli hanya muncul di log `--debug` — az 2.91 di Windows melempar traceback
# ('The content for this response was already consumed') alih-alih pesannya, jadi tanpa
# --debug sebuah kegagalan terlihat seperti perintah yang berhasil.
if [ "$CEK_UKURAN" -eq 1 ]; then
    SEDIA="$(az vm list-skus --location "$LOKASI" --size "$UKURAN" \
        --query "[?name=='$UKURAN'] | length(@)" -o tsv 2>/dev/null || echo 0)"
    if [ "$SEDIA" = "0" ]; then
        echo "Daftar SKU: $UKURAN tidak terlihat di $LOKASI. Belum tentu — provider baru"
        echo "  ter-register juga menghasilkan daftar kosong. Penciptaannya tetap dicoba."
    else
        echo "Daftar SKU: $UKURAN terlihat tersedia untuk langganan ini di $LOKASI."
    fi
else
    echo "Ukuran tidak diverifikasi lewat daftar SKU (lambat dan punya riwayat menyesatkan);"
    echo "  pakai --cek-ukuran kalau curiga $UKURAN memang tidak ada di region ini."
fi
echo

if [ "$CEK" -eq 1 ]; then
    echo "Rencana yang akan dijalankan:"
    echo "  az group create -n $RG -l $LOKASI                        (kalau belum ada)"
    echo "  az network public-ip create -g $RG -n $IP --sku Standard (kalau belum ada)"
    echo "  az vm create -g $RG -n $NAMA --image Ubuntu2404 --size $UKURAN \\"
    echo "      --admin-user $USER_VM --ssh-key-values <isi $KUNCI> \\"
    echo "      --public-ip-address $IP --nsg-rule SSH --security-type Standard \\"
    echo "      --storage-sku StandardSSD_LRS"
    echo
    echo "Tidak ada resource yang dibuat. Jalankan tanpa --cek untuk melanjutkan."
    exit 0
fi

# ── 4. Resource group + IP publik ────────────────────────────────────────────────────
if [ "$(az group exists -n "$RG" -o tsv)" = "true" ]; then
    # Lokasi resource group ikut menentukan lokasi resource yang dibuat di dalamnya dan
    # tidak bisa diubah setelah group ada. Group warisan dari region yang ditolak kebijakan
    # harus diganti, bukan dipakai — itu sebabnya pemeriksaan ini ada di sini, bukan di
    # pesan kegagalan pembuatan IP.
    LOKASI_RG="$(az group show -n "$RG" --query location -o tsv 2>/dev/null || echo tidak-diketahui)"
    if [ "$LOKASI_RG" != "$LOKASI" ]; then
        echo "GAGAL: resource group $RG sudah ada di $LOKASI_RG, yang diminta $LOKASI." >&2
        echo "       Lokasi group tidak bisa diubah. Cek dulu isinya:" >&2
        echo "         az resource list -g $RG" >&2
        echo "       Kalau kosong, hapus group itu lalu jalankan ulang. Kalau tidak," >&2
        echo "       jalankan skrip ini dengan RG=group-baru (variabel lingkungan, bukan argumen)." >&2
        exit 1
    fi
    echo "Resource group $RG sudah ada di $LOKASI — dipakai apa adanya."
else
    echo "Membuat resource group $RG di $LOKASI ..."
    az group create -n "$RG" -l "$LOKASI" --query '{nama:name, lokasi:location}' -o table
fi

# IP dianggap sudah ada hanya kalau alamatnya benar-benar terbaca. Pembuatan yang mati di
# tengah (group yang baru dibuat kadang belum siap menerima resource: create meloloskan
# diri lalu GET-nya mengembalikan ResourceNotFound) meninggalkan IP tanpa alamat yang bisa
# dibaca `show`, dan langkah berikutnya gagal dengan pesan yang menuding VM.
ADA_IP=0
if az network public-ip list -g "$RG" --query "[?name=='$IP'] | length(@)" -o tsv | grep -q '^1$'; then
    ALAMAT_IP="$(az network public-ip show -g "$RG" -n "$IP" --query ipAddress -o tsv 2>/dev/null || true)"
    if [ -n "$ALAMAT_IP" ]; then
        ADA_IP=1
        echo "IP publik $IP sudah ada — alamatnya: $ALAMAT_IP"
    else
        echo "IP publik $IP ada tapi alamatnya belum terbaca — dihapus lalu dibuat ulang."
        az network public-ip delete -g "$RG" -n "$IP" -o none
    fi
fi
if [ "$ADA_IP" -eq 0 ]; then
    echo "Membuat IP publik Standard $IP ..."
    # Standard (bukan Basic) supaya alamat TIDAK dilepas saat VM di-deallocate.
    if [ -n "${DNS:-}" ]; then
        az network public-ip create -g "$RG" -n "$IP" --sku Standard --allocation-method Static \
            --dns-name "$DNS" --query '{alamat:ipAddress, dns:dnsSettings.fqdn}' -o table
    else
        az network public-ip create -g "$RG" -n "$IP" --sku Standard --allocation-method Static \
            --query '{alamat:ipAddress}' -o table
    fi
fi

# ── 5. VM ────────────────────────────────────────────────────────────────────────────
if az vm list -g "$RG" --query "[?name=='$NAMA'] | length(@)" -o tsv | grep -q '^1$'; then
    echo "VM $NAMA sudah ada — tidak dibuat ulang (itu akan membuang disknya)."
else
    echo "Membuat VM $NAMA ($UKURAN, Ubuntu 24.04, keamanan $TIPE_KEAMANAN) ..."
    # Keamanan default di sini adalah keputusan user, dan keputusan itu diuji terhadap
    # kenyataan: sebagian kombinasi region + ukuran hanya mau menerima TrustedLaunch, dan
    # sisanya hanya Standard. Kalau create gagal dengan SkuNotAvailable, yang dicoba dulu
    # adalah variabel TIPE_KEAMANAN di kepala skrip ini — pesan galatnya tidak menyebut
    # security type sama sekali, jadi tanpa percobaan itu penyebabnya tidak terbaca.
    # --zone sengaja tidak dipakai: availability zone menambah cara gagal dan tidak
    # memberi apa pun untuk aplikasi sekecil ini.
    az vm create \
        -g "$RG" -n "$NAMA" \
        --image Ubuntu2404 --size "$UKURAN" \
        --admin-user "$USER_VM" --ssh-key-values "$(cat "$KUNCI")" \
        --public-ip-address "$IP" \
        --nsg-rule SSH \
        --security-type "$TIPE_KEAMANAN" \
        --storage-sku StandardSSD_LRS \
        --query '{nama:name, ip:publicIp}' -o table
fi

# ── 6. Alamat + langkah berikutnya ───────────────────────────────────────────────────
# Keberhasilan dibaca dari resource-nya, bukan dari kode keluar: az di Windows bisa
# meninggalkan traceback di stdout sambil tetap tampak "jalan", dan create yang gagal
# menyisakan resource group + IP yang setengah jadi.
if ! az vm list -g "$RG" --query "[?name=='$NAMA'] | length(@)" -o tsv | grep -q '^1$'; then
    echo "GAGAL: VM $NAMA tidak ditemukan di $RG sesudah langkah create." >&2
    echo "       Yang paling sering: ukuran $UKURAN tidak bisa dipesan di $LOKASI — galatnya" >&2
    echo "       berbunyi SkuNotAvailable / Capacity Restrictions dan tidak menyebut harga," >&2
    echo "       region, atau security type. Coba --ukuran lain (B2s, D2s_v5) atau" >&2
    echo "       TIPE_KEAMANAN=TrustedLaunch, lalu ulangi — IP-nya sudah ada dan dipakai lagi." >&2
    echo "       Kalau tidak jadi, bersihkan: az group delete -n $RG --yes" >&2
    exit 1
fi

ALAMAT="$(az network public-ip show -g "$RG" -n "$IP" --query ipAddress -o tsv)"
if [ -z "$ALAMAT" ]; then
    echo "GAGAL: VM ada, tapi IP $IP tidak mengembalikan alamat. Jangan lanjut ke SSH." >&2
    exit 1
fi
echo
echo "VM siap. Alamat: $ALAMAT"
echo
echo "  1. Masuk:"
echo "       ssh -i ~/.ssh/pradana_azure $USER_VM@$ALAMAT"
echo
echo "  2. Kloning DULU, baru provisi (urutan ini penting — skrip provisi menyetel"
echo "     kepemilikan foldernya ke www-data, dan ia menerima folder yang sudah berisi"
echo "     checkout):"
echo "       sudo git clone $REPO $APP_DIR"
echo "       sudo bash $APP_DIR/bin/sediakan-server.sh --cek   # baca dulu rencananya"
echo "       sudo bash $APP_DIR/bin/sediakan-server.sh"
echo
echo "  3. Sisa langkahnya dicetak skrip provisi itu sendiri. Semua perintah sisi server"
echo '     berjalan sebagai www-data, termasuk `cp .env.example .env` dan `key:generate`.'
echo
echo "  4. Port 80 MASIH TERTUTUP. Buka hanya setelah docroot terbukti:"
echo "       curl -sI http://$ALAMAT/login        # harus 200 + X-Robots-Tag"
echo "       curl -s  http://$ALAMAT/.env | head  # harus kosong/404"
echo "     (perintahnya: az network nsg rule create ... — lihat catatan di akhir skrip)"
echo
echo "  5. Tagihan: VM ini menarik dari kredit langganan, bukan dari kartu. Menonaktifkan"
echo "     (deallocate) menghentikan biaya kompute; IP Standard-nya tetap."
