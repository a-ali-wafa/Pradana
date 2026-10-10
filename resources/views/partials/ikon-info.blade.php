@php
    /*
     * Ikon info yang menjelaskan dirinya saat disorot (hover) atau difokusi.
     *
     * Dipakai 10 Okt 2026 atas permintaan user: teks bantuan yang selalu tampil
     * membuat layar yang padat jadi harus digulir sebelum isinya kelihatan.
     *
     * Bentuknya SENGAJA `title` + `data-bs-toggle="tooltip"`, bukan tooltip yang
     * dibangun JavaScript sendiri:
     *  - `title` adalah fallback nyata. Kalau Bootstrap JS tidak sempat dimuat
     *    (atau JavaScript dimatikan), browser tetap menampilkan pesannya saat
     *    disorot — penjelasan tidak pernah hilang total, sama prinsipnya dengan
     *    tombol Hapus yang tetap form POST sungguhan (lihat public/js/pradana-arsip.js).
     *  - `tabindex="0"` supaya penjelasan juga bisa dibuka dengan keyboard;
     *    informasi yang cuma muncul saat mouse bergerak bukan informasi yang
     *    bisa diandalkan petugas.
     *  - init tooltip ada di SATU tempat (public/js/pradana-arsip.js), jadi view
     *    tidak menambah <script> inline per halaman lagi.
     *
     * Parameter: $pesan (wajib).
     */
    $pesan = isset($pesan) ? trim((string) $pesan) : '';
@endphp

@if ($pesan !== '')
    <span class="pradana-info"
          tabindex="0"
          role="note"
          title="{{ $pesan }}"
          data-bs-toggle="tooltip"
          data-bs-placement="top"
          data-bs-html="false"
          aria-label="{{ $pesan }}">
        <i class="fas fa-circle-info" aria-hidden="true"></i>
    </span>
@endif
