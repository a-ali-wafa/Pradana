<?php

namespace App\Services;

use App\Models\Lampiran;
use Illuminate\Support\Facades\Storage;
use Smalot\PdfParser\Parser;
use Throwable;
use ZipArchive;

/**
 * Membaca ISI berkas lampiran supaya staf tidak perlu mengetik ulang ringkasan
 * (permintaan user 5 Okt 2026: "baca isinya otomatis jika user upload, nanti
 * ditampilkan di dalam form untuk divalidasi").
 *
 * Prinsip yang dipakai:
 * 1. HASIL SELALU DITAMPILKAN SEBAGAI DRAF YANG BELUM VERIFIKASI. Teks mesin
 *    tidak pernah dianggap isi surat yang sah; kolom `isi_terverifikasi_pada`
 *    di `surat_masuk` mencatat kapan manusia menyimpannya.
 * 2. TIDAK PERNAH menggagalkan upload. Semua kegagalan dibaca sebagai
 *    "catatan", bukan exception — lampiran yang tersimpan adalah prioritas,
 *    hasil baca hanya bonus (L-02: tidak ada queue, jadi ini jalan inline di
 *    request unggah).
 * 3. Tanpa dependency berat dan tanpa binary server. PDF pakai `smalot/pdfparser`
 *   (murni PHP, jalan di shared hosting K1=a); DOCX/XLSX dibedah sebagai ZIP +
 *    XML pakai `ZipArchive` extension bawaan.
 *
 * BATASAN yang jujur (dan disampaikan ke user lewat `catatan`, bukan ditutupi):
 * - JPG/PNG/hasil scan: TIDAK dibaca. OCR butuh `tesseract` yang tidak ada di
 *   shared hosting kantor; kantor tetap bisa menyalin isinya manual.
 * - DOC/XLS lama (bukan zip): tidak diurai; minta disimpan ulang sebagai
 *   docx/xlsx dari Office.
 * - PDF hasil scan (gambar) tidak punya lapisan teks → hasilnya kosong.
 */
class PembacaIsiLampiran
{
    /** Teks sepanjang ini cukup untuk ringkasan surat dan hemat memori PHP. */
    public const MAKS_KARAKTER = 20000;

    /** Berkas di atas ini tidak dibaca: risiko memory limit di shared hosting. */
    public const MAKS_UKURAN_BACA = 15_000_000;

    /**
     * @param  Lampiran  $lampiran  baris yang sudah tersimpan di disk lokal
     * @return array{teks: string|null, catatan: string}
     */
    public function baca(Lampiran $lampiran): array
    {
        $nama = (string) $lampiran->nama_file;
        $ekstensi = strtolower(pathinfo($nama, PATHINFO_EXTENSION));

        if (! $lampiran->adaDiLokal()) {
            return $this->gagal('Berkas aslinya tidak ada di computer arsip, jadi tidak bisa dibaca.');
        }

        if ((int) $lampiran->ukuran > self::MAKS_UKURAN_BACA) {
            return $this->gagal('Berkasnya terlalu besar untuk dibaca otomatis (batas 15 MB).');
        }

        $path = Storage::disk($lampiran->disk ?: 'arsip')->path($lampiran->path);

        return match ($ekstensi) {
            'pdf' => $this->rapikan($this->dariPdf($path), $nama),
            'docx' => $this->rapikan($this->dariDocx($path), $nama),
            'xlsx' => $this->rapikan($this->dariXlsx($path), $nama),
            'jpg', 'jpeg', 'png' => $this->gagal(
                'Foto/hasil scan ('.$nama.') tidak bisa dibaca otomatis — sistem ini tidak punya OCR. '
                .'Ringkasan suratnya masih perlu diketik sendiri.'
            ),
            'doc', 'xls' => $this->gagal(
                'Format lama '.$ekstensi.' tidak bisa dibaca otomatis. Simpan ulang sebagai '
                .($ekstensi === 'doc' ? 'docx' : 'xlsx').', lalu unggah lagi kalau mau isinya ikut terbaca.'
            ),
            default => $this->gagal('Jenis berkas '.$nama.' tidak dikenali untuk pembacaan otomatis.'),
        };
    }

    /**
     * @return array{teks: string|null, catatan: string}
     */
    private function gagal(string $catatan): array
    {
        return ['teks' => null, 'catatan' => $catatan];
    }

    /**
     * @return array{teks: string|null, catatan: string}
     */
    private function rapikan(?string $teks, string $nama): array
    {
        if ($teks === null) {
            return $this->gagal('Isi '.$nama.' tidak bisa dibaca (berkasnya mungkin rusak atau diproteksi).');
        }

        // \r\n & \r -> \n, spasi trailing dibuang, >2 baris kosong diringkas.
        $bersih = preg_replace('/\R{3,}/u', "\n\n", str_replace(["\r\n", "\r"], "\n", $teks));
        $bersih = trim(preg_replace('/[ \t]+$/m', '', $bersih));

        if ($bersih === '') {
            // PDF hasil scan: parser sukses tapi tidak ada lapisan teks di dalamnya.
            return $this->gagal(
                'Berkas '.$nama.' tidak mengandung teks yang bisa diambil — kemungkinan besar hasil scan/gambar. '
                .'Isinya masih perlu diketik sendiri.'
            );
        }

        $dipotong = mb_strlen($bersih) > self::MAKS_KARAKTER;

        if ($dipotong) {
            $bersih = mb_substr($bersih, 0, self::MAKS_KARAKTER);
        }

        return [
            'teks' => $bersih,
            'catatan' => 'Isi '.$nama.' terbaca'
                .($dipotong ? ' (dipotong sampai '.self::MAKS_KARAKTER.' karakter)' : '')
                .'. Periksa sebelum disimpan.',
        ];
    }

    private function dariPdf(string $path): ?string
    {
        try {
            // parseFile() membaca dari disk, bukan menelan seluruh berkas ke memori
            // sekaligus sebagai string — penting untuk lampiran 10 MB di server kecil.
            return (new Parser)->parseFile($path)->getText();
        } catch (Throwable) {
            return null;
        }
    }

    private function dariDocx(string $path): ?string
    {
        $xml = $this->dariZip($path, 'word/document.xml');

        if ($xml === null) {
            return null;
        }

        // Paragraf -> baris baru, tab & baris baru di dalam sel -> whitespace,
        // sisanya (semua tag) dibuang. Runs <w:t> dibiarkan bersambung karena
        // Word memecah satu kalimat jadi banyak run.
        $xml = str_replace(['</w:p>', '<w:br/>', '<w:tab/>', '</w:tr>'], ["\n", "\n", "\t", "\n"], $xml);
        $teks = preg_replace('/<[^>]+>/u', '', $xml);

        return html_entity_decode((string) $teks, ENT_QUOTES | ENT_XML1, 'UTF-8');
    }

    private function dariXlsx(string $path): ?string
    {
        $sel = $this->dariZip($path, 'xl/sharedStrings.xml');

        if ($sel === null) {
            return null;
        }

        // Yang dibaca hanya teks bersama (isi sel berupa string). Angka, formula,
        // dan tanggal tersimpan di sheet XML terpisah dan sengaja tidak diikuti —
        // untuk keperluan ringkasan surat, teksnya yang penting.
        preg_match_all('/<t[^>]*>(.*?)<\/t>/us', $sel, $cocok);

        $baris = array_map(
            fn ($t) => html_entity_decode($t, ENT_QUOTES | ENT_XML1, 'UTF-8'),
            $cocok[1] ?? []
        );

        return implode("\n", $baris);
    }

    private function dariZip(string $path, string $diDalam): ?string
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            return null;
        }

        $isi = $zip->getFromName($diDalam);
        $zip->close();

        return $isi === false ? null : (string) $isi;
    }
}
