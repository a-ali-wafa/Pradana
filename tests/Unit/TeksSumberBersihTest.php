<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Tidak ada karakter CJK yang nyasar di naskah project ini.
 *
 * Kenapa tes sepele ini ada: PRADANA ditulis dalam bahasa Indonesia, nama domain
 * sengaja tidak diterjemahkan (`surat_masuk`), dan tidak ada satu pun teks
 * Jepang/Mandarin yang seharusnya muncul. Tapi dalam praktiknya karakter CJK
 * masuk BERULANG KALI ke komentar dan dokumentasi — empat kali hanya dalam satu
 * session (9–10 Okt 2026), selalu di tengah kalimat yang sedang ditulis cepat,
 * dan selalu lolos dari pembacaan sekilas karena bentuknya "huruf lain" saja:
 *   satu-dua karakter Han menyisip di tengah kata Indonesia — "setelah(CJK) dikerjakan",
 *   "yang(CJK)-nya", "masih(CJK) ruang", "dan(CJK) menolak". Placeholder-nya harus
 *   ASCII: contoh pertama saya ditulis dengan huruf Han di dalam kurung, dan tes
 *   ini menangkap baris dokumentasinya sendiri. Huruf aslinya sengaja TIDAK
 *   ditulis di sini — berkas ini ikut dipindai.
 * Pint dan Larastan tidak peduli (mereka melihat byte), jadi satu-satunya yang
 * bisa menangkapnya adalah tes yang memang mencari rentang kode itu.
 *
 * Kalau tes ini gagal, yang salah hampir pasti kata-katanya, bukan test-nya:
 * perbaiki naskahnya, jangan longgarkan polanya.
 */
class TeksSumberBersihTest extends TestCase
{
    /** Rentang yang dicari: hiragana, katakana, kanji, hangul, dan CJK punctuation. */
    private const POLA = '/[\x{3000}-\x{30FF}\x{3400}-\x{4DBF}\x{4E00}-\x{9FFF}\x{F900}-\x{FAFF}\x{AC00}-\x{D7AF}]/u';

    private const DIREKTORI = ['app', 'config', 'database', 'resources', 'routes', 'public/js', 'tests'];

    /**
     * root repo diambil dari __DIR__, BUKAN `base_path()`: kelas ini sengaja
     * berdiri di atas PHPUnit polos (tidak meng-boot aplikasi), jadi tesnya
     * tetap jalan meski Laravel tidak bisa dipasang — dan itu penting, karena
     * yang diperiksa di sini adalah teks, bukan perilaku aplikasi.
     */
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    public function test_komentar_dan_naskah_tidak_memuat_karakter_cjk(): void
    {
        $nyasar = [];

        foreach ($this->berkas() as $path) {
            $baris = explode("\n", (string) file_get_contents($path));

            foreach ($baris as $nomor => $isi) {
                if (preg_match(self::POLA, $isi)) {
                    // Separator dinormalisasi: iterator Windows memberi `A:\Pradana\app\..`
                    // sementara root-nya bisa berbeda bentuk, dan pesan gagal yang
                    // tidak bisa dibaca = pesan gagal yang diabaikan.
                    $relatif = str_replace('\\', '/', $path);
                    $relatif = str_replace(str_replace('\\', '/', $this->root()).'/', '', $relatif);

                    $nyasar[] = $relatif.':'.($nomor + 1).' → '.trim($isi);
                }
            }
        }

        $this->assertSame([], $nyasar, "Karakter CJK nyasar di naskah Indonesia:\n  ".implode("\n  ", $nyasar));
    }

    /**
     * Semua berkas sumber + dokumen yang seharusnya berbahasa Indonesia.
     *
     * Markdown ikut diperiksa karena dokumentasi project ini (AGENTS.md, docs/)
     * adalah tempat karakter nyasar paling sering mendarat. `vendor/` dan
     * `node_modules/` tidak pernah dimasukkan — di sana memang ada contoh teks
     * asing (Carbon, portable-ascii).
     *
     * @return \Generator<string>
     */
    private function berkas(): \Generator
    {
        foreach (self::DIREKTORI as $direktori) {
            $absolut = $this->root().'/'.$direktori;

            if (! is_dir($absolut)) {
                continue;
            }

            $jalan = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($absolut, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($jalan as $file) {
                if ($file->isFile()) {
                    yield $file->getPathname();
                }
            }
        }

        foreach (['AGENTS.md', 'AGENTS_HISTORY.md', 'README.md', 'docs/manual-pemakaian.md', 'docs/daftar-peningkatan.md', 'docs/plan-perapian-kode.md', 'docs/aset-vendor.md'] as $relatif) {
            if (is_file($this->root().'/'.$relatif)) {
                yield $this->root().'/'.$relatif;
            }
        }
    }
}
