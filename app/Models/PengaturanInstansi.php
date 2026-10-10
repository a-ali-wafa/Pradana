<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * Pengaturan instansi — satu baris tunggal (aplikasi satu kantor, L-26/J1),
 * diedit lewat `Route::singleton` oleh admin saja (B4/L-07).
 *
 * Kolomnya dipakai untuk mencetak KOP SURAT di semua keluaran PDF (surat keluar,
 * Berita Acara Pemusnahan, Buku Agenda), jadi helper di bawah ini sengaja taruh
 * di model: tiga template PDF tidak boleh menyimpulkan format kop masing-masing.
 *
 * kopBarisAtas() menghasilkan daftar baris bertingkat ala tata naskah dinas desa
 * (Kabupaten > Kecamatan > Desa — Permendagri 1/2023). Baris yang kolomnya kosong
 * TIDAK diemit, sehingga kantor dengan kop satu baris tetap tercetak benar dan
 * data lama (sebelum migration 5 Okt 2026) tidak terlihat rusak.
 */
class PengaturanInstansi extends Model
{
    protected $table = 'pengaturan_instansi';

    /** Kunci cache untuk brand layout; dibuang setiap baris instansi berubah. */
    public const KUNCI_MEREK = 'prd:merek-instansi';

    protected $fillable = [
        'nama_instansi',
        'nama_kabupaten',
        'nama_kecamatan',
        'jenis_instansi',
        'alamat_instansi',
        'kode_pos',
        'no_telp',
        'email',
        'logo_path',
    ];

    /**
     * Baris kop di atas nama instansi, sudah diberi label dan huruf kapital.
     *
     * @return array<int, string>
     */
    public function kopBarisAtas(): array
    {
        $baris = [];

        if (filled($this->nama_kabupaten)) {
            $baris[] = 'PEMERINTAH KABUPATEN '.mb_strtoupper(trim($this->nama_kabupaten));
        }

        if (filled($this->nama_kecamatan)) {
            $baris[] = 'KECAMATAN '.mb_strtoupper(trim($this->nama_kecamatan));
        }

        return $baris;
    }

    /**
     * Baris alamat + kontak di bawah nama instansi (satu string per baris).
     *
     * @return array<int, string>
     */
    public function kopAlamat(): array
    {
        $baris = [];

        $alamat = trim((string) $this->alamat_instansi);

        if ($alamat !== '') {
            $baris[] = $alamat.(filled($this->kode_pos) ? ', Kode Pos '.trim($this->kode_pos) : '');
        }

        $kontak = [];

        if (filled($this->no_telp)) {
            $kontak[] = 'Telp. '.trim($this->no_telp);
        }

        if (filled($this->email)) {
            $kontak[] = 'E-mail: '.trim($this->email);
        }

        if ($kontak !== []) {
            // Karakter "·" sungguhan, bukan entity: nilai ini dicetak lewat `{{ }}`
            // di template PDF, dan `&middot;` akan terbaca sebagai teks mentah.
            $baris[] = implode(' · ', $kontak);
        }

        return $baris;
    }

    /**
     * URL logo untuk di-embed di halaman (login, Pengaturan Instansi).
     *
     * SENGAJA lewat route `instansi.logo`, BUKAN `Storage::url()`: yang terakhir
     * menunjuk `public/storage/...` dan butuh `php artisan storage:link`. Di shared
     * hosting kantor (K1=a) symlink itu sering tidak dibuat — dan memang tidak bisa
     * dibuat lewat FTP biasa — akibatnya logo hilang diam-diam tanpa error.
     * Query `?v=` dibuat dari nama berkas: nama berkas logo selalu baru tiap upload
     * (`store('logo')` mengirim nama acak), jadi URL-nya berubah tepat saat logo
     * diganti dan browser boleh menyimpannya lama.
     */
    public function logoUrl(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return route('instansi.logo', ['v' => substr(md5($this->logo_path), 0, 8)]);
    }

    /**
     * Nama + logo untuk brand di layout (sidebar & dashboard).
     *
     * `layouts.app` dirender di SETIAP halaman, jadi kalau method ini memanggil
     * database tanpa cache, setiap klik staf menambah satu query cuma untuk gambar
     * yang jarang berubah. `Cache::remember` (driver file, S10) membuat biayanya
     * satu pembacaan disk, bukan satu SELECT; kunci dibuang di
     * `PengaturanInstansiObserver::updated()` sehingga logo baru langsung muncul
     * tanpa menunggu kedaluwarsa.
     *
     * Tidak ada `env()` di sini dan tidak ada di view: nilai ini lewat model, jadi
     * `config:cache` tidak mengubah perilakunya (jebakan yang sudah dicatat untuk
     * SECURITY_CSP, DEV_PIN, AGENDA_BATAS_BARIS, dan BACKUP_*).
     *
     * @return array{nama: ?string, logo: ?string}
     */
    public static function untukTampilan(): array
    {
        return Cache::remember(self::KUNCI_MEREK, 900, function (): array {
            $instansi = static::first();

            return [
                'nama' => $instansi?->nama_instansi,
                'logo' => $instansi?->logoUrl(),
            ];
        });
    }

    /**
     * Path fisik logo untuk dompdf (template cetak/berita acara/agenda).
     *
     * DomPDF lebih andal membaca berkas lokal daripada mengambil URL, dan dengan
     * `Storage::disk('public')->path()` kita tidak bergantung pada symlink
     * `public/storage` — lihat logoUrl() di atas. Berkas yang ternyata hilang dari
     * disk menghasilkan null, bukan exception, supaya PDF tetap tercetak tanpa kop.
     */
    public function logoPathUntukPdf(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        $disk = Storage::disk('public');

        return $disk->exists($this->logo_path) ? $disk->path($this->logo_path) : null;
    }

    /**
     * Nama tempat pada baris tanggal surat ("Urek-Urek, 5 Oktober 2026").
     *
     * Kolom `nama_instansi` biasanya terisi "Pemerintah Desa Urek-Urek", sedangkan
     * tempat surat tidak perlu kata "Pemerintah". Prefix kepanitiaan/lembaga
     * dibuang; sisanya ditulis apa adanya. Kantor yang hasil pembuangannya tidak
     * cocok cukup menulis nama instansi tanpa prefix — tidak ada kolom baru untuk
     * satu kata, dan nilainya tetap dipakai utuh di kop.
     */
    public function tempatSurat(): string
    {
        $nama = trim((string) $this->nama_instansi);

        foreach (['pemerintah', 'sekretariat', 'kantor'] as $prefix) {
            if (mb_stripos($nama, $prefix.' ') === 0) {
                $nama = trim(mb_substr($nama, mb_strlen($prefix) + 1));
            }
        }

        if ($nama === '') {
            $nama = trim((string) $this->nama_kecamatan);
        }

        return $nama;
    }

    /**
     * Label peran untuk kop: "Desa"/"Kelurahan" dipakai di frasa seperti
     * "Kepala Desa" pada blok tanda tangan. Falls back ke teks netral.
     */
    public function sebutanPemimpin(): string
    {
        $jenis = trim((string) $this->jenis_instansi);

        if ($jenis === '') {
            return 'Kepala Instansi';
        }

        // "Pemerintah Desa" → "Kepala Desa"; "Kelurahan ABC" → "Kepala Kelurahan".
        foreach (['Desa', 'Kelurahan', 'Nagari'] as $sebutan) {
            if (mb_stripos($jenis, $sebutan) !== false) {
                return 'Kepala '.$sebutan;
            }
        }

        return 'Kepala '.ucfirst($jenis);
    }
}
