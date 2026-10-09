<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Otak kotak "cari" di daftar surat masuk & surat keluar.
 *
 * Kenapa satu kelas dan bukan dua blok `where(...)` di controller: perilaku
 * pencarian HARUS sama di daftar masuk, daftar keluar, dan mode gabungan —
 * kedua controller dulu menyalin daftar kolomnya masing-masing dan isinya
 * sudah melenceng (yang satu menggali `ringkasan`, yang lain menggali
 * `tembusan`).
 *
 * SENGAJA masih LIKE, bukan MATCH AGAINST (keputusan S11, 4 Okt 2026: dataset
 * kantor masih ribuan baris dan LIKE jalan identik di MariaDB maupun SQLite
 * yang dipakai suite tes). Kalau volume ternyata bikin lambat, yang ditambah
 * itu satu migration FULLTEXT — bukan rombak kelas ini, karena model tetap
 * memanggil lewat `scopeCari()`.
 *
 * Semua SQL mentah di kelas ini harus jalan di DUA engine. Itu sebabnya
 * karakter escape LIKE dipilih `!` dan bukan `\`: MariaDB memproses backslash
 * di dalam string literal (`'\\'` = satu backslash) sedangkan SQLite tidak
 * (`'\\'` = dua, dan ESCAPE menuntut satu karakter). `ESCAPE '\'` pun gagal di
 * MariaDB karena backslashnya dimakan parser string. Huruf `!` adalah literal
 * satu karakter yang identik di keduanya.
 */
class CariArsip
{
    /** Lebih dari ini bukan pencarian lagi — dan tiap kata menambah satu LIKE. */
    public const MAKS_KATA = 8;

    /** Satu kata dipotong di sini supaya pola LIKE tidak bisa jadi raksasa. */
    public const MAKS_PANJANG_KATA = 60;

    /** Karakter escape LIKE — lihat catatan kelas di atas. */
    public const MASKAWAT = '!';

    /**
     * Pecah kalimat jadi daftar kata.
     *
     * Ini inti perbaikan: `cari = "surat izin 2024"` dulu harus ada PERSIS
     * BERUNTUN di satu kolom, padahal orang mengetik beberapa kata dan cuma
     * mengingat sebagian. Sekarang tiap kata harus cocok di suatu tempat
     * (AND antar kata, OR antar kolom dan antar tabel relasi).
     *
     * @return list<string>
     */
    public static function kata(?string $masukan): array
    {
        $bersih = trim(preg_replace('/\s+/u', ' ', (string) $masukan) ?? '');

        if ($bersih === '') {
            return [];
        }

        $kata = [];

        foreach (explode(' ', $bersih) as $satu) {
            $satu = mb_substr(trim($satu), 0, self::MAKS_PANJANG_KATA);

            if ($satu === '' || in_array($satu, $kata, true)) {
                continue;
            }

            $kata[] = $satu;

            if (count($kata) >= self::MAKS_KATA) {
                break;
            }
        }

        return $kata;
    }

    /**
     * Kalimat utuh (kata hasil split, spasi tunggal) untuk penilaian relevansi.
     */
    public static function frasa(?string $masukan): string
    {
        return implode(' ', self::kata($masukan));
    }

    /**
     * Masker LIKE untuk satu kata.
     *
     * `%` dan `_` adalah karakter yang benar-benar diketik orang kantor: nama
     * berkas sering memakai `_` (`scan_pembayaran.pdf`), dan dulu `_` di dalam
     * kata kunci diam-diam jadi wildcard satu karakter.
     */
    public static function pola(string $kata): string
    {
        return '%'.self::topeng($kata).'%';
    }

    /**
     * Topengi karakter khusus LIKE. Backslash sengaja TIDAK di-escape karena
     * karakter escape kita `!`, bukan `\`.
     */
    public static function topeng(string $kata): string
    {
        return str_replace(
            [self::MASKAWAT, '%', '_'],
            [self::MASKAWAT.self::MASKAWAT, self::MASKAWAT.'%', self::MASKAWAT.'_'],
            $kata
        );
    }

    /**
     * Terapkan pencarian multi-kata ke sebuah query Eloquent.
     *
     * `@template TModel` bukan hiasan: tanpa itu PHPStan menolak
     * `Builder<SuratMasuk>` masuk ke parameter bertipe `Builder<Model>`
     * (templatenya tidak kovarian), dan scope di model ikut salah simpul.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  list<string>  $kolom  nama kolom di tabel model ini
     * @param  array<string, list<string>>  $relasi  nama relasi => kolom di tabelnya
     * @return Builder<TModel>
     */
    public static function terapkan(
        Builder $query,
        ?string $masukan,
        array $kolom,
        array $relasi = []
    ): Builder {
        foreach (self::kata($masukan) as $kata) {
            $pola = self::pola($kata);

            $query->where(function (Builder $satuKata) use ($kolom, $relasi, $pola): void {
                foreach ($kolom as $nama) {
                    $satuKata->orWhereRaw(self::like($nama), [$pola]);
                }

                foreach ($relasi as $namaRelasi => $kolomRelasi) {
                    $satuKata->orWhereHas($namaRelasi, function (Builder $rel) use ($kolomRelasi, $pola): void {
                        // where() pembungkus ini WAJIB, bukan hiasan. Tanpa dia,
                        // orWhereRaw() di bawah tersambung ke constraint relasi
                        // dengan OR: `WHERE fk = surat.id OR isi_surat LIKE ?`
                        // — satu baris draf/lampiran milik surat lain membuat
                        // EXISTS benar untuk SEMUA surat, dan hasil pencarian
                        // membuncah. Bug ini sudah ada di kode lama (bentuknya
                        // `fk = id AND isi LIKE ? OR tembusan LIKE ?`, bocor
                        // lewat tembusan), dibuktikan tes
                        // CariArsipTest::test_isi_draf_surat_lain_tidak_bocor.
                        $rel->where(function (Builder $dalam) use ($kolomRelasi, $pola): void {
                            foreach ($kolomRelasi as $nama) {
                                $dalam->orWhereRaw(self::like($nama), [$pola]);
                            }
                        });
                    });
                }
            });
        }

        return $query;
    }

    /**
     * Peringkat relevansi murah, TANPA index FULLTEXT.
     *
     * Yang dicari orang kantor hampir selalu nomor surat atau perihal. Surat
     * bernomor `471/001` harus di atas surat yang cuma menyebut "471" di
     * dalam ringkasan tiga paragraf. Urutan tinggi: nomor persis > nomor
     * diawali > perihal diawali > salah satu memuat. Urutan tanggal yang lama
     * tetap dipakai sebagai pemecah seri (panggil `orderByDesc` sesudah ini).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function peringkat(Builder $query, ?string $masukan): Builder
    {
        if (self::frasa($masukan) === '') {
            return $query;
        }

        [$skor, $binding] = self::ekspresiSkor($masukan);

        return $query->orderByRaw($skor.' DESC', $binding);
    }

    /**
     * Ekspresi SQL skor + bindingnya, dipakai dua tempat dengan timbangan yang
     * harus sama: `peringkat()` memasangnya di ORDER BY, mode gabungan "Semua
     * arsip" memasangnya sebagai kolom `skor` di kaki UNION supaya urutan
     * gabungan bisa dinilai sebelum dua tabel digabung.
     *
     * @return array{0: string, 1: list<string>}
     */
    public static function ekspresiSkor(?string $masukan): array
    {
        $frasa = self::frasa($masukan);
        $awal = self::topeng($frasa).'%';
        $muat = self::pola($frasa);

        $like = "LIKE ? ESCAPE '".self::MASKAWAT."'";

        return [
            'CASE'
            .' WHEN nomor_surat = ? THEN 400'
            ." WHEN nomor_surat {$like} THEN 300"
            ." WHEN perihal {$like} THEN 200"
            ." WHEN nomor_surat {$like} OR perihal {$like} THEN 100"
            .' ELSE 0 END',
            [$frasa, $awal, $awal, $muat, $muat],
        ];
    }

    /**
     * Tandai kata yang cocok dengan `<mark>` untuk ditampilkan di daftar.
     *
     * Urutannya sengaja `e()` dulu baru sisip tag: teks surat adalah isian
     * user, jadi kutip dan sudut kurung sudah jadi entitas HTML sebelum
     * `<mark>` ditambahkan. Keluarannya untuk `{!! ... !!}`.
     */
    public static function sorot(?string $teks, ?string $masukan): HtmlString
    {
        $kata = self::kata($masukan);

        if ($kata === []) {
            return new HtmlString(e($teks ?? ''));
        }

        $pola = '#('.implode('|', array_map(
            static fn (string $s): string => preg_quote($s, '#'),
            $kata
        )).')#iu';

        $html = e($teks ?? '');
        $sorot = preg_replace($pola, '<mark>$1</mark>', $html);

        return new HtmlString($sorot === null ? $html : $sorot);
    }

    /**
     * Potongan SQL `<kolom> LIKE ? ESCAPE '!'`.
     *
     * Nama kolom datang dari daftar statis di model, bukan dari request —
     * tetap dijaga regex karena ini satu-satunya tempat nama kolom masuk ke
     * SQL sebagai teks.
     */
    private static function like(string $kolom): string
    {
        if (preg_match('/^[a-z_]+$/', $kolom) !== 1) {
            throw new \InvalidArgumentException("Nama kolom pencarian tidak sah: {$kolom}");
        }

        return $kolom." LIKE ? ESCAPE '".self::MASKAWAT."'";
    }
}
