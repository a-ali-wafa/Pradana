<?php

namespace App\Models;

use App\Services\GoogleDriveService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

class Lampiran extends Model
{
    protected $table = 'lampiran';

    protected $fillable = [
        'lampiranable_id', 'lampiranable_type',
        'google_drive_file_id', 'google_drive_folder_id',
        'disk', 'path', 'hash_file',
        'nama_file', 'mime_type', 'ukuran', 'diunggah_oleh',
    ];

    /** @return MorphTo<Model, $this> */
    public function lampiranable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function pengunggah(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diunggah_oleh');
    }

    /**
     * Riwayat pengajuan hapus file ini (menunggu/disetujui/ditolak).
     * Dipakai di halaman show surat dan daftar pengajuan admin.
     *
     * @return HasMany<PengajuanHapusLampiran, $this>
     */
    public function pengajuanHapus(): HasMany
    {
        return $this->hasMany(PengajuanHapusLampiran::class, 'lampiran_id');
    }

    /**
     * File fisik sudah tersedia di disk lokal (sumber utama sejak L-01)?
     */
    public function adaDiLokal(): bool
    {
        return filled($this->path) && Storage::disk($this->disk ?? 'arsip')->exists($this->path);
    }

    /**
     * Lampiran boleh DIAJUKAN untuk dihapus kalau surat induknya sudah lewat
     * retensi (L-04: lebih dari 5 tahun; L-21: acuannya `tanggal_surat` untuk
     * kedua jenis surat).
     *
     * Nama method diubah ke Indonesia (`layakDihapus`) karena seluruh kosakata
     * domain lain di project ini Indonesia — `hapusBerkasFisik`, `lewatRetensi`,
     * `umurTahun`. Aturan 5 tahun TIDAK dihitung di sini lagi: ia didelegasikan
     * ke `UmurArsip::lewatRetensi()` milik surat induk, supaya jalur yang
     * menegakkan aturan di server (`PengajuanHapusLampiranController::store()`)
     * dan yang menampilkan tombolnya di layar tidak bisa berbeda pendapat.
     *
     * `$this->lampiranable` dipakai apa adanya: kalau relasinya sudah
     * eager-loaded (halaman show surat memuat `lampiran.lampiranable`), tidak ada
     * query tambahan sama sekali.
     */
    public function layakDihapus(): bool
    {
        $surat = $this->lampiranable;

        // Cek eksplisit, bukan sekadar null-check: `lampiranable` adalah morph,
        // jadi secara teori barisnya bisa menunjuk kelas yang bukan surat (mis.
        // sisa data lama). Yang bukan surat masuk/keluar tidak punya aturan
        // retensi di aplikasi ini, jadi dianggap TIDAK layak — gagal tertutup.
        if (! $surat instanceof SuratMasuk && ! $surat instanceof SuratKeluar) {
            return false;
        }

        return $surat->lewatRetensi();
    }

    /**
     * Hapus berkas fisiknya: selalu file lokal, dan file Drive (kalau masih
     * ada) dipakai sebagai usaha terbaik — kegagalan Drive tidak boleh
     * memblokir penghapusan arsip lokal.
     */
    public function hapusBerkasFisik(): void
    {
        if (filled($this->path)) {
            Storage::disk($this->disk ?? 'arsip')->delete($this->path);
        }

        if (filled($this->google_drive_file_id)) {
            try {
                app(GoogleDriveService::class)->delete($this->google_drive_file_id);
            } catch (\Throwable $e) {
                logger()->warning('Gagal hapus salinan Drive saat menghapus lampiran', [
                    'lampiran_id' => $this->id,
                    'google_drive_file_id' => $this->google_drive_file_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
