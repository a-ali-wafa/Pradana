<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Masuk — {{ $instansi?->nama_instansi ?? 'PRADANA' }}</title>

    {{--
        auth/login.blade.php — dibuat sesuai AuthController::create() & LoginRequest
        (field: email, pin [digits:6], remember [boolean]).

        $instansi (baris tunggal `pengaturan_instansi`, dikirim AuthController::create()
        via PengaturanInstansi::first()) dipakai untuk nama & logo di panel kiri — BISA
        null (belum di-seed / tabel kosong), jadi semua akses pakai null-safe (?->) dengan
        fallback ke branding generik "PRADANA". Logo diambil dari disk `public` (C7,
        BUKAN Google Drive) lewat Storage::url() — sama seperti pola di
        pengaturan-instansi/edit.blade.php, butuh `php artisan storage:link` sudah jalan.

        SENGAJA standalone (bukan @extends('layouts.app')): layout itu dibangun untuk halaman
        yang SUDAH login (ada sidebar nav ke route yang butuh auth, blok @auth untuk info user +
        tombol keluar) — tidak relevan untuk halaman guest ini. Token desain (warna, font, CDN)
        DISALIN PERSIS dari layouts/app.blade.php supaya tetap satu identitas visual dengan
        halaman lain, cuma tata letaknya beda (split-screen, bukan sidebar+topbar).

        Error login salah (email ATAU pin salah) & rate-limit sama-sama dilempar LoginRequest ke
        key 'email' (lihat authenticate()/ensureIsNotRateLimited()) — makanya ditampilkan sebagai
        1 alert umum di atas form, terpisah dari error field 'pin' (required/format 6 digit) yang
        ditampilkan inline di bawah field-nya sendiri.

        Tidak ada link "lupa PIN": A4 [DEFAULT] di AGENTS.md bilang reset PIN itu manual oleh
        admin, bukan alur self-service — jadi cuma teks instruksi, bukan link ke route yang belum
        ada di web.php.
    --}}

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --primary: #ffffff;
            --secondary: #3b82f6;
            --accent: #14b8a6;
            --bg-color: #f3f6f9;
            --text-dark: #334155;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            background-color: var(--bg-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-dark);
        }

        .login-wrapper { min-height: 100vh; display: flex; }

        /* Panel kiri — identitas & konteks aplikasi */
        .login-brand {
            flex: 1 1 46%;
            background: linear-gradient(160deg, var(--secondary), #1d4ed8);
            color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 4rem 3.5rem;
            position: relative;
            overflow: hidden;
        }
        .login-brand::after {
            content: '';
            position: absolute;
            width: 420px; height: 420px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.07);
            right: -140px; bottom: -170px;
        }
        .login-brand .brand-icon,
        .login-brand .brand-logo {
            width: 58px; height: 58px;
            display: flex; align-items: center; justify-content: center;
            background: rgba(255, 255, 255, 0.15);
            border-radius: 14px;
            font-size: 1.4rem;
            margin-bottom: 1.75rem;
        }
        .login-brand .brand-logo { padding: 8px; }
        .login-brand .brand-logo img { max-width: 100%; max-height: 100%; object-fit: contain; }
        .login-brand h1 { font-size: 1.85rem; font-weight: 700; margin: 0 0 0.4rem; letter-spacing: 0.02em; }
        .login-brand .brand-tagline {
            font-size: 0.8rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.7);
            margin: 0 0 1rem;
        }
        .login-brand p { color: rgba(255, 255, 255, 0.85); max-width: 32ch; line-height: 1.6; font-size: 0.95rem; margin: 0; }
        .login-brand ul {
            list-style: none; padding: 0; margin: 2.25rem 0 0;
            display: flex; flex-direction: column; gap: 0.9rem;
            position: relative;
        }
        .login-brand ul li {
            display: flex; align-items: center; gap: 0.65rem;
            font-size: 0.87rem; color: rgba(255, 255, 255, 0.85);
        }
        .login-brand ul li i { color: var(--accent); }

        /* Panel kanan — form */
        .login-form-side {
            flex: 1 1 54%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1.5rem;
        }
        .login-card { width: 100%; max-width: 380px; }
        .login-card .card-eyebrow { color: var(--secondary); font-weight: 600; font-size: 0.85rem; margin-bottom: 0.35rem; }
        .login-card h2 { font-size: 1.5rem; font-weight: 700; margin: 0 0 0.4rem; }
        .login-card .subtitle { color: #64748b; font-size: 0.9rem; margin: 0 0 1.75rem; line-height: 1.5; }

        .form-label { font-weight: 600; font-size: 0.85rem; color: var(--text-dark); }
        .form-control {
            border-radius: 10px;
            border: 1px solid #e2e8f0;
            padding: 0.65rem 0.9rem;
        }
        .form-control:focus {
            border-color: var(--secondary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.15);
        }
        .input-group-text {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-right: none;
            color: #94a3b8;
            border-radius: 10px 0 0 10px;
        }
        .input-group .form-control { border-left: none; border-radius: 0 10px 10px 0; }
        .input-group .form-control:focus { z-index: 3; }

        .btn-login {
            background: var(--secondary);
            border: none;
            border-radius: 10px;
            padding: 0.7rem 1rem;
            font-weight: 600;
            width: 100%;
            color: #fff;
        }
        .btn-login:hover { background: #2563eb; color: #fff; }
        .btn-login:focus-visible { outline: 3px solid rgba(59, 130, 246, 0.35); outline-offset: 2px; }

        .form-check-input:checked { background-color: var(--secondary); border-color: var(--secondary); }

        @media (max-width: 860px) {
            .login-brand { display: none; }
            .login-form-side { flex: 1 1 100%; }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-brand">
            @if ($instansi?->logo_path)
                <div class="brand-logo">
                    <img src="{{ Storage::url($instansi->logo_path) }}" alt="Logo {{ $instansi->nama_instansi }}">
                </div>
            @else
                <div class="brand-icon"><i class="fas fa-archive"></i></div>
            @endif

            <h1>{{ $instansi?->nama_instansi ?? 'PRADANA' }}</h1>
            @if ($instansi?->nama_instansi)
                <p class="brand-tagline">Sistem Arsip Digital PRADANA</p>
            @endif
            <p>Arsip surat masuk &amp; keluar untuk kantor desa/kelurahan — satu tempat untuk klasifikasi, penomoran, dan penyimpanan naskah dinas.</p>
            <ul>
                <li><i class="fas fa-check-circle"></i> Klasifikasi arsip berjenjang</li>
                <li><i class="fas fa-check-circle"></i> Penomoran surat otomatis</li>
                <li><i class="fas fa-check-circle"></i> Lampiran tersimpan aman di Google Drive</li>
            </ul>
        </div>

        <div class="login-form-side">
            <div class="login-card">
                <div class="card-eyebrow">Selamat datang kembali</div>
                <h2>Masuk ke akun Anda</h2>
                <p class="subtitle">Gunakan email dan PIN yang terdaftar untuk mengakses PRADANA.</p>

                @error('email')
                    <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm" role="alert">
                        <i class="fas fa-exclamation-triangle me-1"></i> {{ $message }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
                    </div>
                @enderror

                <form method="POST" action="{{ route('login') }}" novalidate>
                    @csrf

                    <div class="mb-3">
                        <label for="email" class="form-label">Email</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                            <input
                                type="email"
                                class="form-control @error('email') is-invalid @enderror"
                                id="email"
                                name="email"
                                value="{{ old('email') }}"
                                placeholder="nama@instansi.go.id"
                                required
                                autofocus
                                autocomplete="username"
                            >
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="pin" class="form-label">PIN (6 digit)</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-lock"></i></span>
                            <input
                                type="password"
                                class="form-control @error('pin') is-invalid @enderror"
                                id="pin"
                                name="pin"
                                placeholder="••••••"
                                maxlength="6"
                                inputmode="numeric"
                                pattern="\d{6}"
                                autocomplete="current-password"
                                required
                            >
                        </div>
                        @error('pin')
                            <div class="text-danger small mt-1">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="form-check mb-4">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember">
                        <label class="form-check-label small" for="remember">Ingat saya di perangkat ini</label>
                    </div>

                    <button type="submit" class="btn btn-login">
                        <i class="fas fa-right-to-bracket me-1"></i> Masuk
                    </button>
                </form>

                <p class="text-center text-muted small mt-4 mb-0">
                    Lupa PIN? Hubungi admin instansi Anda untuk direset.
                </p>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
