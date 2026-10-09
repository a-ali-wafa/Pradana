<?php

namespace Tests\Feature;

use App\Models\Lampiran;
use App\Models\PemusnahanArsip;
use App\Models\PengajuanHapusLampiran;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\MariaDbHarness;

/**
 * Dua bentuk SQL BARU yang dipakai di hampir semua layar, dibuktikan di MariaDB
 * sungguhan (L-24 / Q1=b) — keduanya masuk lewat perapian Fase 1, 9 Okt 2026:
 *
 * 1. `SUM(CASE WHEN … THEN 1 ELSE 0 END)` sebagai pengganti sembilan `count()`
 *    di dashboard, dengan batas periode interval setengah terbuka. Di SQLite
 *    tes ini tidak memberi informasi apa pun; yang perlu dibuktikan justru
 *    MariaDB: kolom DATE-nya memotong jam, dan `CASE` di posisi SELECT harus
 *    diterima tanpa error.
 * 2. Badge antrian di sidebar (`layouts.app`) yang sekarang SATU query
 *    `UNION ALL` dua tabel, bukan dua `count()`. Composer itu jalan di SETIAP
 *    halaman, jadi kalau sintaksnya ditolak MariaDB, seluruh aplikasi 500.
 *
 * Yang diuji = ANGKA yang tampil, bukan hanya "tidak error": UNION ALL yang
 * kaki-kakinya tertukar tetap menghasilkan 200 dengan angka salah.
 */
class AgregatMariaDbTest extends MariaDbHarness
{
    protected const PENANDA = 'MJG';

    protected const KODE_PRIMER = 'ZE';

    private User $admin;

    /** @var list<int> */
    private array $pengajuanDibuat = [];

    /** @var list<int> */
    private array $pemusnahanDibuat = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::query()->firstOrCreate(
            ['email' => 'admin.harness@example.test'],
            [
                'nama_lengkap' => 'Kepala Desa Uji',
                'pin' => bcrypt('12345678'),
                'role' => 'admin',
            ]
        );
    }

    protected function tearDown(): void
    {
        // Baris antrian tidak punya penanda nomor surat, jadi dibuang lewat id
        // yang dicatat saat dibuat. Tanpa ini, badge di run berikutnya ikut
        // membengkak dan tes jadi bergantung pada urutan jalannya suite.
        if ($this->pengajuanDibuat !== []) {
            DB::table('pengajuan_hapus_lampiran')->whereIn('id', $this->pengajuanDibuat)->delete();
        }

        if ($this->pemusnahanDibuat !== []) {
            DB::table('pemusnahan_arsip_item')->whereIn('pemusnahan_arsip_id', $this->pemusnahanDibuat)->delete();
            DB::table('pemusnahan_arsip')->whereIn('id', $this->pemusnahanDibuat)->delete();
        }

        parent::tearDown();
    }

    public function test_agregat_dashboard_berjalan_dan_angkanya_benar_di_mariadb(): void
    {
        $sebelum = $this->dashboardStats();

        $akhirBulan = Carbon::now()->endOfMonth()->toDateString();
        $bulanDepan = Carbon::now()->startOfMonth()->addMonthNoOverflow()->toDateString();
        $bulanLalu = Carbon::now()->startOfMonth()->subDay()->toDateString();

        // Surat masuk diuji lewat `tanggal_diterima` (kolom yang dipakai
        // dashboard untuk surat masuk), surat keluar lewat `tanggal_surat`.
        foreach ([$bulanLalu, $akhirBulan, $bulanDepan] as $i => $tanggal) {
            SuratMasuk::query()->create(array_merge($this->kerangka(), [
                'pengirim' => 'Kecamatan Uji',
                'nomor_surat' => self::PENANDA."-M{$i}",
                'perihal' => 'Perihal uji agregat',
                'tanggal_surat' => $tanggal,
                'tanggal_diterima' => $tanggal,
                // Surat terakhir (bulan depan) sekaligus mendesak: ia tidak boleh
                // masuk "bulan ini" tapi HARUS masuk hitungan "mendesak aktif".
                'sifat' => $i === 2 ? 'mendesak' : 'biasa',
            ]));
        }

        // array_merge, BUKAN operator `+`: dengan `+` sisi kiri menang, jadi
        // 'inaktif'/'mendesak' di bawah ini diam-diam tidak pernah terpakai dan
        // tesnya lulus karena alasan yang salah.
        SuratKeluar::query()->create(array_merge($this->kerangka(), [
            'penerima' => 'Warga RT 01',
            'nomor_surat' => self::PENANDA.'-K1',
            'perihal' => 'Surat keterangan',
            'tanggal_surat' => $akhirBulan,
            'status_arsip' => 'inaktif',
            'sifat' => 'mendesak',
        ]));

        $sesudah = $this->dashboardStats();

        // SELISIH, bukan angka mutlak: `mysql_test_a` adalah database yang sama
        // dengan yang dipakai NomorSuratKeluarTest, dan dashboard menghitung
        // SELURUH tabel. Assert mutlak akan bergantung pada urutan jalannya suite.
        $this->assertSame(3, $sesudah['total_surat_masuk'] - $sebelum['total_surat_masuk']);
        $this->assertSame(1, $sesudah['total_surat_keluar'] - $sebelum['total_surat_keluar']);

        // Hanya yang tertanggal hari TERAKHIR bulan berjalan yang boleh masuk
        // "bulan ini": satu surat masuk + satu surat keluar.
        $this->assertSame(1, $sesudah['surat_masuk_bulan_ini'] - $sebelum['surat_masuk_bulan_ini']);
        $this->assertSame(1, $sesudah['surat_keluar_bulan_ini'] - $sebelum['surat_keluar_bulan_ini']);

        // Ketiganya 'aktif' (bawaan kerangka()), jadi +3 — yang membedakan cuma
        // periode bulannya.
        $this->assertSame(3, $sesudah['surat_masuk_aktif'] - $sebelum['surat_masuk_aktif']);
        $this->assertSame(0, $sesudah['surat_masuk_inaktif'] - $sebelum['surat_masuk_inaktif']);
        $this->assertSame(0, $sesudah['surat_keluar_aktif'] - $sebelum['surat_keluar_aktif']);
        $this->assertSame(1, $sesudah['surat_keluar_inaktif'] - $sebelum['surat_keluar_inaktif']);
        // `sifat = 'mendesak' AND status_arsip = 'aktif'`: cuma surat masuk bulan
        // depan yang cocok — surat keluar ujiannya mendesak TAPI inaktif, jadi
        // sengaja tidak dihitung (membuktikan AND-nya, bukan cuma salah satu sisi).
        $this->assertSame(1, $sesudah['surat_mendesak_aktif'] - $sebelum['surat_mendesak_aktif']);
    }

    /**
     * @return array<string, int>
     */
    private function dashboardStats(): array
    {
        $respons = $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

        /** @var array<string, int> $stats */
        $stats = $respons->viewData('stats');

        return $stats;
    }

    public function test_badge_antrian_sidebar_membedakan_dua_sumber_di_mariadb(): void
    {
        $sebelum = $this->antrian();

        $surat = SuratMasuk::query()->create([
            'user_id' => $this->userId,
            'pengirim' => 'Kecamatan Uji',
            'klasifikasi_primer_id' => $this->primerId,
            'nomor_surat' => self::PENANDA.'-ANTRIAN',
            'perihal' => 'Perihal uji badge',
            'tanggal_surat' => Carbon::now()->toDateString(),
            'tanggal_diterima' => Carbon::now()->toDateString(),
            'status_arsip' => 'aktif',
            'sifat' => 'biasa',
        ]);

        // Dua berkas menunggu persetujuan hapus, satu pengajuan pemusnahan
        // menunggu — badge harus memisahkan keduanya, bukan menjumlahkan.
        foreach ([1, 2] as $nomor) {
            $lampiran = Lampiran::query()->create([
                'lampiranable_type' => SuratMasuk::class,
                'lampiranable_id' => $surat->id,
                'nama_file' => self::PENANDA."-berkas-{$nomor}.pdf",
                'disk' => 'arsip',
                'path' => 'uji/'.self::PENANDA."-berkas-{$nomor}.pdf",
            ]);

            $pengajuan = PengajuanHapusLampiran::query()->create([
                'lampiran_id' => $lampiran->id,
                'nama_file_snapshot' => $lampiran->nama_file,
                'diajukan_oleh' => $this->userId,
                'status' => 'menunggu',
            ]);

            $this->pengajuanDibuat[] = $pengajuan->id;
        }

        $pemusnahan = PemusnahanArsip::query()->forceCreate([
            'status' => 'menunggu',
            'diajukan_oleh' => $this->userId,
        ]);

        $this->pemusnahanDibuat[] = $pemusnahan->id;

        $sesudah = $this->antrian();

        $this->assertSame($sebelum['hapus'] + 2, $sesudah['hapus']);
        $this->assertSame($sebelum['musnah'] + 1, $sesudah['musnah']);

        // Angka total tidak dipakai di assertion di atas kalau kedua kaki UNION
        // ternyata terbalik: sumber mana yang menghasilkan angka berapa.
        $this->assertSame(2, $sesudah['hapus'] - $sebelum['hapus']);
    }

    /**
     * Kolom wajib yang sama untuk semua baris uji.
     *
     * @return array<string, mixed>
     */
    private function kerangka(): array
    {
        return [
            'user_id' => $this->userId,
            'klasifikasi_primer_id' => $this->primerId,
            'status_arsip' => 'aktif',
            'sifat' => 'biasa',
        ];
    }

    /**
     * Baca dua badge antrian dari HTML yang ter-render.
     *
     * `viewData('antrianHapusLampiran')` TIDAK bisa dipakai: composer menempel
     * pada `layouts.app` (view INDUK), sementara TestResponse hanya membuka data
     * view terluar (`surat-masuk.index`) — kuncinya memang tidak ada di sana.
     * Karena itu angkanya dibaca dari markup, berurutan: badge "pengajuan hapus
     * lampiran" didaftarkan lebih dulu daripada badge pemusnahan (lihat
     * resources/views/layouts/app.blade.php).
     *
     * @return array{hapus: int, musnah: int}
     */
    private function antrian(): array
    {
        $isi = $this->actingAs($this->admin)->get(route('surat-masuk.index'))
            ->assertOk()
            ->getContent();

        preg_match_all(
            '/class="badge text-bg-warning ms-auto">\s*(\d+) menunggu/',
            (string) $isi,
            $cocok
        );

        return [
            'hapus' => (int) ($cocok[1][0] ?? 0),
            'musnah' => (int) ($cocok[1][1] ?? 0),
        ];
    }
}
