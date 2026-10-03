{{--
    Klasifikasi berjenjang primer -> sekunder -> tersier di form surat.
    Dipakai 4 halaman: surat-masuk/{create,edit} dan surat-keluar/{create,edit}.

    Kenapa parsial (H6, 4 Okt 2026): sebelumnya ada DUA salinan file ini, satu di
    folder surat-masuk dan satu di surat-keluar, isinya sudah melenceng (komentar
    beda, spasi beda, dan hanya tinggal waktu yang menentukan siapa yang lupa
    disinkronkan saat perilakunya diubah). Sekarang satu file untuk keduanya.

    Variabel yang harus di-pass lewat @include:
        $oldPrimerId, $oldSekId, $oldTerId — nilai old() (form error) atau ID
        tersimpan (halaman edit); boleh null.

    Data hierarki TIDAK di-fetch ke sini: tiap <option> di #klasifikasi_primer_id
    sudah membawa JSON sekunder+tersiernya di atribut `data-sekunder` (diisi
    controller), jadi cascade jalan tanpa request tambahan.
--}}
<script>
(function () {
    const elPrimer = document.getElementById('klasifikasi_primer_id');
    const elSekunder = document.getElementById('klasifikasi_sekunder_id');
    const elTersier = document.getElementById('klasifikasi_tersier_id');

    if (! elPrimer || ! elSekunder || ! elTersier) return;

    const oldSekId = {{ $oldSekId ? (int) $oldSekId : 'null' }};
    const oldTerId = {{ $oldTerId ? (int) $oldTerId : 'null' }};

    function bangunPilihan(el, daftar, terpilih, labelKosong) {
        el.innerHTML = '<option value="">' + labelKosong + '</option>';

        daftar.forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item.id;
            // textContent, bukan innerHTML: nama klasifikasi boleh berisi
            // karakter yang tidak boleh ditafsirkan sebagai HTML.
            opt.textContent = item.kode + ' \u2013 ' + item.nama;
            if (item.tersier) opt.dataset.tersier = JSON.stringify(item.tersier);
            if (terpilih && parseInt(item.id) === parseInt(terpilih)) opt.selected = true;
            el.appendChild(opt);
        });
    }

    function saatPrimerBerubah(pulihkanSekId, pulihkanTerId) {
        const dipilih = elPrimer.options[elPrimer.selectedIndex];
        const sekunder = dipilih && dipilih.dataset.sekunder
            ? JSON.parse(dipilih.dataset.sekunder)
            : [];

        bangunPilihan(elSekunder, sekunder, pulihkanSekId, '-- Pilih Sekunder (opsional) --');
        bangunPilihan(elTersier, [], null, '-- Pilih Tersier (opsional) --');

        if (pulihkanSekId) saatSekunderBerubah(pulihkanTerId);
    }

    function saatSekunderBerubah(pulihkanTerId) {
        const dipilih = elSekunder.options[elSekunder.selectedIndex];
        const tersier = dipilih && dipilih.dataset.tersier
            ? JSON.parse(dipilih.dataset.tersier)
            : [];

        bangunPilihan(elTersier, tersier, pulihkanTerId, '-- Pilih Tersier (opsional) --');
    }

    elPrimer.addEventListener('change', function () { saatPrimerBerubah(null, null); });
    elSekunder.addEventListener('change', function () { saatSekunderBerubah(null); });

    // Halaman edit & kembali-nya form setelah error: pilihan tersimpan harus
    // muncul lagi, termasuk daftar sekunder/tersier yang dibangun dari sana.
    if (elPrimer.value) {
        saatPrimerBerubah(oldSekId, oldTerId);
    }
})();
</script>
