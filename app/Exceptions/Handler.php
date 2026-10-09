<?php

namespace App\Exceptions;

use App\Support\TandaiPrivat;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    /**
     * Header anti-index juga harus ada di HALAMAN ERROR.
     *
     * Exception di-render di luar pipeline middleware, jadi `TandaiArsipPrivat`
     * tidak pernah menyentuh respons 404/419/500/503 — padahal halaman inilah
     * yang paling sering ditemui crawler (tautan basi, bookmark, URL tebakan).
     * Tanpa override ini, arsip kantor bocor lewat alamat yang salah ketik.
     */
    public function render($request, Throwable $e)
    {
        $respons = parent::render($request, $e);

        if ($respons instanceof Response) {
            TandaiPrivat::terapkan($respons);
        }

        return $respons;
    }
}
