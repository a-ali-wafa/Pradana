@php
    /*
     * Pemilih jenis arsip — Surat Masuk | Surat Keluar.
     *
     * 10 Okt 2026: tab ketiga ("Semua arsip") DIBUANG dari deretan tombol atas,
     * atas permintaan user — tiga tombol besar di kepala halaman terasa seperti
     * bagian terpisah dari aplikasi, padahal mode gabungan itu cuma bantuan
     * pencarian. Kemampuannya TIDAK hilang (L-15 tetap berlaku, tetap tanpa route
     * baru): dia pindah ke samping, hanya muncul saat ada kata kunci — justru di
     * situlah orang tidak ingat suratnya masuk atau keluar.
     *
     * TIDAK ada route baru di sini. Mode gabungan hanya menambah `?jenis=semua`
     * pada halaman ASAL ($routeAsal), dan controller halaman itulah yang memanggil
     * DaftarArsipGabungan. Alasannya sama seperti saat halaman /pencarian dihapus
     * 5 Okt 2026: satu kotak cari saja, jangan ada layar kedua.
     *
     * Dipakai dengan $aktif = 'masuk' | 'keluar' | 'semua' dan $routeAsal.
     * Kata kunci ikut dibawa saat berpindah jenis surat; filter lain sengaja
     * direset supaya tidak ada tombol yang diam-diam masih menyaring dari layar
     * sebelumnya.
     */
    $filter = array_filter(
        request()->only(['cari', 'sifat', 'klasifikasi_primer_id', 'status_arsip', 'tahun', 'usang']),
        fn ($v) => $v !== null && $v !== ''
    );
    $kata = $filter['cari'] ?? null;

    // Label jalan keluar harus menyebut daftar yang benar-benar akan ditampilkan.
    // `$aktif` TIDAK bisa dipakai untuk itu: di cabang ini nilainya selalu 'semua'
    // (bug yang dulu tersembunyi di balik tiga tombol tab).
    $asal = str_contains((string) $routeAsal, 'surat-keluar') ? 'Surat Keluar' : 'Surat Masuk';
@endphp

<div class="d-flex flex-wrap align-items-center gap-3 mb-3">
    <div class="btn-group" role="group" aria-label="Jenis arsip">
        <a href="{{ route('surat-masuk.index', array_filter(['cari' => $kata])) }}"
           class="btn btn-sm {{ $aktif === 'masuk' ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="fas fa-inbox me-1"></i> Surat Masuk
        </a>
        <a href="{{ route('surat-keluar.index', array_filter(['cari' => $kata])) }}"
           class="btn btn-sm {{ $aktif === 'keluar' ? 'btn-primary' : 'btn-outline-primary' }}">
            <i class="fas fa-paper-plane me-1"></i> Surat Keluar
        </a>
    </div>

    @if ($aktif === 'semua')
        {{-- Bar status: orang harus selalu tahu sedang melihat daftar gabungan,
             dan harus ada satu jalan keluar yang jelas. --}}
        <span class="badge text-bg-primary rounded-pill">
            <i class="fas fa-layer-group me-1"></i> Semua arsip (masuk + keluar)
        </span>
        <a href="{{ route($routeAsal, $filter) }}" class="small text-decoration-none">
            <i class="fas fa-times me-1"></i>Kembali ke {{ $asal }} saja
        </a>
    @elseif ($kata)
        <a href="{{ route($routeAsal, $filter + ['jenis' => 'semua']) }}"
           class="small text-decoration-none"
           title="Gabungkan surat masuk dan keluar dalam satu daftar, terbaru di atas">
            <i class="fas fa-layer-group me-1"></i>Cari <strong>{{ $kata }}</strong> di semua arsip
        </a>
    @endif
</div>
