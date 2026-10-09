<?php

namespace App\Models;

use App\Support\CariArsip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read int $lampiran_count Kolom virtual hasil `withCount('lampiran')`
 *          (bukan kolom di tabel). Dinyatakan di sini supaya PHPStan dan IDE
 *          mengenalnya di query yang memakai withCount — lihat LaporanController.
 */
class SuratMasuk extends Model
{
    // L-05: surat tidak pernah hilang benar-benar dari sistem; yang ada hanya
    // disembunyikan dari daftar (pulihkan lewat admin) atau dimusnahkan lewat
    // alur pemusnahan + Berita Acara.
    use SoftDeletes;

    protected $table = 'surat_masuk';

    /**
     * Kolom yang digali kotak "cari" (L-15).
     *
     * `isi_hasil_baca` ikut sejak 9 Okt 2026: fitur baca otomatis isi lampiran
     * (5 Okt 2026) menyimpan teks hasil baca PDF/DOCX/XLSX di kolom itu, tapi
     * tidak ada satu pun query yang mencarinya — jadi isinya terbaca ke layar
     * dan tetap tidak bisa ditemukan. `ringkasan` saja tidak cukup karena ia
     * hanya berubah kalau user mencentang "jadikan ringkasan".
     */
    private const KOLOM_CARI = [
        'nomor_surat', 'perihal', 'pengirim', 'jabatan_pengirim', 'instansi_pengirim',
        'kota_asal', 'provinsi_asal', 'lokasi_fisik', 'ringkasan', 'isi_hasil_baca',
    ];

    /** Nama berkas lampiran ikut digali — orang mengingat "scan pembayaran", bukan nomornya. */
    private const RELASI_CARI = ['lampiran' => ['nama_file']];

    protected $fillable = [
        'pengirim', 'jabatan_pengirim', 'instansi_pengirim',
        'klasifikasi_primer_id', 'klasifikasi_sekunder_id', 'klasifikasi_tersier_id',
        'sifat', 'nomor_surat', 'kota_asal', 'provinsi_asal',
        'tanggal_surat', 'tanggal_diterima', 'perihal', 'ringkasan',
        'status_berkas', 'status_arsip', 'lokasi_fisik', 'user_id',
    ];

    /**
     * SENGAJA tidak ada di $fillable: `isi_hasil_baca`, `isi_dibaca_dari`,
     * `isi_dibaca_pada`, `isi_terverifikasi_pada` (fitur baca otomatis 5 Okt 2026).
     * Keempatnya dikelola sistem — ditulis `LampiranController` saat unggah dan
     * `SuratMasukController::updateIsi()` saat user memeriksa. Kalau suatu hari
     * form surat ikut mengirimkannya, isinya harus lewat jalur eksplisit itu,
     * bukan lewat mass assignment. Di tes pakai `forceFill()`.
     */
    protected $casts = [
        'tanggal_surat' => 'date',
        'tanggal_diterima' => 'date',
        'isi_dibaca_pada' => 'datetime',
        'isi_terverifikasi_pada' => 'datetime',
    ];

    /**
     * Petugas yang mencatat surat ini. `withTrashed()` supaya nama tetap tampil
     * walau usernya sudah dihapus (L-10: tidak ada sistem kepemilikan).
     *
     * Return type generik (`BelongsTo<User, $this>`, bukan `BelongsTo` polos)
     * dipakai PHPStan/IDE untuk tahu MODEL sebelah mana yang kembali: tanpa itu
     * `$surat->petugas?->nama_lengkap` cuma `Model` buta dan setiap pemakainya
     * masuk baseline Larastan. Urutan parameter: related dulu, declaring belakangan.
     *
     * @return BelongsTo<User, $this>
     */
    public function petugas(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    /** @return BelongsTo<KlasifikasiPrimer, $this> */
    public function primer(): BelongsTo
    {
        return $this->belongsTo(KlasifikasiPrimer::class, 'klasifikasi_primer_id');
    }

    /** @return BelongsTo<KlasifikasiSekunder, $this> */
    public function sekunder(): BelongsTo
    {
        return $this->belongsTo(KlasifikasiSekunder::class, 'klasifikasi_sekunder_id');
    }

    /** @return BelongsTo<KlasifikasiTersier, $this> */
    public function tersier(): BelongsTo
    {
        return $this->belongsTo(KlasifikasiTersier::class, 'klasifikasi_tersier_id');
    }

    /** @return MorphMany<Lampiran, $this> */
    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'lampiranable');
    }

    /**
     * Satu-satunya jalur filter "cari" untuk surat masuk. Dipakai daftar surat
     * masuk dan mode gabungan "Semua arsip" supaya keduanya tidak bisa
     * menyimpulkan sendiri (lihat header kelas App\Support\CariArsip).
     *
     * @param  Builder<SuratMasuk>  $query
     * @return Builder<SuratMasuk>
     */
    public function scopeCari(Builder $query, ?string $masukan): Builder
    {
        return CariArsip::terapkan($query, $masukan, self::KOLOM_CARI, self::RELASI_CARI);
    }

    /**
     * @param  Builder<SuratMasuk>  $query
     * @return Builder<SuratMasuk>
     */
    public function scopePalingRelevan(Builder $query, ?string $masukan): Builder
    {
        return CariArsip::peringkat($query, $masukan);
    }
}
