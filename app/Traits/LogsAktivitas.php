<?php

namespace App\Traits;

use App\Models\Aktivitas;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * BARU — 1 Sep 2026. Bagian dari roadmap "Logging aktivitas otomatis di setiap aksi"
 * (sebelumnya cuma dicatat sebagai rencana di 12.8, belum ada implementasi apa pun).
 *
 * Dipakai oleh Model Observer (lihat app/Observers/) supaya tiap Observer tidak
 * menulis ulang logika insert ke tabel `aktivitas`.
 *
 * ASUMSI (model Aktivitas.php sendiri TIDAK diupload ke sesi ini, tapi $fillable-nya
 * sudah terverifikasi 28 Agu 2026 — lihat 12.15 di AGENTS_HISTORY.md — jadi dipakai
 * langsung, bukan tebakan baru):
 * - $fillable: user_id, aksi, subjek_type, subjek_id.
 * - aktivitas.user_id TIDAK nullable (FK restrictOnDelete, lihat Bagian 5 AGENTS.md)
 *   → kalau tidak ada user yang sedang login (mis. dipanggil dari seeder/console/job
 *   terjadwal), log DILEWATI (tidak dibuat sama sekali) daripada memaksa insert yang
 *   akan gagal atau salah menuliskan user_id.
 */
trait LogsAktivitas
{
    protected function catatAktivitas(string $aksi, Model $subjek): void
    {
        if (! Auth::check()) {
            return;
        }

        Aktivitas::create([
            'user_id' => Auth::id(),
            'aksi' => $aksi,
            'subjek_type' => $subjek->getMorphClass(),
            'subjek_id' => $subjek->getKey(),
        ]);
    }
}
