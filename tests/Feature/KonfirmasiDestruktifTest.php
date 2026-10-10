<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\Lampiran;
use App\Models\PengajuanHapusLampiran;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aksi destruktif adalah form sungguhan, JavaScript hanya menambah konfirmasi
 * (P0#1 paruh kedua di docs/daftar-peningkatan.md, 10 Okt 2026).
 *
 * Bentuk lama punya dua cacat yang tidak kelihatan sampai seseorang mencoba
 * memakai aplikasi ini saat internet kantor bermasalah:
 *  1. `pradanaConfirmHapus(url, pesan)` disalin TUJUH kali, dan versi lamanya
 *     MERAKIT `<form>` dari JavaScript (`form.innerHTML = '@csrf @method("DELETE")'`).
 *     Tombolnya `type="button"`. Tanpa JS — atau tanpa SweetAlert2, yang tidak
 *     pernah diperiksa keberadaannya di satu pun salinan — tombol itu diam total:
 *     tidak ada pesan, tidak ada aksi.
 *  2. Blok yang "dibuka lewat tombol" (`form-tolak`, form reset PIN) memakai
 *     `style="display:none"` di HTML. Matinya JavaScript berarti fitur itu tidak
 *     bisa dicapai sama sekali, bukan cuma kehilangan hiasan.
 *
 * Karena itu guardrail-nya teks (dibaca tanpa komentar, seperti FlashKonsistenTest)
 * DAN perilaku (form-nya benar-benar ada di respons). Yang dijaga:
 *  - setiap form `@method('DELETE')` menulis pesannya sendiri di `data-konfirmasi`,
 *  - tidak ada lagi fungsi konfirmasi per halaman dan tidak ada `Swal.` telanjang,
 *  - helper bersama punya fallback `window.confirm` dan tidak menyuntik HTML,
 *  - blok buka/tutup disembunyikan OLEH script, bukan oleh atribut style.
 */
class KonfirmasiDestruktifTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $pegawai;

    private KlasifikasiPrimer $primer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::forceCreate([
            'nama_lengkap' => 'Kepala Desa Uji',
            'email' => 'admin-konfirm@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'admin',
        ]);

        $this->pegawai = User::forceCreate([
            'nama_lengkap' => 'Staf Uji',
            'email' => 'staf-konfirm@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        $this->primer = KlasifikasiPrimer::forceCreate(['kode' => '01', 'nama' => 'Umum']);
    }

    /**
     * Blade + HTML komentar dibuang lebih dulu — pelajaran Fase 6: guardrail teks
     * yang membaca komentar akan menegur dokumentasi dan melewatkan kode.
     *
     * @return array<string, string> path => isi tanpa komentar
     */
    private function views(): array
    {
        $hasil = [];

        $rekursif = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(resource_path('views'), \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($rekursif as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $hasil[$file->getPathname()] = $this->tanpaKomentar((string) file_get_contents($file->getPathname()));
            }
        }

        $this->assertGreaterThan(20, count($hasil), 'Scan view tidak menemukan apa-apa — tes ini tidak mengukur apa pun.');

        return $hasil;
    }

    private function tanpaKomentar(string $isi): string
    {
        return (string) preg_replace(
            '/\{\{--.*?--\}\}/s',
            '',
            (string) preg_replace('/<!--.*?-->/s', '', $isi)
        );
    }

    public function test_setiap_form_destruktif_menulis_pesannya_di_atribut(): void
    {
        $tanpaPesan = [];
        $diperiksa = 0;

        foreach ($this->views() as $path => $isi) {
            preg_match_all('/<form\b[^>]*>(.*?)<\/form>/s', $isi, $blok);

            foreach ($blok[0] as $form) {
                if (! str_contains($form, "@method('DELETE')")) {
                    continue;
                }

                $diperiksa++;

                if (! str_contains($form, 'data-konfirmasi')) {
                    $tanpaPesan[] = str_replace(resource_path(), '', $path);
                }
            }
        }

        $this->assertGreaterThanOrEqual(7, $diperiksa, 'Scan form tidak menemukan apa-apa — tes ini tidak mengukur apa pun.');
        $this->assertSame([], $tanpaPesan, 'Form DELETE tanpa data-konfirmasi: pesannya hidup di JavaScript, bukan di HTML.');
    }

    public function test_tidak_ada_lagi_fungsi_konfirmasi_yang_disalin_per_halaman(): void
    {
        $pola = '/(function\s+pradanaConfirm\w*|function\s+pradanaToggleTolak|function\s+setujuiPemusnahan|Swal\.\s*fire)/';

        // Komentar Blade/HTML dibuang sebelum mencocokkan, tapi komentar di dalam
        // `<script>` TIDAK — dan itu sengaja. Membedakannya butuh parser JavaScript,
        // dan konsekuensinya cuma satu: view tidak boleh menulis ulang nama `Swal.fire`
        // bahkan sebagai penjelasan historis. (Ini terjadi: komentar saya sendiri di
        // pemusnahan-arsip/create.blade.php membuat tes ini gagal, dan bentuk
        // perbaikannya memang memindahkan penjelasannya ke AGENTS.md — tempat yang
        // boleh membicarakan masa lalu.)
        $tersalah = [];

        foreach ($this->views() as $path => $isi) {
            if (preg_match($pola, $isi, $cocok)) {
                $tersalah[] = basename(dirname($path)).'/'.basename($path).' -> '.$cocok[0];
            }
        }

        $this->assertSame(
            [],
            $tersalah,
            'Konfirmasi harus lewat public/js/pradana-arsip.js. Tujuh salinan pradanaConfirmHapus() adalah cara bug Fase 6 bisa terjadi dua kali.'
        );
    }

    public function test_helper_bersama_punya_fallback_dan_tidak_menyuntik_html(): void
    {
        $js = (string) file_get_contents(public_path('js/pradana-arsip.js'));

        // Inti perbaikannya: SweetAlert2 tidak lagi diasumsikan ada.
        $this->assertStringContainsString('typeof window.Swal', $js);
        $this->assertStringContainsString('window.confirm', $js, 'Tanpa SweetAlert2 harus jatuh ke confirm() bawaan browser, bukan ReferenceError.');

        // `form.innerHTML = '@csrf @method("DELETE")'` adalah cara bentuk lama
        // merakit formnya; pola yang sama di sini akan berarti pesan Blade
        // disisipkan sebagai markup.
        $this->assertStringNotContainsString('innerHTML', $js);
        $this->assertStringNotContainsString('html:', $js, 'Teks dari atribut data-* harus lewat jalur teks, bukan HTML.');

        // Helper harus dimuat di layout, bukan per halaman.
        $this->assertStringContainsString(
            'js/pradana-arsip.js',
            $this->tanpaKomentar((string) file_get_contents(resource_path('views/layouts/app.blade.php')))
        );
    }

    public function test_blok_buka_tutup_tidak_disembunyikan_di_html(): void
    {
        $pakai = 0;

        foreach ($this->views() as $isi) {
            $pakai += preg_match_all('/data-pradana-tertutup/', $isi);

            $this->assertDoesNotMatchRegularExpression(
                '/<div[^>]*id="form-[A-Za-z0-9_\-]*"[^>]*style="[^"]*display\s*:\s*none/i',
                $isi,
                'Blok yang dibuka tombol tidak boleh tertutup lewat atribut style: tanpa JS fiturnya tidak bisa dicapai.'
            );
        }

        // users/index, pengajuan-hapus-lampiran, pemusnahan-arsip/show.
        $this->assertGreaterThanOrEqual(3, $pakai, 'Mekanisme data-pradana-tertutup tidak dipakai di mana-mana.');
    }

    public function test_tombol_hapus_surat_adalah_form_post_yang_bisa_dikirim_tanpa_js(): void
    {
        $surat = SuratMasuk::forceCreate([
            'user_id' => $this->pegawai->id,
            'pengirim' => 'Kecamatan',
            'klasifikasi_primer_id' => $this->primer->id,
            'nomor_surat' => 'KONF-001',
            'perihal' => 'Perihal uji konfirmasi',
            'tanggal_surat' => '2026-05-01',
            'tanggal_diterima' => '2026-05-02',
            'sifat' => 'biasa',
            'status_arsip' => 'aktif',
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('surat-masuk.index'))
            ->assertOk()
            ->getContent();

        // Form + token + _method ada di HTML: itu seluruh yang dibutuhkan browser
        // tanpa JavaScript untuk tetap bisa menghapus.
        $this->assertStringContainsString('data-konfirmasi="Pindahkan surat masuk', $html);
        $this->assertStringContainsString('name="_method" value="DELETE"', $html);
        $this->assertStringContainsString('name="_token"', $html);
        $this->assertStringNotContainsString('onclick="pradanaConfirmHapus', $html);

        // Dan jalur tanpa JS-nya memang jalan: POST biasa (tanpa pernah menyentuh
        // SweetAlert2) memindahkan surat ke tempat sampah.
        $this->actingAs($this->admin)
            ->delete(route('surat-masuk.destroy', $surat))
            ->assertRedirect(route('surat-masuk.index'));

        $this->assertSoftDeleted('surat_masuk', ['id' => $surat->id]);
    }

    public function test_form_tolak_pengajuan_tetap_ada_di_respons(): void
    {
        $lampiran = Lampiran::forceCreate([
            'lampiranable_type' => SuratMasuk::class,
            'lampiranable_id' => $this->pegawaiSurat()->id,
            'nama_file' => 'berkas-tolak.pdf',
            'disk' => 'arsip',
            'path' => 'uji/berkas-tolak.pdf',
        ]);

        PengajuanHapusLampiran::forceCreate([
            'lampiran_id' => $lampiran->id,
            'diajukan_oleh' => $this->pegawai->id,
            'status' => 'menunggu',
            'nama_file_snapshot' => 'berkas-tolak.pdf',
            'alasan' => 'Sudah lewat retensi',
        ]);

        $html = $this->actingAs($this->admin)
            ->get(route('pengajuan-hapus-lampiran.index'))
            ->assertOk()
            ->getContent();

        // Sebelum 10 Okt: `style="display:none;"` + `pradanaToggleTolak()` di JS.
        // Sekarang textarea-nya ada di HTML dan hanya disembunyikan oleh helper.
        $this->assertStringContainsString('data-pradana-tertutup', $html);
        $this->assertStringContainsString('name="catatan_admin"', $html);
        $this->assertStringNotContainsString('pradanaToggleTolak', $html);

        // Setujui juga form + atribut, bukan `onsubmit="return pradanaConfirmSetujui(...)"`.
        $this->assertStringContainsString('data-konfirmasi="File &raquo;berkas-tolak.pdf&raquo;', $html);
        $this->assertStringNotContainsString('onsubmit="return pradanaConfirmSetujui', $html);
    }

    private function pegawaiSurat(): SuratMasuk
    {
        return SuratMasuk::forceCreate([
            'user_id' => $this->pegawai->id,
            'pengirim' => 'Desa',
            'klasifikasi_primer_id' => $this->primer->id,
            'nomor_surat' => 'KONF-002',
            'perihal' => 'Surat untuk uji tolak',
            'tanggal_surat' => '2026-05-03',
            'tanggal_diterima' => '2026-05-04',
            'sifat' => 'biasa',
            'status_arsip' => 'aktif',
        ]);
    }
}
