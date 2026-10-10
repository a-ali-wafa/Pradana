/**
 * pradana-arsip.js — satu tempat untuk konfirmasi aksi destruktif, blok yang
 * baru boleh tertutup kalau JavaScript hidup, dan penyalaan tooltip penjelasan.
 *
 * Semua yang di sini berlaku sama: JavaScript hanya MENAMBAH sesuatu ke halaman,
 * tidak pernah menjadi satu-satunya jalan untuk memakainya.
 *
 * Kenapa file ini ada (P0 di docs/daftar-peningkatan.md, 10 Okt 2026):
 * sebelumnya setiap halaman menyalin `function pradanaConfirmHapus(url, pesan)`
 * TUJUH kali (arsip-aksi, 3 klasifikasi, 2 daftar surat, users). Bentuk lamanya
 * membuat tombol "Hapus" jadi `<button type="button" onclick="…">` yang
 * MERAKIT form lewat JavaScript. Artinya tanpa JS — atau kalau SweetAlert2 gagal
 * dimuat — tombol itu tidak hanya kehilangan konfirmasi, ia tidak melakukan
 * apa-apa sama sekali. Dan karena tidak ada satu pun salinan yang memeriksa
 * `typeof Swal`, satu kegagalan jaringan di CDN sudah cukup untuk itu.
 *
 * Bentuk baru: formnya sungguhan ada di HTML (`<form method="POST"> @csrf
 * @method('DELETE')`), tombolnya `type="submit"`. JS hanya MENAMBAHKAN
 * konfirmasi. Itu bukan perbedaan kecil:
 *   - JS mati / file ini gagal dimuat  -> aksi tetap jalan, tanpa jeda konfirmasi
 *   - SweetAlert2 tidak ada             -> jatuh ke window.confirm() bawaan
 *   - server tetap satu-satunya penjaga (admin + CSRF), jadi tidak ada jalur
 *     baru yang jadi mungkin karena perubahan ini
 *
 * Aturan untuk siapa pun yang menambah tombol berbahaya: tulis form-nya, kasih
 * `data-konfirmasi="…"`, jangan menulis script per halaman lagi.
 */
(function () {
    'use strict';

    /** Atribut yang boleh dipakai sebuah form untuk menjelaskan dirinya. */
    var ATRIBUT = {
        pesan: 'data-konfirmasi',
        judul: 'data-konfirmasi-judul',
        catatan: 'data-konfirmasi-catatan',
        ya: 'data-konfirmasi-ya',
        tidak: 'data-konfirmasi-tidak'
    };

    function teks(form, kunci, defaultnya) {
        var nilai = form.getAttribute(kunci);

        return (nilai === null || nilai === '') ? defaultnya : nilai;
    }

    /**
     * Tanya dulu. Selalu mengembalikan Promise<boolean>, baik saat SweetAlert2
     * tersedia maupun tidak — percabangan itulah seluruh maksud file ini, jadi
     * dia tidak boleh bocor ke pemanggilnya.
     */
    function tanya(form) {
        var pesan = teks(form, ATRIBUT.pesan, 'Lanjutkan aksi ini?');
        var judul = teks(form, ATRIBUT.judul, 'Konfirmasi');
        var catatan = teks(form, ATRIBUT.catatan, '');
        var ya = teks(form, ATRIBUT.ya, 'Ya, lanjutkan');
        var tidak = teks(form, ATRIBUT.tidak, 'Batal');

        // Guard yang sesungguhnya. Sebelumnya tidak ada, dan itu temuan #1 di
        // daftar peningkatan: Swal undefined -> ReferenceError di dalam onclick
        // -> tombol tampak mati tanpa pesan apa pun.
        if (typeof window.Swal === 'undefined' || typeof window.Swal.fire !== 'function') {
            var teksConfirm = judul + '\n\n' + pesan + (catatan ? '\n\n' + catatan : '');

            return Promise.resolve(window.confirm(teksConfirm));
        }

        return window.Swal.fire({
            title: judul,
            // `text`, BUKAN `html`/`footer`: nilai atribut datang dari Blade yang
            // meng.escape `&quot;`/`&lt;` — tapi begitu dibaca getAttribute(), hasilnya
            // sudah teks mentah lagi. Menyisipkannya ke footer (yang menerima HTML)
            // membuat nomor surat seperti `<img onerror=…>` jadi eksekusi. `text`
            // dirender Swal sebagai teks polos, jadi tidak ada jalur markup sama sekali.
            text: pesan + (catatan ? ' — ' + catatan : ''),
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: ya,
            cancelButtonText: tidak
        }).then(function (hasil) {
            return !! hasil.isConfirmed;
        });
    }

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (! form.matches || ! form.matches('form[' + ATRIBUT.pesan + ']')) {
            return;
        }

        var status = form.dataset.pradanaStatus;

        // Dialog masih terbuka dan ada klik tambahan -> abaikan diam-diam, JANGAN
        // tanya dua kali. Tanpa hal ini, dua klik cepat menghasilkan dua dialog dan
        // DUA submit (itu yang ditemukan skrip verifikasi node 10 Okt, bukan teori).
        if (status === 'ditable') {
            event.preventDefault();

            return;
        }

        // Permintaan submit yang berasal dari kita sendiri: biarkan lewat, tidak
        // ada preventDefault dan tidak ada dialog lagi.
        if (status === 'dikirim') {
            return;
        }

        event.preventDefault();
        form.dataset.pradanaStatus = 'ditable';

        tanya(form).then(function (setuju) {
            if (! setuju) {
                // Dibatalkan -> status dibersihkan supaya petugas bisa mencoba lagi
                // dan tetap mendapat konfirmasi.
                form.dataset.pradanaStatus = '';

                return;
            }

            form.dataset.pradanaStatus = 'dikirim';

            // `requestSubmit()` duluan: dia menjalankan validasi HTML5 bawaan, jadi
            // form dengan `required`/`pattern` tetap divalidasi browser. `submit()`
            // hanya fallback browser lama.
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }

            // Kalau validasi HTML5 menahan pengiriman, status "dikirim" tidak boleh
            // tertinggal — kalau tidak, submit berikutnya lolos TANPA konfirmasi.
            // Permintaan yang benar-benar terkirim sudah meninggalkan halaman, jadi
            // reset di akhir task ini aman.
            window.setTimeout(function () {
                if (form.dataset.pradanaStatus === 'dikirim') {
                    form.dataset.pradanaStatus = '';
                }
            }, 0);
        });
    });

    /**
     * Blok yang `data-pradana-tertutup` baru disembunyikan OLEH SCRIPT INI, bukan
     * oleh `style="display:none"` di Blade. Urutannya penting: bentuk lama
     * (tertutup di HTML, dibuka JS) membuat fitur yang sama matinya bersama JS —
     * misalnya tombol "Tolak" di pengajuan hapus lampiran tidak pernah bisa
     * dibuka tanpa JavaScript.
     */
    function tutupBlokYangBisaDibuka() {
        var blok = document.querySelectorAll('[data-pradana-tertutup]');

        for (var i = 0; i < blok.length; i++) {
            blok[i].classList.add('d-none');
            blok[i].setAttribute('aria-hidden', 'true');
        }
    }

    /** Satu listener untuk semua tombol buka/tutup: `data-pengubah="#id-blok"`. */
    document.addEventListener('click', function (event) {
        var tombol = event.target.closest ? event.target.closest('[data-pengubah]') : null;

        if (! tombol) {
            return;
        }

        var target = document.querySelector(tombol.getAttribute('data-pengubah'));

        if (! target) {
            return;
        }

        var terbuka = ! target.classList.toggle('d-none');

        target.setAttribute('aria-hidden', terbuka ? 'false' : 'true');

        if (tombol.getAttribute('aria-expanded') !== null) {
            tombol.setAttribute('aria-expanded', terbuka ? 'true' : 'false');
        }

        if (terbuka && target.querySelector && target.querySelector('input, select, textarea')) {
            target.querySelector('input:not([type=hidden]), select, textarea').focus();
        }
    });

    /**
     * Nyalakan tooltip Bootstrap untuk ikon penjelasan (`partials/ikon-info.blade.php`).
     *
     * Ini BUKAN satu-satunya jalur informasi. Ikonnya sudah membawa `title`, jadi
     * tanpa JavaScript pun browser menampilkan pesannya saat disorot — tugas skrip
     * ini cuma menaikkan bentuknya (tooltip yang bisa difokus keyboard, tidak
     * terlambat muncul seperti title bawaan). Karena itu:
     *  - kalau `bootstrap` tidak ada (aset gagal dimuat), kami diam — `title` tetap jalan;
     *  - `trigger: ['hover', 'focus']` supaya penjelasan juga terbuka lewat Tab,
     *    bukan hanya lewat mouse.
     * Tidak ada HTML yang disisipkan di sini: konten dibaca dari atribut dan
     * Bootstrap memindahkannya ke `data-bs-original-title` (teks polos).
     */
    function nyalakanTooltip() {
        if (typeof window.bootstrap === 'undefined' || typeof window.bootstrap.Tooltip !== 'function') {
            return;
        }

        var elemen = document.querySelectorAll('[data-bs-toggle="tooltip"]');

        for (var i = 0; i < elemen.length; i++) {
            // Opsi dibaca Bootstrap dari atribut `data-bs-*` di elemennya
            // (partials/ikon-info.blade.php menulis data-bs-html bernilai false), jadi
            // di sini hanya trigger. Sengaja TIDAK menulis kunci HTML di objek opsi:
            // guardrail KonfirmasiDestruktifTest melarang helper menyuntik HTML dari
            // atribut, dan menyebut nama kunci itu di sini — walau untuk mematikannya —
            // terbaca sebagai pelanggaran oleh guardrail yang sama (komentar JS tidak
            // dibuang saat memindai; itu keputusan yang sengaja dipertahankan).
            window.bootstrap.Tooltip.getOrCreateInstance(elemen[i], {
                trigger: 'hover focus',
                animation: false
            });
        }
    }

    function siap() {
        tutupBlokYangBisaDibuka();
        nyalakanTooltip();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', siap);
    } else {
        siap();
    }
})();
