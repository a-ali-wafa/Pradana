<?php

namespace App\Providers;

use App\Models\KlasifikasiPrimer;
use App\Models\KlasifikasiSekunder;
use App\Models\KlasifikasiTersier;
use App\Models\Lampiran;
use App\Models\PengajuanHapusLampiran;
use App\Models\PengaturanInstansi;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Observers\KlasifikasiPrimerObserver;
use App\Observers\KlasifikasiSekunderObserver;
use App\Observers\KlasifikasiTersierObserver;
use App\Observers\LampiranObserver;
use App\Observers\PengajuanHapusLampiranObserver;
use App\Observers\PengaturanInstansiObserver;
use App\Observers\SuratKeluarObserver;
use App\Observers\SuratMasukObserver;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\GoogleDriveService::class, function ($app) {
            $client = new \Google\Client();
            $client->setApplicationName('PRADANA Arsip Digital');

            $credentialsPath = config('gdrive.credentials_path');
            if ($credentialsPath && file_exists($credentialsPath)) {
                $client->setAuthConfig($credentialsPath);
            }

            $client->addScope(\Google\Service\Drive::DRIVE);

            $drive = new \Google\Service\Drive($client);

            return new \App\Services\GoogleDriveService($drive);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Logging aktivitas otomatis (baru, 1 Sep 2026) — lihat AGENTS.md 12.22.
        // Kalau nanti ada isi boot() lain yang ditambahkan, taruh SETELAH baris
        // registrasi Observer ini atau sebelum, urutan di antara ini tidak penting.
        SuratMasuk::observe(SuratMasukObserver::class);
        SuratKeluar::observe(SuratKeluarObserver::class);
        KlasifikasiPrimer::observe(KlasifikasiPrimerObserver::class);
        KlasifikasiSekunder::observe(KlasifikasiSekunderObserver::class);
        KlasifikasiTersier::observe(KlasifikasiTersierObserver::class);
        Lampiran::observe(LampiranObserver::class);
        PengajuanHapusLampiran::observe(PengajuanHapusLampiranObserver::class);
        PengaturanInstansi::observe(PengaturanInstansiObserver::class);
        User::observe(UserObserver::class);
    }
}
