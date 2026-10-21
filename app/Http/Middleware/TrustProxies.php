<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

class TrustProxies extends Middleware
{
    /**
     * The trusted proxies for this application.
     *
     * Diisi dari `config('security.trusted_proxies')`, bukan properti hard-coded dan
     * bukan `env()` langsung: deploy memakai `config:cache` (setelah itu `env()`
     * mengembalikan null), dan daftar yang boleh dipercaya itu beda per mesin —
     * `127.0.0.1` untuk Cloudflare Tunnel di mesin yang sama, rentang IP CDN untuk
     * server di belakang proxy. Default kosong = tidak ada yang dipercaya, dan itu
     * memang sikap aman: siapa pun yang bisa mengirim `X-Forwarded-For` tidak boleh
     * bisa memalsukan alamat IP.
     *
     * Yang rusak kalau daftar ini tidak diisi di belakang proxy: throttle login
     * memakai email+IP (`LoginRequest::throttleKey()`), sehingga lima salah ketik PIN
     * oleh satu orang mengunci seluruh kantor karena semua orang tampak ber-IP sama.
     *
     * @var array<int, string>|string|null
     */
    protected $proxies;

    /**
     * The headers that should be used to detect proxies.
     *
     * @var int
     */
    protected $headers =
        Request::HEADER_X_FORWARDED_FOR |
        Request::HEADER_X_FORWARDED_HOST |
        Request::HEADER_X_FORWARDED_PORT |
        Request::HEADER_X_FORWARDED_PROTO |
        Request::HEADER_X_FORWARDED_AWS_ELB;

    public function __construct()
    {
        $terpercaya = (array) config('security.trusted_proxies', []);

        // null (bukan []) saat daftar kosong: persis perilaku bawaan kerangka, jadi
        // memasang config yang tidak diisi tidak mengubah apa pun di request.
        $this->proxies = $terpercaya === [] ? null : $terpercaya;
    }
}
