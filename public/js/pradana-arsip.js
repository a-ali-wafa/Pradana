/**
 * pradana-arsip.js — satu tempat untuk konfirmasi aksi destruktif + blok yang
 * baru boleh tertutup kalau JavaScript hidup.
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

        // Form yang sudah dikonfirmasi sedang disubmit — jangan tanya dua kali.
        // Flag ini juga yang membuat dua klik cepat tetap menghasilkan SATU permintaan.
        if (form.dataset.pradanaDikonfirmasi === '1') {
            return;
        }

        event.preventDefault();

        tanya(form).then(function (setuju) {
            if (! setuju) {
                return;
            }

            form.dataset.pradanaDikonfirmasi = '1';

            // `requestSubmit()` duluan: dia menjalankan validasi HTML5 bawaan dan
            // mengirim lewat jalur biasa, jadi form yang punya `required`/`pattern`
            // (form user, form draf) tetap divalidasi browser. `submit()` hanya
            // dipakai sebagai fallback browser lama — dan aman dipanggil setelah
            // flag di atas karena listener ini keluar lebih dulu.
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', tutupBlokYangBisaDibuka);
    } else {
        tutupBlokYangBisaDibuka();
    }
})();
