{{--
    Partial: _klasifikasi_cascade_js.blade.php (Surat Keluar)
    Variabel yang harus di-pass saat @include:
        $oldPrimerId  — old() / existing primer ID
        $oldSekId     — old() / existing sekunder ID
        $oldTerId     — old() / existing tersier ID
--}}
<script>
(function () {
    const elPrimer   = document.getElementById('klasifikasi_primer_id');
    const elSekunder = document.getElementById('klasifikasi_sekunder_id');
    const elTersier  = document.getElementById('klasifikasi_tersier_id');

    const oldSekId = {{ $oldSekId ? (int)$oldSekId : 'null' }};
    const oldTerId = {{ $oldTerId ? (int)$oldTerId : 'null' }};

    function buildOptions(el, items, selectedId, emptyLabel) {
        el.innerHTML = `<option value="">${emptyLabel}</option>`;
        items.forEach(function (item) {
            const opt = document.createElement('option');
            opt.value = item.id;
            opt.textContent = item.kode + ' \u2013 ' + item.nama;
            if (item.tersier) opt.dataset.tersier = JSON.stringify(item.tersier);
            if (selectedId && parseInt(item.id) === parseInt(selectedId)) opt.selected = true;
            el.appendChild(opt);
        });
    }

    function onPrimerChange(restoreSekId, restoreTerId) {
        const selected = elPrimer.options[elPrimer.selectedIndex];
        const sekunderData = selected && selected.dataset.sekunder
            ? JSON.parse(selected.dataset.sekunder) : [];
        buildOptions(elSekunder, sekunderData, restoreSekId, '-- Pilih Sekunder (opsional) --');
        buildOptions(elTersier, [], null, '-- Pilih Tersier (opsional) --');
        if (restoreSekId) onSekunderChange(restoreTerId);
    }

    function onSekunderChange(restoreTerId) {
        const selected = elSekunder.options[elSekunder.selectedIndex];
        const tersierData = selected && selected.dataset.tersier
            ? JSON.parse(selected.dataset.tersier) : [];
        buildOptions(elTersier, tersierData, restoreTerId, '-- Pilih Tersier (opsional) --');
    }

    elPrimer.addEventListener('change', function () { onPrimerChange(null, null); });
    elSekunder.addEventListener('change', function () { onSekunderChange(null); });

    // Restore state (edit / validation error)
    if (elPrimer.value) {
        onPrimerChange(oldSekId, oldTerId);
    }
})();
</script>
