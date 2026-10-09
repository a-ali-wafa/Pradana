@extends('layouts.app')

@section('title', 'Detail Surat Masuk - PRADANA')
@section('page-title', 'Detail Surat Masuk')

@push('styles')
<style>
    .badge-sifat-mendesak  { background: #fee2e2; color: #dc2626; }
    .badge-sifat-penting   { background: #fef3c7; color: #d97706; }
    .badge-sifat-rahasia   { background: #ede9fe; color: #7c3aed; }
    .badge-sifat-biasa     { background: #f0fdf4; color: #16a34a; }
    .badge-arsip-aktif     { background: #dcfce7; color: #15803d; }
    .badge-arsip-inaktif   { background: #f1f5f9; color: #64748b; }
    .dl-grid {
        display: grid;
        grid-template-columns: 160px 1fr;
        gap: 0.5rem 1.5rem;
        align-items: start;
    }
    .dl-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #94a3b8;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        padding-top: 2px;
    }
    .dl-value { color: #334155; }
    .lampiran-card {
        border: 1px solid #e2e8f0;
        border-radius: 10px;
        padding: 0.75rem 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }
    .lampiran-card:hover { background: #f8fafc; }
</style>
@endpush

@section('content')

{{-- Header toolbar --}}
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-2">
    <div>
        <nav aria-label="breadcrumb" class="mb-1">
            <ol class="breadcrumb small mb-0">
                <li class="breadcrumb-item"><a href="{{ route('surat-masuk.index') }}">Surat Masuk</a></li>
                <li class="breadcrumb-item active">{{ $surat_masuk->nomor_surat }}</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-0">
            <i class="fas fa-inbox me-2 text-primary"></i>{{ $surat_masuk->nomor_surat }}
        </h4>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('surat-masuk.edit', $surat_masuk) }}" class="btn btn-outline-primary rounded-pill px-3">
            <i class="fas fa-pen me-1"></i> Edit
        </a>
        @include('partials.arsip-aksi', ['surat' => $surat_masuk, 'jenis' => 'masuk'])
    </div>
</div>

<div class="row g-4">
    {{-- Kolom kiri: data utama --}}
    <div class="col-lg-8">

        {{-- Badge status --}}
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <span class="badge rounded-pill px-3 py-2 badge-sifat-{{ $surat_masuk->sifat }}">
                <i class="fas fa-circle-dot me-1"></i>{{ ucfirst($surat_masuk->sifat) }}
            </span>
            <span class="badge rounded-pill px-3 py-2 badge-arsip-{{ $surat_masuk->status_arsip }}">
                <i class="fas fa-archive me-1"></i>Arsip {{ ucfirst($surat_masuk->status_arsip) }}
            </span>
            <span class="badge rounded-pill px-3 py-2 text-bg-light border">
                <i class="fas fa-copy me-1"></i>{{ ucfirst($surat_masuk->status_berkas) }}
            </span>
        </div>

        {{-- Perihal --}}
        <div class="card mb-3">
            <div class="card-body">
                <p class="text-secondary small fw-bold text-uppercase mb-1">Perihal</p>
                <p class="fs-5 fw-semibold mb-2">{{ $surat_masuk->perihal }}</p>
                @if($surat_masuk->ringkasan)
                    <p class="text-secondary mb-0" style="white-space: pre-line;">{{ $surat_masuk->ringkasan }}</p>
                @endif
            </div>
        </div>

        {{--
            HASIL BACA ISI LAMPIRAN (fitur baru 5 Okt 2026). Teks di bawah ini
            diambil MESIN dari berkas lampiran dan belum tentu benar, makanya
            badge-nya "belum diverifikasi" sampai user menekan Simpan. Kartu ini
            ada di DOM sejak awal tapi tersembunyi kalau belum ada hasil baca —
            begitu unggah lampiran menghasilkan teks, JavaScript dari
            partials/lampiran-upload mengisi textarea ini dan melepas .d-none,
            jadi user tidak perlu memuat ulang halaman untuk melihat hasilnya.
        --}}
        <div class="card mb-3 {{ $surat_masuk->isi_hasil_baca ? '' : 'd-none' }}" id="kartuHasilBaca">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-robot me-2"></i>Hasil Baca Isi Lampiran</span>
                <span class="badge {{ $surat_masuk->isi_terverifikasi_pada ? 'text-bg-success' : 'text-bg-warning' }}"
                      id="badgeVerifikasiBaca">
                    {{ $surat_masuk->isi_terverifikasi_pada ? 'sudah diverifikasi' : 'belum diverifikasi' }}
                </span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('surat-masuk.isi.update', $surat_masuk) }}">
                    @csrf
                    @method('PATCH')

                    <label class="form-label small fw-bold text-secondary" for="isiHasilBaca">
                        Teks yang diambil sistem dari lampiran — periksa, boleh diperbaiki langsung
                    </label>
                    <textarea name="isi_hasil_baca" id="isiHasilBaca" rows="8" class="form-control"
                              style="white-space: pre-wrap;">{{ $surat_masuk->isi_hasil_baca }}</textarea>
                    @error('isi_hasil_baca') <div class="text-danger small mt-1">{{ $message }}</div> @enderror

                    <div class="small text-secondary mt-1" id="keteranganBaca">
                        @if($surat_masuk->isi_dibaca_dari)
                            Dibaca dari {{ $surat_masuk->isi_dibaca_dari }}
                            pada {{ $surat_masuk->isi_dibaca_pada?->translatedFormat('d F Y, H:i') }}.
                        @endif
                        @if($surat_masuk->isi_terverifikasi_pada)
                            Disimpan oleh pengguna pada {{ $surat_masuk->isi_terverifikasi_pada->translatedFormat('d F Y, H:i') }}.
                        @endif
                    </div>

                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" value="1" id="jadikanRingkasan"
                               name="jadikan_ringkasan" @checked(empty($surat_masuk->ringkasan))>
                        <label class="form-check-label small" for="jadikanRingkasan">
                            Salin teks ini ke <strong>Ringkasan surat</strong> di atas
                        </label>
                        <div class="form-text">
                            Ringkasan dipakai daftar surat &amp; pencarian. Kalau isian ringkasannya
                            sudah ada dan tidak mau tertimpa, jangan dicentang.
                        </div>
                    </div>

                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 mt-3">
                        <i class="fas fa-check me-1"></i> Simpan (sudah saya periksa)
                    </button>
                </form>
            </div>
        </div>

        {{-- Data pengirim --}}
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-user me-2"></i>Pengirim</div>
            <div class="card-body">
                <div class="dl-grid">
                    <span class="dl-label">Nama</span>
                    <span class="dl-value">{{ $surat_masuk->pengirim }}</span>

                    @if($surat_masuk->jabatan_pengirim)
                    <span class="dl-label">Jabatan</span>
                    <span class="dl-value">{{ $surat_masuk->jabatan_pengirim }}</span>
                    @endif

                    @if($surat_masuk->instansi_pengirim)
                    <span class="dl-label">Instansi</span>
                    <span class="dl-value">{{ $surat_masuk->instansi_pengirim }}</span>
                    @endif

                    @if($surat_masuk->kota_asal || $surat_masuk->provinsi_asal)
                    <span class="dl-label">Asal</span>
                    <span class="dl-value">
                        {{ collect([$surat_masuk->kota_asal, $surat_masuk->provinsi_asal])->filter()->implode(', ') }}
                    </span>
                    @endif
                </div>
            </div>
        </div>

        {{-- Data surat --}}
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-file-alt me-2"></i>Data Surat</div>
            <div class="card-body">
                <div class="dl-grid">
                    <span class="dl-label">Nomor Surat</span>
                    <span class="dl-value fw-semibold">{{ $surat_masuk->nomor_surat }}</span>

                    <span class="dl-label">Tanggal Surat</span>
                    <span class="dl-value">{{ \Carbon\Carbon::parse($surat_masuk->tanggal_surat)->translatedFormat('d F Y') }}</span>

                    <span class="dl-label">Tgl Diterima</span>
                    <span class="dl-value">{{ \Carbon\Carbon::parse($surat_masuk->tanggal_diterima)->translatedFormat('d F Y') }}</span>

                    @if($surat_masuk->lokasi_fisik)
                    <span class="dl-label">Lokasi Fisik</span>
                    <span class="dl-value">{{ $surat_masuk->lokasi_fisik }}</span>
                    @endif

                    <span class="dl-label">Dicatat Oleh</span>
                    <span class="dl-value">{{ $surat_masuk->petugas?->nama_lengkap ?? '—' }}</span>

                    <span class="dl-label">Didaftarkan</span>
                    <span class="dl-value text-secondary small">
                        {{ $surat_masuk->created_at->translatedFormat('d F Y, H:i') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Lampiran --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-paperclip me-2"></i>Lampiran</span>
                <span class="badge bg-primary rounded-pill" id="hitung-lampiran">{{ $surat_masuk->lampiran->count() }}</span>
            </div>
            <div class="card-body">
                <div class="d-flex flex-column gap-2" id="daftar-lampiran">
                @if($surat_masuk->lampiran->isEmpty())
                    <div class="text-center text-secondary py-3" data-kosong>
                        <i class="fas fa-paperclip fa-2x mb-2 opacity-25"></i>
                        <p class="small mb-0">Belum ada lampiran untuk surat ini.</p>
                    </div>
                @else
                        @foreach($surat_masuk->lampiran as $lamp)
                        <div class="lampiran-card">
                            <i class="fas fa-file text-primary fa-lg"></i>
                            <div class="flex-grow-1 min-w-0">
                                <div class="fw-semibold text-truncate">{{ $lamp->nama_file }}</div>
                                <div class="text-secondary small">
                                    Diunggah oleh {{ $lamp->pengunggah?->nama_lengkap ?? '—' }}
                                    · {{ $lamp->created_at->diffForHumans() }}
                                </div>
                            </div>
                            <a href="{{ route('lampiran.download', $lamp) }}"
                               class="btn btn-sm btn-outline-primary rounded-pill px-3" title="Unduh">
                                <i class="fas fa-download me-1"></i>Unduh
                            </a>
                            {{--
                                Tombol "Ajukan Hapus" hanya untuk surat yang sudah lewat retensi
                                (L-04: lebih dari 5 tahun; L-21: acuannya `tanggal_surat`).
                                Dulu blok ini menghitung umurnya sendiri dari `tanggal_diterima`,
                                jadi surat masuk bisa menampilkan tombol yang ditolak server
                                (dan aturan 5 tahun punya dua dasar hitung di satu aplikasi).
                                Sekarang pertanyaan umurnya diajukan ke `UmurArsip::lewatRetensi()`
                                — sama persis dengan yang ditegakkan
                                PengajuanHapusLampiranController::store().
                                `pengajuanHapus` dibaca dari koleksi yang sudah di-eager-load di
                                controller; bentuk lama `$lamp->pengajuanHapus()->exists()` berarti
                                satu query TAMBAHAN untuk setiap berkas di daftar ini.
                            --}}
                            @if($surat_masuk->lewatRetensi())
                                @if($lamp->pengajuanHapus->where('status', 'menunggu')->isEmpty())
                                    <form method="POST" action="{{ route('lampiran.pengajuan-hapus.store', $lamp) }}"
                                          onsubmit="return confirm('Ajukan penghapusan lampiran ini?')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3" title="Ajukan Hapus">
                                            <i class="fas fa-trash-alt me-1"></i>Ajukan Hapus
                                        </button>
                                    </form>
                                @else
                                    <span class="badge text-bg-warning rounded-pill px-2">Menunggu</span>
                                @endif
                            @endif
                        </div>
                        @endforeach
                @endif
                </div>

                {{-- Upload lampiran baru --}}
                @include('partials.lampiran-upload', [
                    'action' => route('surat-masuk.lampiran.store', $surat_masuk),
                    'suratId' => $surat_masuk->id,
                ])
            </div>
        </div>

    </div>

    {{-- Kolom kanan: klasifikasi & metadata --}}
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-tags me-2"></i>Klasifikasi Arsip</div>
            <div class="card-body">
                @if($surat_masuk->primer)
                <div class="mb-2">
                    <span class="text-secondary small fw-bold">PRIMER</span>
                    <div class="fw-semibold">{{ $surat_masuk->primer->kode }} – {{ $surat_masuk->primer->nama }}</div>
                </div>
                @endif
                @if($surat_masuk->sekunder)
                <div class="mb-2">
                    <span class="text-secondary small fw-bold">SEKUNDER</span>
                    <div class="fw-semibold">{{ $surat_masuk->sekunder->kode }} – {{ $surat_masuk->sekunder->nama }}</div>
                </div>
                @endif
                @if($surat_masuk->tersier)
                <div class="mb-2">
                    <span class="text-secondary small fw-bold">TERSIER</span>
                    <div class="fw-semibold">{{ $surat_masuk->tersier->kode }} – {{ $surat_masuk->tersier->nama }}</div>
                </div>
                @endif
                @if(! $surat_masuk->primer)
                    <span class="text-secondary small">Belum dikalsifikasikan</span>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-clock me-2"></i>Riwayat</div>
            <div class="card-body">
                <div class="small text-secondary">
                    <div class="mb-2">
                        <span class="fw-bold text-dark">Dibuat</span><br>
                        {{ $surat_masuk->created_at->translatedFormat('d M Y, H:i') }}
                    </div>
                    <div>
                        <span class="fw-bold text-dark">Terakhir Diubah</span><br>
                        {{ $surat_masuk->updated_at->translatedFormat('d M Y, H:i') }}
                    </div>
                </div>
                <hr class="opacity-25">
                <a href="{{ route('surat-masuk.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill w-100">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar
                </a>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
    // Dipanggil partials/lampiran-upload setelah unggahan selesai, kalau server
    // berhasil membaca isi berkasnya. Tidak ada reload halaman: teks langsung
    // muncul di kartu "Hasil Baca Isi Lampiran" untuk diperiksa user.
    window.pradanaHasilBacaIsi = function (baca) {
        const kartu = document.getElementById('kartuHasilBaca');
        if (!kartu) return;

        if (baca && baca.teks) {
            document.getElementById('isiHasilBaca').value = baca.teks;
            kartu.classList.remove('d-none');

            const badge = document.getElementById('badgeVerifikasiBaca');
            badge.textContent = 'belum diverifikasi';
            badge.className = 'badge text-bg-warning';

            const ket = document.getElementById('keteranganBaca');
            ket.textContent = (baca.catatan || []).join(' ');

            kartu.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else if (baca && (baca.catatan || []).length) {
            // Tidak ada teks (foto/DOC lama/PDF scan) — beritahu lewat badge
            // keterangan kartu kalau kartunya sedang terbuka, dan lewat alert
            // ringan kalau kartunya tersembunyi, supaya "kok isinya nggak kebaca"
            // tidak terasa seperti aplikasi error.
            const kotak = document.getElementById('keteranganBaca');

            if (!kartu.classList.contains('d-none')) {
                kotak.textContent = (baca.catatan || []).join(' ');
            } else {
                window.alert((baca.catatan || []).join('\n'));
            }
        }
    };
</script>
@endpush
