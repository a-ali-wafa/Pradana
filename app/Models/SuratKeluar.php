<?php

namespace App\Models;

use App\Support\CariArsip;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property-read int $lampiran_count Kolom virtual hasil `withCount('lampiran')`,
 *          lihat catatan yang sama di SuratMasuk.
 */
class SuratKeluar extends Model
{
    // Lihat SuratMasuk: soft delete sesuai keputusan L-05.
    use SoftDeletes;

    protected $table = 'surat_keluar';

    /**
     * Kolom yang digali kotak "cari" (L-15). Isi surat keluar TIDAK ada di
     * tabel ini — ia di `draf_konten_surat_keluar`, lihat RELASI_CARI.
     */
    private const KOLOM_CARI = [
        'nomor_surat', 'perihal', 'penerima', 'jabatan_penerima', 'instansi_penerima',
        'kota_tujuan', 'provinsi_tujuan', 'lokasi_fisik', 'ringkasan',
    ];

    /**
     * `isi_surat` + `tembusan` (draf konten) dan `nama_file` (lampiran) ikut
     * digali. Daftar ini dulu hidup dua kali di controller dengan isi sedikit
     * berbeda; sekarang satu tempat.
     */
    private const RELASI_CARI = [
        'drafKonten' => ['isi_surat', 'tembusan'],
        'lampiran' => ['nama_file'],
    ];

    protected $fillable = [
        'penerima', 'jabatan_penerima', 'instansi_penerima',
        'klasifikasi_primer_id', 'klasifikasi_sekunder_id', 'klasifikasi_tersier_id',
        'sifat', 'nomor_surat', 'kota_tujuan', 'provinsi_tujuan',
        'tanggal_surat', 'perihal', 'ringkasan',
        'status_berkas', 'status_arsip', 'lokasi_fisik', 'user_id',
    ];

    protected $casts = [
        'tanggal_surat' => 'date',
    ];

    /**
     * Return type generik di seluruh model (format `BelongsTo<User, $this>`):
     * alasannya ada di SuratMasuk, file yang sama, perapian 9 Okt 2026.
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

    /** @return HasOne<DrafKontenSuratKeluar, $this> */
    public function drafKonten(): HasOne
    {
        return $this->hasOne(DrafKontenSuratKeluar::class);
    }

    /** @return MorphMany<Lampiran, $this> */
    public function lampiran(): MorphMany
    {
        return $this->morphMany(Lampiran::class, 'lampiranable');
    }

    /**
     * Satu-satunya jalur filter "cari" untuk surat keluar — dipakai daftar
     * surat keluar dan mode gabungan "Semua arsip".
     *
     * @param  Builder<SuratKeluar>  $query
     * @return Builder<SuratKeluar>
     */
    public function scopeCari(Builder $query, ?string $masukan): Builder
    {
        return CariArsip::terapkan($query, $masukan, self::KOLOM_CARI, self::RELASI_CARI);
    }

    /**
     * @param  Builder<SuratKeluar>  $query
     * @return Builder<SuratKeluar>
     */
    public function scopePalingRelevan(Builder $query, ?string $masukan): Builder
    {
        return CariArsip::peringkat($query, $masukan);
    }
}
