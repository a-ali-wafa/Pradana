@extends('layouts.app')

@section('title', 'Detail Surat Keluar - PRADANA')
@section('page-title', 'Detail Surat Keluar')

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
        grid-template-columns: 170px 1fr;
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
                <li class="breadcrumb-item"><a href="{{ route('surat-keluar.index') }}">Surat Keluar</a></li>
                <li class="breadcrumb-item active">{{ $suratKeluar->nomor_surat }}</li>
            </ol>
        </nav>
        <h4 class="fw-bold mb-0">
            <i class="fas fa-paper-plane me-2 text-primary"></i>{{ $suratKeluar->nomor_surat }}
        </h4>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('surat-keluar.cetak', $suratKeluar) }}"
           class="btn btn-outline-success rounded-pill px-3" target="_blank">
            <i class="fas fa-print me-1"></i> Cetak PDF
        </a>
        <a href="{{ route('surat-keluar.edit', $suratKeluar) }}" class="btn btn-outline-primary rounded-pill px-3">
            <i class="fas fa-pen me-1"></i> Edit
        </a>
        @auth
            @if(Auth::user()->isAdmin())
            <button type="button" class="btn btn-outline-danger rounded-pill px-3"
                onclick="pradanaConfirmHapus('{{ route('surat-keluar.destroy', $suratKeluar) }}', 'Hapus surat ini secara permanen?')">
                <i class="fas fa-trash me-1"></i> Hapus
            </button>
            @endif
        @endauth
    </div>
</div>

<div class="row g-4">
    {{-- Kolom kiri: data utama --}}
    <div class="col-lg-8">

        {{-- Badge status --}}
        <div class="d-flex gap-2 mb-3 flex-wrap">
            <span class="badge rounded-pill px-3 py-2 badge-sifat-{{ $suratKeluar->sifat }}">
                <i class="fas fa-circle-dot me-1"></i>{{ ucfirst($suratKeluar->sifat) }}
            </span>
            <span class="badge rounded-pill px-3 py-2 badge-arsip-{{ $suratKeluar->status_arsip }}">
                <i class="fas fa-archive me-1"></i>Arsip {{ ucfirst($suratKeluar->status_arsip) }}
            </span>
            <span class="badge rounded-pill px-3 py-2 text-bg-light border">
                <i class="fas fa-copy me-1"></i>{{ ucfirst($suratKeluar->status_berkas) }}
            </span>
        </div>

        {{-- Perihal --}}
        <div class="card mb-3">
            <div class="card-body">
                <p class="text-secondary small fw-bold text-uppercase mb-1">Perihal</p>
                <p class="fs-5 fw-semibold mb-2">{{ $suratKeluar->perihal }}</p>
                @if($suratKeluar->ringkasan)
                    <p class="text-secondary mb-0" style="white-space: pre-line;">{{ $suratKeluar->ringkasan }}</p>
                @endif
            </div>
        </div>

        {{-- Data penerima --}}
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-user me-2"></i>Penerima</div>
            <div class="card-body">
                <div class="dl-grid">
                    <span class="dl-label">Nama</span>
                    <span class="dl-value">{{ $suratKeluar->penerima }}</span>

                    @if($suratKeluar->jabatan_penerima)
                    <span class="dl-label">Jabatan</span>
                    <span class="dl-value">{{ $suratKeluar->jabatan_penerima }}</span>
                    @endif

                    @if($suratKeluar->instansi_penerima)
                    <span class="dl-label">Instansi</span>
                    <span class="dl-value">{{ $suratKeluar->instansi_penerima }}</span>
                    @endif

                    @if($suratKeluar->kota_tujuan || $suratKeluar->provinsi_tujuan)
                    <span class="dl-label">Tujuan</span>
                    <span class="dl-value">
                        {{ collect([$suratKeluar->kota_tujuan, $suratKeluar->provinsi_tujuan])->filter()->implode(', ') }}
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
                    <span class="dl-value fw-semibold font-monospace">{{ $suratKeluar->nomor_surat }}</span>

                    <span class="dl-label">Tanggal Surat</span>
                    <span class="dl-value">{{ \Carbon\Carbon::parse($suratKeluar->tanggal_surat)->translatedFormat('d F Y') }}</span>

                    @if($suratKeluar->lokasi_fisik)
                    <span class="dl-label">Lokasi Fisik</span>
                    <span class="dl-value">{{ $suratKeluar->lokasi_fisik }}</span>
                    @endif

                    <span class="dl-label">Dibuat Oleh</span>
                    <span class="dl-value">{{ $suratKeluar->petugas?->nama_lengkap ?? '—' }}</span>

                    <span class="dl-label">Didaftarkan</span>
                    <span class="dl-value text-secondary small">
                        {{ $suratKeluar->created_at->translatedFormat('d F Y, H:i') }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Isi / Draf Konten --}}
        @if($suratKeluar->drafKonten)
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-align-left me-2"></i>Isi Surat (Draf)</span>
                <a href="{{ route('surat-keluar.cetak', $suratKeluar) }}"
                   class="btn btn-sm btn-outline-success rounded-pill px-3" target="_blank">
                    <i class="fas fa-file-pdf me-1"></i> Generate PDF
                </a>
            </div>
            <div class="card-body" style="white-space: pre-line; font-size: 0.9rem; line-height: 1.8;">
                {{ $suratKeluar->drafKonten->isi_surat ?? '—' }}
            </div>
        </div>
        @else
        <div class="card mb-3">
            <div class="card-body text-center text-secondary py-4">
                <i class="fas fa-file-alt fa-2x mb-2 opacity-25"></i>
                <p class="small mb-2">Belum ada draf isi surat.</p>
                <a href="{{ route('surat-keluar.cetak', $suratKeluar) }}"
                   class="btn btn-sm btn-outline-success rounded-pill px-3" target="_blank">
                    <i class="fas fa-print me-1"></i> Cetak dengan Template Default
                </a>
            </div>
        </div>
        @endif

        {{-- Lampiran --}}
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-paperclip me-2"></i>Lampiran</span>
                <span class="badge bg-primary rounded-pill">{{ $suratKeluar->lampiran->count() }}</span>
            </div>
            <div class="card-body">
                @if($suratKeluar->lampiran->isEmpty())
                    <div class="text-center text-secondary py-3">
                        <i class="fas fa-paperclip fa-2x mb-2 opacity-25"></i>
                        <p class="small mb-0">Belum ada lampiran untuk surat ini.</p>
                    </div>
                @else
                    <div class="d-flex flex-column gap-2">
                        @foreach($suratKeluar->lampiran as $lamp)
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
                            @php
                                $umurTahun = \Carbon\Carbon::parse($suratKeluar->tanggal_surat)->diffInYears(now());
                            @endphp
                            @if($umurTahun >= 5 && ! $lamp->pengajuanHapus()->whereIn('status', ['menunggu'])->exists())
                            <form method="POST" action="{{ route('lampiran.pengajuan-hapus.store', $lamp) }}"
                                  onsubmit="return confirm('Ajukan penghapusan lampiran ini?')">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-danger rounded-pill px-3">
                                    <i class="fas fa-trash-alt me-1"></i>Ajukan Hapus
                                </button>
                            </form>
                            @elseif($umurTahun >= 5)
                            <span class="badge text-bg-warning rounded-pill px-2">Menunggu</span>
                            @endif
                        </div>
                        @endforeach
                    </div>
                @endif

                {{-- Upload lampiran baru --}}
                <hr class="text-secondary opacity-25 my-3">
                <form method="POST" action="{{ route('surat-keluar.lampiran.store', $suratKeluar) }}"
                      enctype="multipart/form-data" class="d-flex gap-2 align-items-end">
                    @csrf
                    <div class="flex-grow-1">
                        <label class="form-label small fw-bold mb-1">Unggah Lampiran Baru</label>
                        <input type="file" name="file" class="form-control @error('file') is-invalid @enderror" required>
                        @error('file') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <button type="submit" class="btn btn-outline-primary rounded-pill px-3" style="white-space:nowrap;">
                        <i class="fas fa-upload me-1"></i> Unggah
                    </button>
                </form>
            </div>
        </div>

    </div>

    {{-- Kolom kanan: klasifikasi & metadata --}}
    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-tags me-2"></i>Klasifikasi Arsip</div>
            <div class="card-body">
                @if($suratKeluar->primer)
                <div class="mb-2">
                    <span class="text-secondary small fw-bold">PRIMER</span>
                    <div class="fw-semibold">{{ $suratKeluar->primer->kode }} – {{ $suratKeluar->primer->nama }}</div>
                </div>
                @endif
                @if($suratKeluar->sekunder)
                <div class="mb-2">
                    <span class="text-secondary small fw-bold">SEKUNDER</span>
                    <div class="fw-semibold">{{ $suratKeluar->sekunder->kode }} – {{ $suratKeluar->sekunder->nama }}</div>
                </div>
                @endif
                @if($suratKeluar->tersier)
                <div class="mb-2">
                    <span class="text-secondary small fw-bold">TERSIER</span>
                    <div class="fw-semibold">{{ $suratKeluar->tersier->kode }} – {{ $suratKeluar->tersier->nama }}</div>
                </div>
                @endif
                @if(! $suratKeluar->primer)
                    <span class="text-secondary small">Belum diklasifikasikan</span>
                @endif
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-clock me-2"></i>Riwayat</div>
            <div class="card-body">
                <div class="small text-secondary">
                    <div class="mb-2">
                        <span class="fw-bold text-dark">Dibuat</span><br>
                        {{ $suratKeluar->created_at->translatedFormat('d M Y, H:i') }}
                    </div>
                    <div>
                        <span class="fw-bold text-dark">Terakhir Diubah</span><br>
                        {{ $suratKeluar->updated_at->translatedFormat('d M Y, H:i') }}
                    </div>
                </div>
                <hr class="opacity-25">
                <a href="{{ route('surat-keluar.index') }}" class="btn btn-outline-secondary btn-sm rounded-pill w-100">
                    <i class="fas fa-arrow-left me-1"></i> Kembali ke Daftar
                </a>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<form id="formHapusSuratKeluar" method="POST" style="display:none;">
    @csrf @method('DELETE')
</form>
<script>
function pradanaConfirmHapus(url, pesan) {
    Swal.fire({
        title: 'Konfirmasi Hapus',
        text: pesan,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.getElementById('formHapusSuratKeluar');
            form.action = url;
            form.submit();
        }
    });
}
</script>
@endpush
