{{--
    Layout khusus halaman error (K16, 4 Okt 2026).

    SENGAJA berdiri sendiri, TIDAK @extends('layouts.app'):
    1. layouts.app memanggil auth()->user()->isAdmin() dan menembak query
       pengajuan yang menunggu — di halaman error itu bisa melempar exception
       kedua (mis. DB mati = penyebab 500-nya), dan user malah lihat layar kosong.
    2. Halaman error harus jalan untuk tamu maupun yang login, tanpa tahu
       siapa yang login. Tautannya URL tetap; /dashboard sendiri sudah
       melempar tamu ke /login.

    Tidak ada satu pun variabel dari $exception yang dicetak: pesan teknis
    (nama tabel, path file) tidak berguna untuk staf dan justru membocorkan
    isi sistem.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    {{-- Sekadar alamat salah tidak boleh ikut ter-index. Header X-Robots-Tag
         (TandaiArsipPrivat) sudah menutupi kasus ini; meta di bawah adalah
         lapisan keduanya. --}}
    <meta name="robots" content="noindex, nofollow, noarchive">
    <title>@yield('title', 'Ada yang kurang beres - PRADANA')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f3f6f9; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; color: #334155; }
        .error-kartu { max-width: 640px; margin: 8vh auto 0; }
        .error-kode { font-size: 4rem; font-weight: 800; color: #3b82f6; line-height: 1; }
        .error-ikon { font-size: 2.4rem; color: #94a3b8; }
        .error-tindakan { text-align: left; }
        .error-tindakan li { margin-bottom: .35rem; }
    </style>
</head>
<body>
<div class="error-kartu">
    <div class="card border-0 shadow-sm">
        <div class="card-body text-center p-5">
            <div class="error-kode mb-1">@yield('kode')</div>
            <div class="error-ikon mb-3"><i class="fas @yield('ikon', 'fa-triangle-exclamation')"></i></div>
            <h1 class="h4 fw-bold mb-2">@yield('judul')</h1>
            <p class="text-secondary mb-4">@yield('pesan')</p>

            @hasSection('tindakan')
                <div class="alert alert-light border error-tindakan small mb-4">
                    <strong class="d-block mb-1">Yang perlu dilakukan:</strong>
                    @yield('tindakan')
                </div>
            @endif

            <div class="d-flex flex-wrap gap-2 justify-content-center">
                @yield('tautan')
            </div>

            <p class="text-secondary small mt-4 mb-0">PRADANA — Arsip Surat Digital</p>
        </div>
    </div>
</div>
</body>
</html>
