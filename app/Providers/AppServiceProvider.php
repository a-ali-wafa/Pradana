<?php

namespace App\Providers;

use App\Models\DrafKontenSuratKeluar;
use App\Models\KlasifikasiPrimer;
use App\Models\KlasifikasiSekunder;
use App\Models\KlasifikasiTersier;
use App\Models\Lampiran;
use App\Models\PemusnahanArsip;
use App\Models\PengajuanHapusLampiran;
use App\Models\PengaturanInstansi;
use App\Models\SuratKeluar;
use App\Models\SuratMasuk;
use App\Models\User;
use App\Observers\DrafKontenSuratKeluarObserver;
use App\Observers\KlasifikasiPrimerObserver;
use App\Observers\KlasifikasiSekunderObserver;
use App\Observers\KlasifikasiTersierObserver;
use App\Observers\LampiranObserver;
use App\Observers\PemusnahanArsipObserver;
use App\Observers\PengajuanHapusLampiranObserver;
use App\Observers\PengaturanInstansiObserver;
use App\Observers\SuratKeluarObserver;
use App\Observers\SuratMasukObserver;
use App\Observers\UserObserver;
use App\Services\GoogleDriveService;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Drive;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(GoogleDriveService::class, function ($app) {
            $client = new Client;
            $client->setApplicationName('PRADANA Arsip Digital');

            $credentialsPath = config('gdrive.credentials_path');
            if ($credentialsPath && file_exists($credentialsPath)) {
                $client->setAuthConfig($credentialsPath);
            }

            $client->addScope(Drive::DRIVE);

            $drive = new Drive($client);

            return new GoogleDriveService($drive);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Nama bulan & hari berbahasa Indonesia untuk semua `translatedFormat()`/
        // `diffForHumans()` di view dan PDF. Tanpa ini output-nya tetap bahasa
        // Inggris meskipun `config/app.locale` = 'id' (Carbon punya locale sendiri).
        Carbon::setLocale('id');

        // H1=b: satu-satunya "notifikasi" yang diminta kantor adalah antrian
        // pengajuan yang menunggu approval admin. Tidak ada email/WA (kantor
        // tanpa SMTP), jadi angkanya ditempel di menu sidebar admin — kalau
        // tidak, antrian itu tidak pernah terlihat.
        View::composer('layouts.app', function (\Illuminate\View\View $view) {
            $user = Auth::user();

            if (! $user || ! $user->isAdmin()) {
                $view->with(['antrianHapusLampiran' => 0, 'antrianPemusnahan' => 0]);

                return;
            }

            // SATU round-trip untuk dua angka. Composer ini menempel di
            // `layouts.app`, jadi dia jalan di SETIAP halaman aplikasi — dua
            // `count()` terpisah berarti dua statement tambahan di setiap klik
            // user, selamanya. Bentuk UNION tetap memakai builder model (bukan
            // nama tabel mentah) supaya scope soft delete masing-masing model
            // tidak ikut hilang.
            $baris = PengajuanHapusLampiran::query()
                ->selectRaw("'lampiran' as sumber, count(*) as jumlah")
                ->where('status', 'menunggu')
                ->unionAll(
                    PemusnahanArsip::query()
                        ->selectRaw("'pemusnahan' as sumber, count(*) as jumlah")
                        ->where('status', 'menunggu')
                )
                ->get();

            $antrian = $baris->pluck('jumlah', 'sumber');

            $view->with([
                'antrianHapusLampiran' => (int) $antrian->get('lampiran', 0),
                'antrianPemusnahan' => (int) $antrian->get('pemusnahan', 0),
            ]);
        });

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
        DrafKontenSuratKeluar::observe(DrafKontenSuratKeluarObserver::class);
        PemusnahanArsip::observe(PemusnahanArsipObserver::class);
    }
}
