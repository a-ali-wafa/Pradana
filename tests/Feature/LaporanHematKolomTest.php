<?php

namespace Tests\Feature;

use App\Models\KlasifikasiPrimer;
use App\Models\Lampiran;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Laporan hanya mengangkat kolom yang dibacanya (P0 di `docs/daftar-peningkatan.md`,
 * dikerjakan 10 Okt 2026).
 *
 * Angka yang mendorong perubahan ini (diukur 9 Okt di MariaDB tanding, 16.005 surat):
 *   `->get()`           1.674 ms, puncak memori 56 MB
 *   `->get([kolom])`      383 ms, puncak memori  2 MB
 * Selisihnya hampir seluruhnya `isi_hasil_baca` (LONGTEXT, batas writer-nya 20.000
 * karakter) dan `ringkasan` (TEXT) — dua kolom yang TIDAK dicetak di satu pun kolom
 * rekap CSV maupun Buku Agenda. Rekap setahun penuh adalah jalur yang paling mungkin
 * memakainya, dan target deploy kantor (K1=a) tidak memberi kita `memory_limit`.
 *
 * Tes ini Menjaga dua arah sekaligus:
 *  1. query surat tidak lagi menyebut kolom berat (guardrail terhadap regresi
 *     "biar gampang, get() saja"), dan
 *  2. hasil yang dibutuhkan tetap TERISI — karena `select()` + eager load berkolom
 *     terbatas + `withCount()` adalah kombinasi yang bisa gagal senyap:
 *     `lampiran_count` hilang berarti CSV menulis "0 berkas" tanpa error apa pun,
 *     dan `petugas` tanpa `user_id` di pihak induk berarti nama petugas jadi "-".
 */
class LaporanHematKolomTest extends TestCase
{
    use RefreshDatabase;

    private User $petugas;

    private KlasifikasiPrimer $primer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->petugas = User::forceCreate([
            'nama_lengkap' => 'Staf Arsip',
            'email' => 'staf-hemat@example.test',
            'pin' => bcrypt('12345678'),
            'role' => 'pegawai',
        ]);

        $this->primer = KlasifikasiPrimer::forceCreate([
            'kode' => '42',
            'nama' => 'Pengaduan Masyarakat',
        ]);
    }

    /**
     * Satu surat yang isinya besar-besar, seperti hasil baca lampiran sungguhan.
     */
    private function suratBerisiPanjang(string $nomor): SuratMasuk
    {
        $surat = SuratMasuk::forceCreate([
            'user_id' => $this->petugas->id,
            'pengirim' => 'Kecamatan Ujung',
            'klasifikasi_primer_id' => $this->primer->id,
            'nomor_surat' => $nomor,
            'perihal' => 'Berkas hasil baca yang panjang',
            'tanggal_surat' => '2026-05-13',
            'tanggal_diterima' => '2026-05-14',
            'sifat' => 'biasa',
            'status_arsip' => 'aktif',
        ]);

        // `isi_hasil_baca` sengaja di luar $fillable (ditulis forceFill di controller),
        // jadi di sini pun lewat jalur yang sama.
        $surat->forceFill([
            'isi_hasil_baca' => str_repeat('R', 20_000),
            'ringkasan' => str_repeat('K', 5_000),
        ])->save();

        return $surat;
    }

    /**
     * @return list<string> isi CSV yang memuat $nomor
     */
    private function barisRekap(string $nomor): array
    {
        $csv = $this->actingAs($this->petugas)
            ->get(route('laporan.rekap', ['dari' => '2026-05-01', 'sampai' => '2026-05-31']))
            ->streamedContent();

        return array_values(array_filter(
            explode("\n", $csv),
            fn (string $baris) => str_contains($baris, $nomor)
        ));
    }

    public function test_query_surat_tidak_mengangkat_isi_hasil_baca_dan_ringkasan(): void
    {
        $surat = $this->suratBerisiPanjang('HEMAT-001');

        $pernyataan = [];
        DB::listen(function ($query) use (&$pernyataan) {
            $pernyataan[] = $query->sql;
        });

        $this->assertStringContainsString(
            'HEMAT-001',
            implode("\n", $this->barisRekap('HEMAT-001')),
            'Rekap harus tetap memuat suratnya — penghematan kolom tidak boleh membuang baris.'
        );

        $terhadapSurat = array_values(array_filter(
            $pernyataan,
            fn (string $sql) => str_contains($sql, 'from "surat_masuk"') || str_contains($sql, 'from `surat_masuk`')
        ));

        $this->assertNotEmpty($terhadapSurat, 'Tidak ada satu pun query ke surat_masuk — tes ini tidak mengukur apa-apa.');

        foreach ($terhadapSurat as $sql) {
            // Awas, dan ini benar-benar terjadi saat guardrail-nya diuji terhadap
            // kode lama: `withCount()` membuat Eloquent menulis
            // `select "surat_masuk".*, (...) as "lampiran_count"`, BUKAN
            // `select *`. Jadi mengecek ketiadaan tulisan "isi_hasil_baca" atau
            // "select * from" lolos sempurna pada versi yang justru mau dibuang.
            // Yang membuktikan penghematan adalah bentuk POSITIF-nya: daftar kolom
            // eksplisit ada, dan wildcard tabel tidak ada.
            $this->assertStringNotContainsString(
                'surat_masuk".*',
                $sql,
                'Select wildcard = seluruh kolom diangkut, termasuk isi_hasil_baca LONGTEXT. Daftar kolomnya harus eksplisit.'
            );
            $this->assertStringContainsString(
                '"nomor_surat"',
                $sql,
                'Tidak ada daftar kolom eksplisit di SELECT — berarti query-nya balik ke hidrasi penuh.'
            );
            $this->assertStringNotContainsString('isi_hasil_baca', $sql);
            $this->assertStringNotContainsString('ringkasan', $sql);
        }

        // Bukti tidak ada yang curang: kolomnya memang ada di database, isinya
        // hanya tidak diminta oleh jalur laporan.
        $this->assertSame(
            20_000,
            strlen((string) $surat->fresh()->isi_hasil_baca),
            'Kalau isinya tidak pernah tersimpan, tes di atas lolos tanpa arti.'
        );
    }

    public function test_klasifikasi_petugas_dan_jumlah_lampiran_tetap_terisi(): void
    {
        $surat = $this->suratBerisiPanjang('HEMAT-002');

        foreach ([1, 2] as $i) {
            Lampiran::forceCreate([
                'lampiranable_type' => SuratMasuk::class,
                'lampiranable_id' => $surat->id,
                'nama_file' => 'berkas-'.$i.'.pdf',
                'disk' => 'arsip',
                'path' => 'uji/berkas-'.$i.'.pdf',
            ]);
        }

        $baris = $this->barisRekap('HEMAT-002');
        $this->assertCount(1, $baris, 'Seharusnya tepat satu baris rekap.');

        // Header CSV: No;Jenis;Tanggal Surat;Tanggal Diterima;Nomor Surat;
        // Pengirim/Penerima;Perihal;Sifat;Klasifikasi;Status Arsip;Lampiran;Petugas
        $kolom = str_getcsv($baris[0], ';');

        $this->assertSame('Surat Masuk', $kolom[1]);
        $this->assertSame('2026-05-13', $kolom[2]);
        $this->assertSame('2026-05-14', $kolom[3], 'tanggal_diterima hanya ada di surat masuk.');
        $this->assertSame('Kecamatan Ujung', $kolom[5]);
        $this->assertSame('Berkas hasil baca yang panjang', $kolom[6]);
        $this->assertSame(
            '42 Pengaduan Masyarakat',
            $kolom[8],
            'Eager load primer butuh klasifikasi_primer_id di daftar kolom induk.'
        );
        $this->assertSame('aktif', $kolom[9]);
        $this->assertSame(
            '2',
            $kolom[10],
            'withCount() + select() harus tetap memberi lampiran_count; kalau hilang, CSV menulis "0 berkas" tanpa error.'
        );
        $this->assertSame(
            'Staf Arsip',
            $kolom[11],
            'Eager load petugas butuh user_id di daftar kolom induk.'
        );
    }

    public function test_petugas_yang_sudah_dihapus_tetap_tercantum_di_rekap(): void
    {
        // Relasi `petugas()` memakai withTrashed(): arsip kantor harus tetap
        // menyebut nama orang yang menginput, meski akunnya sudah dihapus
        // (L-10). Eager load yang dibatasi kolom (`petugas:id,nama_lengkap`)
        // adalah tempat paling mungkin untuk senyap mengubah itu jadi "-".
        $surat = $this->suratBerisiPanjang('HEMAT-003');
        $this->petugas->delete();

        $kolom = str_getcsv($this->barisRekap('HEMAT-003')[0], ';');

        $this->assertSame('Staf Arsip', $kolom[11]);
    }

    public function test_buku_agenda_tetap_terbit_dengan_kolom_yang_dipangkas(): void
    {
        $surat = $this->suratBerisiPanjang('HEMAT-004');

        Lampiran::forceCreate([
            'lampiranable_type' => SuratMasuk::class,
            'lampiranable_id' => $surat->id,
            'nama_file' => 'agenda-uji.pdf',
            'disk' => 'arsip',
            'path' => 'uji/agenda-uji.pdf',
        ]);

        // Agenda memakai baris() yang sama dengan CSV. Yang diuji di sini adalah
        // jalur data-nya sampai ke template tanpa exception (DOMPDFException /
        // undefined index), bukan isi teksnya: stream PDF ditekan sehingga
        // mencocokkan string di dalamnya tidak berarti apa-apa.
        $isi = $this->actingAs($this->petugas)
            ->get(route('laporan.agenda', ['dari' => '2026-05-01', 'sampai' => '2026-05-31']))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->getContent();

        $this->assertStringStartsWith('%PDF-', $isi);
        $this->assertStringContainsString('%%EOF', $isi, 'PDF agenda terpotong di tengah.');
    }
}
