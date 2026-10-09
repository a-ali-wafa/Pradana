<?php

namespace App\Providers;

use App\Events\LogAktivitasEvent;
use App\Listeners\CatatLogAktivitasListener;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event to listener mappings for the application.
     *
     * Satu-satunya event aplikasi: mencatat aksi ke tabel `aktivitas`.
     * `Registered => SendEmailVerificationNotification` dulu ada di sini dari
     * boilerplate Laravel, tapi kantor tidak punya registrasi publik maupun SMTP
     * (L-11: login dibuat manual oleh admin lewat `arsip:akun-pertama`), jadi
     * pasangan itu tidak pernah bisa jalan dan sudah dibuang.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        LogAktivitasEvent::class => [
            CatatLogAktivitasListener::class,
        ],
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        //
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
