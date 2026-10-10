@extends('layouts.app')

@section('title', 'Detail Pemusnahan Arsip - PRADANA')
@section('page-title', 'Detail Pemusnahan Arsip')

@section('content')

@php
    $belumDiproses = $pemusnahan->status === 'menunggu';
    $isAdmin = auth()->user()->isAdmin();
@endphp

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">
            <i class="fas fa-fire me-2 text-danger"></i>
            Pemusnahan #{{ $pemusnahan->id }}
        </h4>
        <p class="text-secondary small mb-0">
            Diajukan {{ $pemusnahan->created_at->translatedFormat('d M Y, H:i') }}
            oleh <strong>{{ $pemusnahan->pengaju?->nama_lengkap ?? '—' }}</strong>
        </p>
    </div>
    <div class="d-flex gap-2">
        <a href="{{ route('pemusnahan-arsip.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">
            <i class="fas fa-arrow-left me-1"></i> Kembali
        </a>
        @if($pemusnahan->status === 'disetujui')
            <a href="{{ route('pemusnahan-arsip.berita-acara', $pemusnahan) }}" target="_blank"
               class="btn btn-sm btn-dark rounded-pill">
                <i class="fas fa-file-pdf me-1"></i> Cetak Berita Acara
            </a>
        @endif
    </div>
</div>

@if($pemusnahan->nomor_berita_acara)
    <div class="alert alert-danger rounded-3 d-flex align-items-center gap-2">
        <i class="fas fa-stamp"></i>
        <div>
            <strong>Berita Acara {{ $pemusnahan->nomor_berita_acara }}</strong>
            — dilaksanakan
            {{ $pemusnahan->tanggal_pelaksanaan?->translatedFormat('d M Y') ?? '(tanggal belum diisi)' }}
            @if($pemusnahan->pemroses)
                · disetujui oleh {{ $pemusnahan->pemroses->nama_lengkap }}
                @if($pemusnahan->diproses_pada)
                    pada {{ $pemusnahan->diproses_pada->translatedFormat('d M Y, H:i') }}
                @endif
            @endif
        </div>
    </div>
@endif

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-box me-2"></i>Rincian Arsip ({{ $pemusnahan->items->count() }} surat)
            </div>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Jenis</th>
                            <th>Nomor Surat</th>
                            <th>Perihal</th>
                            <th>Tanggal Surat</th>
                            <th class="text-center">Lampiran</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($pemusnahan->items as $item)
                            <tr>
                                <td>
                                    <span class="badge text-bg-{{ $item->adalahSuratMasuk() ? 'info' : 'primary' }}">
                                        {{ $item->adalahSuratMasuk() ? 'Masuk' : 'Keluar' }}
                                    </span>
                                </td>
                                <td class="small fw-semibold">{{ $item->nomor_surat_snapshot ?? '—' }}</td>
                                <td class="small">{{ $item->perihal_snapshot ?? '—' }}</td>
                                <td class="small">{{ $item->tanggal_surat_snapshot?->translatedFormat('d M Y') ?? '—' }}</td>
                                <td class="text-center small">
                                    {{ $item->jumlah_lampiran_snapshot }}
                                    @if($pemusnahan->status === 'disetujui')
                                        <i class="fas fa-check text-danger ms-1" title="Berkas fisik sudah dimusnahkan"></i>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="text-center text-secondary py-4 small">
                                    Tidak ada arsip dalam pengajuan ini.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($pemusnahan->alasan)
            <div class="card">
                <div class="card-body">
                    <div class="small fw-semibold text-uppercase text-secondary mb-1">Alasan Pemusnahan</div>
                    <div>{!! nl2br(e($pemusnahan->alasan)) !!}</div>
                </div>
            </div>
        @endif

        @if($pemusnahan->catatan_admin)
            <div class="card">
                <div class="card-body">
                    <div class="small fw-semibold text-uppercase text-secondary mb-1">Catatan Admin</div>
                    <div>{!! nl2br(e($pemusnahan->catatan_admin)) !!}</div>
                </div>
            </div>
        @endif
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-body">
                <div class="small fw-semibold text-uppercase text-secondary mb-2">Status</div>
                <span class="badge rounded-pill fs-6 {{ $pemusnahan->status === 'disetujui' ? 'text-bg-danger' : ($pemusnahan->status === 'ditolak' ? 'text-bg-secondary' : 'text-bg-warning') }}">
                    {{ ucfirst($pemusnahan->status) }}
                </span>

                <hr>

                <div class="small text-secondary mb-1">Diajukan oleh</div>
                <div class="mb-3">{{ $pemusnahan->pengaju?->nama_lengkap ?? '—' }}</div>

                @if($pemusnahan->diproses_oleh)
                    <div class="small text-secondary mb-1">Diproses oleh</div>
                    <div class="mb-3">
                        {{ $pemusnahan->pemroses?->nama_lengkap ?? '—' }}
                        <div class="small text-secondary">
                            {{ $pemusnahan->diproses_pada?->translatedFormat('d M Y, H:i') }}
                        </div>
                    </div>
                @endif

                @if($belumDiproses && $isAdmin)
                    <form method="POST" action="{{ route('pemusnahan-arsip.setujui', $pemusnahan) }}"
                          class="mb-2"
                          data-konfirmasi="Semua surat pada daftar ini beserta seluruh lampirannya akan dihapus permanen dan tidak bisa dipulihkan. Berita Acara akan diterbitkan sebagai bukti."
                          data-konfirmasi-judul="Musnahkan arsip ini?"
                          data-konfirmasi-ya="Ya, Musnahkan">
                        @csrf
                        <button type="submit" class="btn btn-danger w-100 rounded-pill">
                            <i class="fas fa-fire me-1"></i> Setujui &amp; Musnahkan
                        </button>
                    </form>

                    <button type="button" class="btn btn-outline-secondary w-100 rounded-pill"
                            data-pengubah="#form-tolak" aria-expanded="false">
                        <i class="fas fa-ban me-1"></i> Tolak
                    </button>

                    <div id="form-tolak" data-pradana-tertutup class="mt-2">
                        <form method="POST" action="{{ route('pemusnahan-arsip.tolak', $pemusnahan) }}">
                            @csrf
                            <textarea name="catatan_admin" class="form-control form-control-sm mb-2" rows="3"
                                      placeholder="Alasan penolakan (opsional)"></textarea>
                            <button type="submit" class="btn btn-secondary btn-sm w-100 rounded-pill">
                                Konfirmasi Tolak
                            </button>
                        </form>
                    </div>
                @elseif($belumDiproses)
                    <div class="alert alert-light border small mb-0 mt-2">
                        Menunggu persetujuan admin. Anda akan menerima notifikasi setelah diputuskan.
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

