@php
    /*
     * Tab jenis arsip — Surat Masuk | Surat Keluar | Semua arsip.
     *
     * TIDAK ada route baru di sini. Dua tab pertama menunjuk halaman yang memang
     * sudah ada; tab ketiga hanya menambah `?jenis=semua` pada halaman ASAL
     * ($routeAsal), dan controller halaman itulah yang memanggil
     * DaftarArsipGabungan. Alasannya sama seperti saat halaman /pencarian
     * dihapus 5 Okt 2026: satu kotak cari saja, jangan ada layar kedua.
     *
     * Dipakai dengan $aktif = 'masuk' | 'keluar' | 'semua' dan $routeAsal.
     * Kata kunci ikut dibawa saat berpindah jenis surat (orang sering tidak
     * ingat suratnya masuk atau keluar); filter lain sengaja direset supaya
     * tidak ada tab yang diam-diam masih menyaring dari layar sebelumnya.
     */
    $filter = array_filter(
        request()->only(['cari', 'sifat', 'klasifikasi_primer_id', 'status_arsip', 'tahun', 'usang']),
        fn ($v) => $v !== null && $v !== ''
    );
    $kata = $filter['cari'] ?? null;
@endphp

<div class="btn-group mb-3 w-100" role="group" aria-label="Jenis arsip">
    <a href="{{ route('surat-masuk.index', array_filter(['cari' => $kata])) }}"
       class="btn btn-sm {{ $aktif === 'masuk' ? 'btn-primary' : 'btn-outline-primary' }}">
        <i class="fas fa-inbox me-1"></i> Surat Masuk
    </a>
    <a href="{{ route('surat-keluar.index', array_filter(['cari' => $kata])) }}"
       class="btn btn-sm {{ $aktif === 'keluar' ? 'btn-primary' : 'btn-outline-primary' }}">
        <i class="fas fa-paper-plane me-1"></i> Surat Keluar
    </a>
    <a href="{{ route($routeAsal, $filter + ['jenis' => 'semua']) }}"
       class="btn btn-sm {{ $aktif === 'semua' ? 'btn-primary' : 'btn-outline-primary' }}">
        <i class="fas fa-layer-group me-1"></i> Semua arsip
    </a>
</div>

@if($aktif === 'semua')
    <p class="text-secondary small mb-3">
        Daftar gabungan: surat masuk dan keluar dalam satu urutan, terbaru di atas
        (kalau ada kata kunci, nomor surat yang paling cocok didahulukan).
        Aksi per surat tetap lewat halaman detailnya masing-masing.
    </p>
@endif
