<?php

namespace App\Observers;

use App\Models\PemusnahanArsip;
use App\Traits\LogsAktivitas;

/**
 * BARU — 4 Okt 2026 (L-06 / E3).
 *
 * Log pemusnahan JANGAN sampai dihapus oleh perintah retensi log (L-22):
 * Berita Acara bisa dicetak bertahun-tahun kemudian, dan jejak "siapa yang
 * menyetujui pemusnahan ini" adalah bagian dari pembuktiannya.
 */
class PemusnahanArsipObserver
{
    use LogsAktivitas;

    public function created(PemusnahanArsip $pemusnahan): void
    {
        // Sengaja tidak menyebut jumlah surat: Observer `created` jalan di tengah
        // transaksi, SEBELUM baris items sempat dimasukkan (lihat store()), jadi
        // hitungan di sini akan selalu 0 dan menyesatkan.
        $this->catatAktivitas(
            'Mengajukan pemusnahan arsip, menunggu persetujuan admin',
            $pemusnahan
        );
    }

    public function updated(PemusnahanArsip $pemusnahan): void
    {
        if (! $pemusnahan->wasChanged('status')) {
            return;
        }

        $jumlah = $pemusnahan->items()->count();

        $aksi = match ($pemusnahan->status) {
            'disetujui' => "Menyetujui pemusnahan arsip ({$jumlah} surat) — Berita Acara {$pemusnahan->nomor_berita_acara}",
            'ditolak' => "Menolak pengajuan pemusnahan arsip ({$jumlah} surat)",
            default => "Mengubah status pemusnahan arsip ({$jumlah} surat)",
        };

        $this->catatAktivitas($aksi, $pemusnahan);
    }
}
