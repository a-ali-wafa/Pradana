{{--
    Form unggah lampiran, dipakai halaman show surat masuk & keluar.
    Satu salinan untuk keduanya (keputusan H6: dulu dua file yang isinya
    mulai melenceng).

    Butuh variabel: $action (URL store), $suratId.

    Upload berjalan sinkron di server (L-02), tapi dikirim lewat AJAX supaya
    ada floating progress bar di pojok kanan bawah dan layar tidak membeku
    (permintaan eksplisit user). Tanpa JavaScript, form tetap submit biasa.
--}}
<hr class="text-secondary opacity-25 my-3">

<form method="POST" action="{{ $action }}" enctype="multipart/form-data"
      class="d-flex gap-2 align-items-end form-unggah-lampiran">
    @csrf
    <div class="flex-grow-1">
        <label class="form-label small fw-bold mb-1">Unggah Lampiran Baru</label>
        <input type="file" name="files[]" multiple required
               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx,.xls,.xlsx"
               class="form-control @error('files') is-invalid @enderror @error('files.*') is-invalid @enderror">
        @error('files') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        @error('files.*') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
        <div class="form-text">Maks 25MB per file, paling banyak 10 file sekaligus.</div>
    </div>
    <button type="submit" class="btn btn-outline-primary rounded-pill px-3" style="white-space:nowrap;">
        <i class="fas fa-upload me-1"></i> Unggah
    </button>
</form>

<div id="panel-unggah-lampiran" class="position-fixed shadow-lg"
     style="right:1rem; bottom:1rem; z-index:1080; width:20rem; max-width:calc(100vw - 2rem); display:none;">
    <div class="card">
        <div class="card-body py-2 px-3">
            <div class="d-flex justify-content-between align-items-center">
                <strong class="small" id="unggah-judul">Mengunggah…</strong>
                <button type="button" class="btn-close" id="unggah-tutup" aria-label="Tutup"></button>
            </div>
            <div class="progress mt-1" style="height:.4rem;">
                <div class="progress-bar progress-bar-striped progress-bar-animated" id="unggah-bar"
                     role="progressbar" aria-label="Progres unggah" style="width:0%"></div>
            </div>
            <div class="small text-secondary mt-1" id="unggah-keterangan"></div>
        </div>
    </div>
</div>

@once
@push('scripts')
<script>
(function () {
    const panel = document.getElementById('panel-unggah-lampiran');
    if (!panel) return;

    const bar = document.getElementById('unggah-bar');
    const judul = document.getElementById('unggah-judul');
    const ket = document.getElementById('unggah-keterangan');

    let berjalan = 0;

    function setel(persen, teksJudul, teksKeterangan) {
        bar.style.width = persen + '%';
        judul.textContent = teksJudul;
        ket.textContent = teksKeterangan || '';
        bar.classList.toggle('progress-bar-animated', persen < 100);
        bar.classList.toggle('bg-success', persen >= 100);
    }

    // Jangan tinggalkan halaman selagi masih ada unggahan berjalan.
    window.addEventListener('beforeunload', function (e) {
        if (berjalan > 0) {
            e.preventDefault();
            e.returnValue = '';
        }
    });

    function barisLampiran(item) {
        const daftar = document.getElementById('daftar-lampiran');
        if (!daftar) return;

        const placeholder = daftar.querySelector('[data-kosong]');
        if (placeholder) placeholder.remove();

        const ukuran = item.ukuran ? Math.round(item.ukuran / 1024) + ' KB' : '—';

        const row = document.createElement('div');
        row.className = 'lampiran-card';
        row.innerHTML = '<i class="fas fa-file text-primary fa-lg"></i>'
            + '<div class="flex-grow-1 min-w-0">'
            + '<div class="fw-semibold text-truncate"></div>'
            + '<div class="text-secondary small"></div></div>'
            + '<a class="btn btn-sm btn-outline-primary rounded-pill px-3" target="_blank" rel="noopener">Unduh</a>';

        // Nama file & angka diisi via textContent supaya tidak pernah jadi HTML.
        row.querySelector('.fw-semibold').textContent = item.nama_file;
        row.querySelector('.text-secondary').textContent = ukuran + ' · baru diunggah';
        const tautan = row.querySelector('a');
        tautan.href = item.unduh;

        daftar.append(row);
    }

    function unggahSatu(form, berkas, indeks, jumlah) {
        return new Promise(function (resolve, reject) {
            const data = new FormData();
            data.append('_token', form.querySelector('input[name="_token"]').value);
            data.append('files[]', berkas);

            const xhr = new XMLHttpRequest();
            xhr.open('POST', form.action);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.upload.onprogress = function (e) {
                if (!e.lengthComputable) return;
                const persenFile = Math.round((e.loaded / e.total) * 100);
                const persenTotal = Math.round(((indeks + persenFile / 100) / jumlah) * 100);
                setel(persenTotal, 'Mengunggah ' + (indeks + 1) + ' dari ' + jumlah + '…',
                    berkas.name + ' — ' + persenFile + '%');
            };

            xhr.onload = function () {
                let hasil = null;
                try { hasil = JSON.parse(xhr.responseText); } catch (err) { /* bukan JSON */ }

                if (xhr.status >= 200 && xhr.status < 300 && hasil) {
                    resolve(hasil);
                } else {
                    reject(new Error(hasil && hasil.message ? hasil.message : 'Gagal menyimpan (HTTP ' + xhr.status + ').'));
                }
            };

            xhr.onerror = function () { reject(new Error('Koneksi terputus saat mengirim berkas.')); };
            xhr.send(data);
        });
    }

    document.querySelectorAll('.form-unggah-lampiran').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            const input = form.querySelector('input[type="file"]');
            const berkas = input && input.files ? Array.from(input.files) : [];
            if (berkas.length === 0) return;

            e.preventDefault();
            panel.style.display = 'block';
            berjalan = berkas.length;
            setel(0, 'Mengunggah ' + berkas.length + ' berkas…', 'Jangan tutup halaman ini.');

            let indeks = 0;
            const lanjutan = function () {
                if (indeks >= berkas.length) return Promise.resolve();
                return unggahSatu(form, berkas[indeks], indeks, berkas.length)
                    .then(function (hasil) {
                        (hasil.lampiran || []).forEach(barisLampiran);
                        if (hasil.duplikat && hasil.duplikat.length) {
                            setel(Math.round(((indeks + 1) / berkas.length) * 100), 'Mengunggah…',
                                '"' + hasil.duplikat[0] + '" sudah ada di surat ini, dilewati.');
                        }
                    })
                    .then(function () { indeks++; berjalan--; return lanjutan(); });
            };

            lanjutan().then(function () {
                setel(100, 'Selesai', document.getElementById('daftar-lampiran') ? '' : 'Memuat ulang daftar…');
                setTimeout(function () {
                    panel.style.display = 'none';
                    if (!document.getElementById('daftar-lampiran')) window.location.reload();
                }, 1200);
            }).catch(function (err) {
                berjalan = 0;
                setel(100, 'Gagal mengunggah', err.message);
            });
        });
    });

    document.getElementById('unggah-tutup').addEventListener('click', function () {
        panel.style.display = 'none';
    });
})();
</script>
@endpush
@endonce
