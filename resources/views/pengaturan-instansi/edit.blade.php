@extends('layouts.app')

@section('title', 'Pengaturan Instansi - PRADANA')
@section('page-title', 'Pengaturan Instansi')

{{--
    View Pengaturan Instansi — dibuat 31 Agu 2026, dirombak ulang 5 Okt 2026.

    Tiga alasan dirombak:
    1. Logo tidak pernah tampil. `Storage::url()` menunjuk `public/storage/...` yang
       bergantung pada symlink `artisan storage:link`; symlink itu belum ada di laptop
       dev dan tidak tentu bisa dibuat di shared hosting kantor (K1=a). Sekarang lewat
       route `instansi.logo` — `$pengaturanInstansi->logoUrl()`.
    2. Halaman sempit dan kosong (satu kolom di tengah). Sekarang dua kolom: kiri data
       kop, kanan PRA-TINJAU kop yang meniru partials/kop-pdf.blade.php, jadi user
       melihat hasil sebelum menyimpan.
    3. Kop desa bertingkat (Kabupaten > Kecamatan > Desa) belum bisa ditulis sama
       sekali; kolom `nama_kabupaten`/`nama_kecamatan`/`kode_pos` ditambahkan lewat
       migration 5 Okt 2026.

    Pola interaksi "field readonly, klik Edit baru bisa isi" SENGAJA dipertahankan
    (meniru `handleEditHeader()` sistem lama) supaya kop surat tidak kepencet berubah.
    Penyimpanan tetap <form> POST+PUT standar Laravel, bukan AJAX — lihat
    `UpdatePengaturanInstansiRequest` dan AGENTS.md 12.18.
--}}

@section('content')
<div class="row g-4">
    <div class="col-12 col-xl-7">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                <span class="fw-bold"><i class="fas fa-building me-2 text-primary"></i>Data Kop Surat</span>
                <button type="button" id="btnToggleEdit" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="pradanaToggleEditPengaturanInstansi()">
                    <i class="fas fa-pen me-1"></i> Edit
                </button>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('pengaturan-instansi.update') }}" enctype="multipart/form-data" id="formPengaturanInstansi">
                    @csrf
                    @method('PUT')

                    <h6 class="text-uppercase fw-bold small text-secondary mb-3">Identitas instansi</h6>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Nama Instansi <span class="text-danger">*</span>
                                @include('partials.ikon-info', ['pesan' => 'Baris paling besar pada kop surat; tulis dengan huruf kapital.'])
                            </label>
                            <input type="text" name="nama_instansi" class="form-control bg-light border-0 pradana-field"
                                   value="{{ old('nama_instansi', $pengaturanInstansi->nama_instansi) }}"
                                   placeholder="Cth: PEMERINTAH DESA UREK-UREK" readonly required>
                            @error('nama_instansi') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Jenis Instansi <span class="text-danger">*</span>
                                @include('partials.ikon-info', ['pesan' => 'Dipakai untuk jabatan penandatangan di dokumen, misalnya "Kepala Desa".'])
                            </label>
                            <input type="text" name="jenis_instansi" class="form-control bg-light border-0 pradana-field"
                                   value="{{ old('jenis_instansi', $pengaturanInstansi->jenis_instansi) }}"
                                   placeholder="Cth: Pemerintah Desa" readonly required>
                            @error('jenis_instansi') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Kabupaten
                                @include('partials.ikon-info', ['pesan' => 'Kosongkan kalau kop kantor hanya satu baris.'])
                            </label>
                            <input type="text" name="nama_kabupaten" class="form-control bg-light border-0 pradana-field"
                                   value="{{ old('nama_kabupaten', $pengaturanInstansi->nama_kabupaten) }}"
                                   placeholder="Cth: Banyumas" readonly>
                            @error('nama_kabupaten') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-secondary">Kecamatan</label>
                            <input type="text" name="nama_kecamatan" class="form-control bg-light border-0 pradana-field"
                                   value="{{ old('nama_kecamatan', $pengaturanInstansi->nama_kecamatan) }}"
                                   placeholder="Cth: Kedung Banteng" readonly>
                            @error('nama_kecamatan') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <h6 class="text-uppercase fw-bold small text-secondary mt-4 mb-3">Alamat &amp; kontak</h6>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label small fw-bold text-secondary">Alamat Instansi <span class="text-danger">*</span></label>
                            <textarea name="alamat_instansi" rows="2" class="form-control bg-light border-0 pradana-field" readonly required>{{ old('alamat_instansi', $pengaturanInstansi->alamat_instansi) }}</textarea>
                            @error('alamat_instansi') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Kode Pos</label>
                            <input type="text" name="kode_pos" class="form-control bg-light border-0 pradana-field"
                                   value="{{ old('kode_pos', $pengaturanInstansi->kode_pos) }}" placeholder="Cth: 53182" readonly inputmode="numeric">
                            @error('kode_pos') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">No. Telepon</label>
                            <input type="text" name="no_telp" class="form-control bg-light border-0 pradana-field"
                                   value="{{ old('no_telp', $pengaturanInstansi->no_telp) }}" placeholder="Cth: 0281-xxxxxxx" readonly>
                            @error('no_telp') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-bold text-secondary">Email</label>
                            <input type="email" name="email" class="form-control bg-light border-0 pradana-field"
                                   value="{{ old('email', $pengaturanInstansi->email) }}" placeholder="Cth: desa@kabupaten.go.id" readonly>
                            @error('email') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                        </div>
                    </div>
                    <div class="small text-secondary mt-2 d-flex align-items-center gap-1">
                        Telepon &amp; email boleh kosong
                        @include('partials.ikon-info', ['pesan' => 'Kantor yang belum punya telepon atau email tetap bisa mencetak kop; barisnya cukup tidak ikut tercetak.'])
                    </div>

                    <hr class="my-4">

                    <h6 class="text-uppercase fw-bold small text-secondary mb-3">Logo</h6>
                    <div class="row g-3 align-items-start">
                        <div class="col-md-6">
                            @if ($pengaturanInstansi->logoUrl())
                                <div class="border rounded p-2 bg-light d-inline-block">
                                    <img src="{{ $pengaturanInstansi->logoUrl() }}" alt="Logo instansi" style="max-height: 84px;">
                                </div>
                                <div class="small text-muted mt-1">Logo yang sekarang dipakai di semua dokumen.</div>
                            @else
                                <div class="border rounded p-3 text-center text-muted small bg-light" style="max-width: 220px; border-style: dashed;">
                                    <i class="fas fa-image fa-2x d-block mb-2 opacity-50"></i>
                                    Belum ada logo tersimpan
                                </div>
                            @endif
                        </div>
                        <div class="col-md-6">
                            <div id="logoUploadWrap" class="d-none">
                                <label class="form-label small fw-bold text-secondary">
                                    {{ $pengaturanInstansi->logo_path ? 'Ganti Logo (biarkan kosong kalau tidak ingin ganti)' : 'Upload Logo' }}
                                    @include('partials.ikon-info', ['pesan' => 'Format jpg/jpeg/png, maksimal 2MB. Logo dengan latar transparan terlihat lebih rapi di kop surat.'])
                                </label>
                                <input type="file" name="logo" id="logoBaru" class="form-control" accept=".jpg,.jpeg,.png">

                                {{-- Pratinjau langsung dari berkas yang dipilih (URL objek, belum
                                     dikirim ke server), jadi gambar muncul seketika. --}}
                                <div class="mt-3 d-none" id="logoPratinjau">
                                    <div class="small fw-bold text-secondary mb-1">
                                        Pratinjau logo baru
                                        <span class="fw-normal" id="logoUkuran"></span>
                                    </div>
                                    <img id="logoPratinjauGambar" alt="Pratinjau logo baru"
                                         class="border rounded p-2 bg-light" style="max-height: 80px;">
                                    <div class="small text-secondary mt-1">
                                        Logo lama masih yang dipakai sampai perubahan disimpan.
                                    </div>
                                </div>
                                @error('logo') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                            </div>
                            <div class="small text-muted">Klik <strong>Edit</strong> di kanan atas untuk mengubah logo.</div>
                        </div>
                    </div>

                    <div class="d-none gap-2 mt-4" id="submitWrap">
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                        <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onclick="window.location.reload()">
                            Batal
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-5">
        <div class="card border-0 shadow-sm" style="position: sticky; top: 1rem;">
            <div class="card-header bg-white py-3">
                <span class="fw-bold"><i class="fas fa-eye me-2 text-secondary"></i>Pratinjau Kop Surat</span>
            </div>
            <div class="card-body">
                {{-- Cermin dari partials/kop-pdf.blade.php: susunan barisnya sama, jadi
                     apa yang benar di sini ikut benar di PDF. --}}
                <div class="border rounded p-3 bg-white">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            {{-- Logo yang SUDAH tersimpan ikut tampil sejak halaman dibuka,
                                 bukan hanya saat berkas baru dipilih (permintaan 10 Okt).
                                 Sebelum ini keluhan "logo tidak muncul di kop" baru kelihatan
                                 sesudah petugas mengunggah ulang logonya sendiri. --}}
                            <td id="kopLogoSel" style="width: 74px; vertical-align: middle; padding-right: 8px;{{ $pengaturanInstansi->logoUrl() ? '' : ' display: none;' }}">
                                <img id="kopLogo" alt="" style="max-width: 64px; max-height: 64px;"
                                     src="{{ $pengaturanInstansi->logoUrl() ?: '' }}">
                            </td>
                            <td style="text-align: center; vertical-align: middle;">
                                <div id="kopKabupaten" class="pradana-kop-tingkat"></div>
                                <div id="kopKecamatan" class="pradana-kop-tingkat"></div>
                                <div id="kopNama" class="pradana-kop-utama">PEMERINTAH DESA / KELURAHAN</div>
                                <div id="kopAlamat" class="small text-muted mt-1"></div>
                                <div id="kopKontak" class="small text-muted"></div>
                            </td>
                            <td id="kopLogoPenyeimbang" style="width: 74px;{{ $pengaturanInstansi->logoUrl() ? '' : ' display: none;' }}">&nbsp;</td>
                        </tr>
                    </table>
                    <div style="border-bottom: 3px solid #000; margin-top: 6px;"></div>
                    <div style="border-bottom: 1px solid #000; margin-top: 2px;"></div>
                </div>

                <div class="small text-secondary mt-3 d-flex align-items-start gap-2">
                    @include('partials.ikon-info', ['pesan' => 'Kop ini dipakai untuk surat keluar, Berita Acara Pemusnahan, dan Buku Agenda. Pratinjau berubah mengikuti isian di kiri, tapi PDF baru memakai versi yang sudah disimpan.'])
                    <span>Pratinjau ikut berubah saat mengetik; PDF memakai versi yang sudah disimpan.</span>
                </div>

                <div class="alert alert-light border small mt-3 mb-0 d-flex align-items-start gap-2">
                    @include('partials.ikon-info', ['pesan' => 'Nama tempat pada baris tanggal surat diambil dari Nama Instansi; kata "Pemerintah", "Sekretariat", dan "Kantor" di depan dibuang otomatis.'])
                    <span>
                        <span class="fw-bold d-block mb-1">Baris tempat &amp; tanggal surat</span>
                        <span id="kopTanggal">-</span>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<style>
    .pradana-kop-tingkat { font-size: .78rem; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; line-height: 1.25; }
    .pradana-kop-utama { font-size: 1.2rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; line-height: 1.2; }
</style>
<script>
    // Mode readonly-sampai-diklik-Edit (pola handleEditHeader() sistem lama) + pratinjau
    // kop langsung. Pratinjau dibaca dari VALUE field, bukan dari data tersimpan, jadi
    // perubahan terlihat sebelum disimpan.
    (function () {
        const field = {
            nama: document.querySelector('[name="nama_instansi"]'),
            kabupaten: document.querySelector('[name="nama_kabupaten"]'),
            kecamatan: document.querySelector('[name="nama_kecamatan"]'),
            alamat: document.querySelector('[name="alamat_instansi"]'),
            kodePos: document.querySelector('[name="kode_pos"]'),
            telp: document.querySelector('[name="no_telp"]'),
            email: document.querySelector('[name="email"]'),
        };

        function teks(el) {
            return el && el.value ? el.value.trim() : '';
        }

        function tampilkan(id, nilai) {
            const el = document.getElementById(id);
            el.textContent = nilai;
            el.style.display = nilai ? '' : 'none';
        }

        function perbarui() {
            tampilkan('kopKabupaten', teks(field.kabupaten) ? 'PEMERINTAH KABUPATEN ' + teks(field.kabupaten).toUpperCase() : '');
            tampilkan('kopKecamatan', teks(field.kecamatan) ? 'KECAMATAN ' + teks(field.kecamatan).toUpperCase() : '');

            const nama = (teks(field.nama) || 'PEMERINTAH DESA / KELURAHAN').toUpperCase();
            document.getElementById('kopNama').textContent = nama;

            let alamat = teks(field.alamat);
            if (alamat && teks(field.kodePos)) {
                alamat += ', Kode Pos ' + teks(field.kodePos);
            }
            document.getElementById('kopAlamat').textContent = alamat;

            const kontak = [];
            if (teks(field.telp)) kontak.push('Telp. ' + teks(field.telp));
            if (teks(field.email)) kontak.push('E-mail: ' + teks(field.email));
            document.getElementById('kopKontak').textContent = kontak.join(' · ');

            // Meniru PengaturanInstansi::tempatSurat() di PHP.
            let tempat = teks(field.nama).replace(/^(pemerintah|sekretariat|kantor)\s+/i, '').trim();
            if (!tempat) tempat = teks(field.kecamatan);
            document.getElementById('kopTanggal').textContent = (tempat || '(isi Nama Instansi dulu)') + ', 05 Oktober 2026';
        }

        Object.values(field).forEach(function (el) {
            if (el) el.addEventListener('input', perbarui);
        });

        // Pratinjau logo: berkas dibaca langsung di browser (URL objek), tidak dikirim
        // ke server dulu, jadi gambar muncul seketika setelah dipilih.
        let urlPratinjau = null;
        const inputLogo = document.getElementById('logoBaru');

        // Logo yang sudah tersimpan dipakai sebagai dasar pratinjau kop: halaman ini
        // dibuka tanpa memilih berkas pun harus tetap menunjukkan kop sungguhan
        // (permintaan user 10 Okt 2026). Diisi dari Blade, jadi tidak ada tebakan path.
        const logoTersimpan = @json($pengaturanInstansi->logoUrl());

        function tampilkanLogoKop(url) {
            const sel = document.getElementById('kopLogoSel');
            const penyeimbang = document.getElementById('kopLogoPenyeimbang');
            const gambar = document.getElementById('kopLogo');

            if (url) {
                gambar.src = url;
                sel.style.display = '';
                penyeimbang.style.display = '';
            } else {
                gambar.removeAttribute('src');
                sel.style.display = 'none';
                penyeimbang.style.display = 'none';
            }
        }

        if (inputLogo) {
            inputLogo.addEventListener('change', function () {
                const kotak = document.getElementById('logoPratinjau');
                const berkas = inputLogo.files && inputLogo.files[0];

                if (urlPratinjau) {
                    URL.revokeObjectURL(urlPratinjau);
                    urlPratinjau = null;
                }

                if (!berkas) {
                    kotak.classList.add('d-none');
                    // Bukan "hilangkan logo" — kembali ke logo yang sekarang tersimpan.
                    tampilkanLogoKop(logoTersimpan);

                    return;
                }

                urlPratinjau = URL.createObjectURL(berkas);
                document.getElementById('logoPratinjauGambar').src = urlPratinjau;
                document.getElementById('logoUkuran').textContent =
                    '- ' + berkas.name + ' (' + Math.round(berkas.size / 1024) + ' KB)';
                kotak.classList.remove('d-none');

                // Logo ikut masuk ke pratinjau kop: keluhan "logo tidak keluar" harus
                // kelihatan di layar, bukan baru ketahuan di PDF.
                tampilkanLogoKop(urlPratinjau);
            });
        }

        window.pradanaToggleEditPengaturanInstansi = function () {
            document.querySelectorAll('.pradana-field').forEach(function (el) {
                el.readOnly = false;
                el.classList.remove('bg-light', 'border-0');
            });
            document.getElementById('logoUploadWrap').classList.remove('d-none');
            document.getElementById('submitWrap').classList.remove('d-none');
            document.getElementById('submitWrap').classList.add('d-flex');
            document.getElementById('btnToggleEdit').classList.add('d-none');
        };

        perbarui();
    })();
</script>
@endpush
