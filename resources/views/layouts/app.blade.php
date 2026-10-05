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
                {{-- Link "Pencarian Arsip" dihapus 5 Okt 2026: tiap daftar surat sudah
                     punya kotak pencarian sendiri (dan sekarang menggali isi surat juga). --}}
                {{-- Link di bawah ini disaring per role (L-07, 4 Okt 2026).
                    Route-nya memang sudah dipagari middleware `admin`, tapi tanpa
                    filter ini staf non-admin melihat menu yang isinya 403 semua. --}}
                @if (auth()->user()->isAdmin())
                    <a class="nav-link {{ request()->routeIs('pengajuan-hapus-lampiran.*') ? 'active' : '' }}" href="{{ route('pengajuan-hapus-lampiran.index') }}">
                        <i class="fas fa-trash-alt fa-fw"></i> Pengajuan Hapus Lampiran
                        @if(($antrianHapusLampiran ?? 0) > 0)
                            <span class="badge text-bg-warning ms-auto">{{ $antrianHapusLampiran }} menunggu</span>
                        @endif
                    </a>
                @endif
                <a class="nav-link {{ request()->routeIs('pemusnahan-arsip.*') ? 'active' : '' }}" href="{{ route('pemusnahan-arsip.index') }}">
                    <i class="fas fa-fire fa-fw"></i> Pemusnahan Arsip
                    @if (($antrianPemusnahan ?? 0) > 0 && auth()->user()->isAdmin())
                        <span class="badge text-bg-warning ms-auto">{{ $antrianPemusnahan }} menunggu</span>
                    @endif
                </a>
                <a class="nav-link {{ request()->routeIs('laporan.*') ? 'active' : '' }}" href="{{ route('laporan.index') }}">
                    <i class="fas fa-file-alt fa-fw"></i> Laporan &amp; Agenda
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
                @if (auth()->user()->isAdmin())
                    <a class="nav-link {{ request()->routeIs('aktivitas.*') ? 'active' : '' }}" href="{{ route('aktivitas.index') }}">
                        <i class="fas fa-history fa-fw"></i> Log Aktivitas
                    </a>
                    <a class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" href="{{ route('users.index') }}">
                        <i class="fas fa-users-cog fa-fw"></i> Manajemen User
                    </a>
                    <a class="nav-link {{ request()->routeIs('pengaturan-instansi.*') ? 'active' : '' }}" href="{{ route('pengaturan-instansi.edit') }}">
                        <i class="fas fa-building fa-fw"></i> Pengaturan Instansi
                    </a>
                @endif
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
                        {{-- "Akun" = satu tempat untuk hal-hal milik user yang login. Ganti PIN
                            dibuka dari sini sebagai popup, bukan sebagai halaman sendiri (4 Okt 2026). --}}
                        <div class="dropdown">
                            <button type="button"
                                    class="btn btn-light btn-sm rounded-pill px-3 d-flex align-items-center gap-2 border"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fas fa-user-circle fa-lg text-secondary"></i>
                                <span class="text-start d-none d-sm-block">
                                    <span class="fw-bold d-block" style="color: var(--text-dark); line-height: 1.15">{{ Auth::user()->nama_lengkap }}</span>
                                    <span class="small text-secondary d-block" style="line-height: 1.15">{{ Auth::user()->labelRole() }}</span>
                                </span>
                                <i class="fas fa-caret-down text-secondary"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                                <li class="px-3 py-2">
                                    <div class="small fw-bold text-dark">{{ Auth::user()->nama_lengkap }}</div>
                                    <div class="small text-secondary">{{ Auth::user()->email }}</div>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                <li>
                                    <button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#modalGantiPin">
                                        <i class="fas fa-key me-2 text-secondary"></i> Ganti PIN
                                    </button>
                                </li>
                            </ul>
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

    @auth
        {{-- Popup "Ganti PIN" (L-12/A5=a), dipanggil dari menu Akun di topbar.
            4 Okt 2026: sebelumnya halaman sendiri di /profil/pin — atas permintaan
            user, aksi milik akun dikumpulkan di menu Akun dan ditampilkan sebagai
            popup. Formulirnya tetap <form> POST sungguhan: kalau JavaScript mati,
            tombol Simpan masih bekerja biasa dan controller membalas redirect+flash. --}}
        <div class="modal fade" id="modalGantiPin" tabindex="-1" aria-labelledby="modalGantiPinJudul" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form id="formGantiPin" method="POST" action="{{ route('profil.pin.update') }}" autocomplete="off">
                        @csrf
                        @method('PATCH')
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalGantiPinJudul">
                                <i class="fas fa-key me-2 text-primary"></i>Ganti PIN
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                        </div>
                        <div class="modal-body">
                            <div id="pinHasil" class="alert small d-none" role="alert"></div>

                            <p class="text-secondary small">
                                Login memakai <strong>{{ Auth::user()->email }}</strong>. PIN baru harus
                                <strong>8 digit angka</strong> dan langsung berlaku — catat dan simpan baik-baik,
                                PIN tidak bisa dilihat ulang setelah disimpan.
                            </p>

                            <div class="mb-3">
                                <label for="pin_lama" class="form-label small fw-bold">PIN Lama <span class="text-danger">*</span></label>
                                <input type="password" class="form-control font-monospace" id="pin_lama" name="pin_lama"
                                       maxlength="8" pattern="\d{8}" inputmode="numeric" autocomplete="current-password" required>
                                <div class="invalid-feedback" id="salah-pin_lama"></div>
                            </div>

                            <div class="mb-3">
                                <label for="pin" class="form-label small fw-bold">PIN Baru <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <input type="password" class="form-control font-monospace" id="pin" name="pin"
                                           maxlength="8" pattern="\d{8}" inputmode="numeric" autocomplete="new-password" required>
                                    <button class="btn btn-outline-secondary" type="button" data-tampilan-pin="pin" aria-label="Tampilkan PIN">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                                <div class="invalid-feedback" id="salah-pin"></div>
                            </div>

                            <div>
                                <label for="pin_confirmation" class="form-label small fw-bold">Ulangi PIN Baru <span class="text-danger">*</span></label>
                                <input type="password" class="form-control font-monospace" id="pin_confirmation" name="pin_confirmation"
                                       maxlength="8" pattern="\d{8}" inputmode="numeric" autocomplete="new-password" required>
                                <div class="invalid-feedback" id="salah-pin_confirmation"></div>
                            </div>

                            <div class="alert alert-light border small mt-3 mb-0">
                                <i class="fas fa-info-circle me-1"></i>
                                Lupa PIN lama? Popup ini tetap membutuhkannya. Kalau benar-benar lupa,
                                minta admin mengresetnya di menu <strong>Manajemen User</strong>.
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
                            <button type="submit" class="btn btn-primary rounded-pill px-4" id="pinSimpan">
                                <i class="fas fa-save me-1"></i> Simpan PIN Baru
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    @endauth

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    @auth
    <script>
    (function () {
        const form = document.getElementById('formGantiPin');
        if (! form) return;

        const modal = document.getElementById('modalGantiPin');
        const hasil = document.getElementById('pinHasil');
        const tombol = document.getElementById('pinSimpan');
        const kolom = ['pin_lama', 'pin', 'pin_confirmation'];

        function bersihkan() {
            hasil.className = 'alert small d-none';
            hasil.textContent = '';
            kolom.forEach(function (nama) {
                const input = document.getElementById(nama);
                input.classList.remove('is-invalid');
                input.value = '';
                document.getElementById('salah-' + nama).textContent = '';
            });
            tombol.disabled = false;
            tombol.innerHTML = '<i class="fas fa-save me-1"></i> Simpan PIN Baru';
        }

        function tampilkan(tipe, teks) {
            hasil.className = 'alert small ' + (tipe === 'ok' ? 'alert-success' : 'alert-danger');
            hasil.textContent = teks;
        }

        function salah(errors) {
            kolom.forEach(function (nama) {
                if (! errors[nama]) return;
                document.getElementById(nama).classList.add('is-invalid');
                document.getElementById('salah-' + nama).textContent = errors[nama][0];
            });
        }

        modal.addEventListener('shown.bs.modal', function () {
            bersihkan();
            document.getElementById('pin_lama').focus();
        });

        // Toggle terlihat/tersembunyi — PIN-nya milik orang yang sedang login,
        // jadi dia boleh membacanya sendiri sebelum menyimpan.
        form.querySelectorAll('[data-tampilan-pin]').forEach(function (tombol) {
            tombol.addEventListener('click', function () {
                const input = document.getElementById(tombol.dataset.tampilanPin);
                const menampilkan = input.type === 'password';
                input.type = menampilkan ? 'text' : 'password';
                tombol.querySelector('i').className = 'fas fa-eye' + (menampilkan ? '-slash' : '');
            });
        });

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            tombol.disabled = true;
            tombol.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span> Menyimpan…';

            try {
                const respons = await fetch(form.action, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': form.querySelector('input[name="_token"]').value,
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        pin_lama: form.pin_lama.value,
                        pin: form.pin.value,
                        pin_confirmation: form.pin_confirmation.value,
                    }),
                });

                if (respons.ok) {
                    const data = await respons.json();
                    tampilkan('ok', data.status || 'PIN Anda berhasil diganti.');
                    kolom.forEach(function (nama) { document.getElementById(nama).value = ''; });
                    return;
                }

                if (respons.status === 419) {
                    tampilkan('gagal', 'Halaman sudah kedaluwarsa. Muat ulang halaman, lalu buka menu Akun lagi.');

                    return;
                }

                const data = await respons.json();
                if (data.errors) {
                    salah(data.errors);
                } else {
                    tampilkan('gagal', data.message || 'PIN tidak bisa diganti.');
                }
            } catch (err) {
                tampilkan('gagal', 'Koneksi terputus — PIN belum diganti.');
            } finally {
                tombol.disabled = false;
                tombol.innerHTML = '<i class="fas fa-save me-1"></i> Simpan PIN Baru';
            }
        });
    })();
    </script>
    @endauth
    @stack('scripts')
</body>
</html>
