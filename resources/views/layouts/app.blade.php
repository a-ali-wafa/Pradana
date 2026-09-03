<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'PRADANA')</title>

    {{--
        Layout dasar PRADANA — dibuat 31 Agu 2026, DIROMBAK ULANG di hari yang sama
        setelah user kasih preview HTML sistem LAMA (Google Apps Script). Sebelum ini,
        AGENTS.md berkali-kali bilang "tidak ada Code.gs yang bisa dicek" (12.16 poin 1,
        LOCKED #15) — sekarang minimal ada referensi FRONTEND-nya, jadi desain di bawah
        ini SENGAJA MENIRU gaya visual file itu (bukan reka-reka bebas seperti versi
        pertama layout ini), sesuai G1 "replikasi tampilan lama".

        ⚠️ PENTING: yang diupload user itu preview HTML/JS SISI KLIEN sistem lama, BUKAN
        `Code.gs` (backend Apps Script asli). Jadi yang bisa diambil cuma: (1) desain
        visual (warna/tipografi/layout kartu — diambil persis dari sana), dan (2) pola
        FORMAT nomor surat yang kebaca dari JS-nya (lihat AGENTS.md 12.19 & D1 di
        Bagian 10 — TIDAK otomatis jadi final, D1 tetap butuh keputusan user). Logika
        BACKEND asli (pembuatan folder Drive, generate PDF, dst) TETAP tidak bisa dicek
        dari file ini — jangan disamakan dua hal itu.

        Token desain (disalin persis dari file referensi):
        --primary: #ffffff (dipakai sbg bg sidebar/topbar)  --secondary: #3b82f6 (biru aktif)
        --accent: #14b8a6 (belum dipakai luas di referensi)  --bg-color: #f3f6f9 (bg halaman)
        --text-dark: #334155  Font: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif
        Ikon: FontAwesome 6.4.0 (BUKAN Bootstrap Icons — ganti dari draft pertama layout
        ini supaya konsisten dgn referensi). SweetAlert2 ikut dimuat (belum dipakai di
        view Pengaturan Instansi, disiapkan utk view lain yg butuh dialog konfirmasi
        gaya sama seperti referensi, mis. hapus Klasifikasi/approve Pengajuan Hapus).

        Perbedaan SENGAJA dari referensi (bukan salah copy, ini keputusan adaptasi):
        - Referensi itu 1 file HTML SPA (ganti-ganti div lewat JS `switchMenu()`), CATATAN:
          project ini sekarang Laravel multi-halaman (server-rendered Blade per route),
          jadi navigasi sidebar di bawah pakai <a href> ke route asli, BUKAN JS switching.
        - Nav item HANYA yang route-nya SUDAH ada di `web.php` sampai sesi ini. Beberapa
          menu di referensi (mis. "Buat Surat (PDF)", "Log Aktivitas", "Daftar Seluruh
          Surat" gabungan Masuk+Keluar) belum ada controller/route-nya sama sekali di
          project Laravel — SENGAJA TIDAK ditaruh linknya dulu supaya tidak 404.
          Klasifikasi juga TETAP 3 link terpisah (Primer/Sekunder/Tersier), BUKAN 1
          halaman gabungan seperti referensi — sesuai LOCKED #1 (3 tabel berjenjang).
        - Nav ditampilkan SAMA ke SEMUA role yang login (tanpa filter) — B1 (matriks
          permission) masih [WAJIB TANYA USER], sama seperti versi layout sebelumnya.
    --}}

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --primary: #ffffff;
            --secondary: #3b82f6;
            --accent: #14b8a6;
            --bg-color: #f3f6f9;
            --text-dark: #334155;
        }
        body {
            background-color: var(--bg-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: var(--text-dark);
        }
        #app-wrapper { display: flex; min-height: 100vh; }

        .sidebar {
            width: 260px;
            flex-shrink: 0;
            background: var(--primary);
            color: var(--text-dark);
            min-height: 100vh;
            border-right: 1px solid #e2e8f0;
            box-shadow: 2px 0 10px rgba(0, 0, 0, 0.03);
        }
        .sidebar-header {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid #e2e8f0;
            color: var(--secondary);
        }
        .sidebar .nav-link {
            color: #64748b;
            padding: 12px 20px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 500;
            border-radius: 0;
        }
        .sidebar .nav-link:hover { color: var(--secondary); background: #f0f7ff; }
        .sidebar .nav-link.active {
            color: var(--secondary);
            background: #eff6ff;
            border-left: 4px solid var(--secondary);
            font-weight: bold;
        }
        .sidebar .nav-section-label {
            margin: 1.5rem 0 0.5rem 1.25rem;
            text-transform: uppercase;
            font-size: 0.72rem;
            letter-spacing: 0.05em;
            color: #94a3b8;
            font-weight: bold;
        }

        .main-content { flex: 1; display: flex; flex-direction: column; min-width: 0; }
        .topbar {
            background: white;
            padding: 15px 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.02);
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
        }
        .content-area { padding: 25px; flex: 1; }

        .card { border: none; border-radius: 12px; box-shadow: 0 4px 15px rgba(0, 0, 0, 0.03); margin-bottom: 20px; background: white; }
        .card-header { background: white; border-bottom: 1px solid #f1f5f9; font-weight: bold; border-radius: 12px 12px 0 0 !important; color: var(--text-dark); }

        .pradana-footer { text-align: center; padding: 1rem; border-top: 1px solid #e2e8f0; background: white; color: #94a3b8; font-size: 0.85rem; }

        @media (max-width: 768px) {
            .sidebar { position: fixed; left: -260px; top: 0; z-index: 1040; transition: 0.3s; }
            .sidebar.show { left: 0; }
            #btnToggleSidebar { display: inline-block !important; }
        }
    </style>

    @stack('styles')
</head>
<body>
    <div id="app-wrapper">
        {{-- Sidebar --}}
        <nav class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <i class="fas fa-archive fa-2x mb-2"></i>
                <h5 class="mb-0 fw-bold">PRADANA</h5>
            </div>

            <div class="nav flex-column mt-2">
                <a class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" href="{{ route('dashboard') }}">
                    <i class="fas fa-chart-pie fa-fw"></i> Dashboard
                </a>
                <a class="nav-link {{ request()->routeIs('surat-masuk.*') ? 'active' : '' }}" href="{{ route('surat-masuk.index') }}">
                    <i class="fas fa-inbox fa-fw"></i> Surat Masuk
                </a>
                <a class="nav-link {{ request()->routeIs('surat-keluar.*') ? 'active' : '' }}" href="{{ route('surat-keluar.index') }}">
                    <i class="fas fa-paper-plane fa-fw"></i> Surat Keluar
                </a>
                <a class="nav-link {{ request()->routeIs('pencarian.*') ? 'active' : '' }}" href="{{ route('pencarian.index') }}">
                    <i class="fas fa-search fa-fw"></i> Pencarian Arsip
                </a>
                <a class="nav-link {{ request()->routeIs('pengajuan-hapus-lampiran.*') ? 'active' : '' }}" href="{{ route('pengajuan-hapus-lampiran.index') }}">
                    <i class="fas fa-trash-alt fa-fw"></i> Pengajuan Hapus Lampiran
                </a>

                <div class="nav-section-label">Administrator</div>
                <a class="nav-link {{ request()->routeIs('klasifikasi-primer.*') ? 'active' : '' }}" href="{{ route('klasifikasi-primer.index') }}">
                    <i class="fas fa-tags fa-fw"></i> Klasifikasi Primer
                </a>
                <a class="nav-link {{ request()->routeIs('klasifikasi-sekunder.*') ? 'active' : '' }}" href="{{ route('klasifikasi-sekunder.index') }}">
                    <i class="fas fa-tags fa-fw"></i> Klasifikasi Sekunder
                </a>
                <a class="nav-link {{ request()->routeIs('klasifikasi-tersier.*') ? 'active' : '' }}" href="{{ route('klasifikasi-tersier.index') }}">
                    <i class="fas fa-tags fa-fw"></i> Klasifikasi Tersier
                </a>
                <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                    <i class="fas fa-users-cog fa-fw"></i> Manajemen User
                </a>
                <a class="nav-link {{ request()->routeIs('pengaturan-instansi.*') ? 'active' : '' }}" href="{{ route('pengaturan-instansi.edit') }}">
                    <i class="fas fa-building fa-fw"></i> Pengaturan Instansi
                </a>
            </div>
        </nav>

        {{-- Konten utama --}}
        <div class="main-content">
            <div class="topbar">
                <button class="btn btn-light d-none" id="btnToggleSidebar" onclick="document.getElementById('sidebar').classList.toggle('show')">
                    <i class="fas fa-bars"></i>
                </button>

                <h5 class="mb-0">@yield('page-title', 'PRADANA')</h5>

                <div class="d-flex align-items-center gap-3">
                    @auth
                        <div class="text-end d-none d-md-block">
                            <div class="fw-bold" style="color: var(--text-dark);">{{ Auth::user()->nama_lengkap }}</div>
                            <div class="small text-secondary text-uppercase">{{ Auth::user()->role }}</div>
                        </div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill px-3">
                                <i class="fas fa-sign-out-alt"></i> Keluar
                            </button>
                        </form>
                    @endauth
                </div>
            </div>

            <div class="content-area">
                @if (session('success'))
                    <div class="alert alert-success alert-dismissible fade show rounded-3 border-0 shadow-sm" role="alert">
                        <i class="fas fa-check-circle me-1"></i> {{ session('success') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @if (session('error'))
                    <div class="alert alert-danger alert-dismissible fade show rounded-3 border-0 shadow-sm" role="alert">
                        <i class="fas fa-exclamation-triangle me-1"></i> {{ session('error') }}
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                @endif

                @yield('content')
            </div>

            <footer class="pradana-footer">
                PRADANA Arsip Digital &copy; {{ date('Y') }}
            </footer>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @stack('scripts')
</body>
</html>
