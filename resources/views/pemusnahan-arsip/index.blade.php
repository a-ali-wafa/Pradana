@extends('layouts.app')

@section('title', 'Pemusnahan Arsip - PRADANA')
@section('page-title', 'Pemusnahan Arsip')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">
            <i class="fas fa-fire me-2 text-danger"></i>Pemusnahan Arsip
        </h4>
        <p class="text-secondary small mb-0">
            {{ $pemusnahan->total() }} pengajuan
            @if($statusAktif !== 'semua') berstatus <strong>{{ $statusAktif }}</strong> @endif.
            Pemusnahan permanen hanya terjadi setelah disetujui admin, dan selalu disertai Berita Acara.
        </p>
    </div>

    <div class="d-flex gap-2 align-items-center">
        <a href="{{ route('pemusnahan-arsip.create') }}" class="btn btn-primary rounded-pill">
            <i class="fas fa-plus me-1"></i> Ajukan Pemusnahan
        </a>
        <div class="btn-group" role="group" aria-label="Filter status">
            @foreach(['semua' => 'Semua', 'menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'] as $nilai => $label)
                <a href="{{ route('pemusnahan-arsip.index', ['status' => $nilai]) }}"
                   class="btn btn-sm {{ $statusAktif === $nilai ? 'btn-primary' : 'btn-outline-secondary' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>
    </div>
</div>

@if($pemusnahan->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5 text-secondary">
            <i class="fas fa-boxes fa-3x mb-3 opacity-25"></i>
            <p class="mb-0 fw-semibold">Belum ada pengajuan pemusnahan.</p>
            <p class="small mt-1">
                Hanya arsip berumur lebih dari 5 tahun yang sudah dinonaktifkan yang bisa diajukan.
                Cek daftar di <a href="{{ route('surat-masuk.index', ['usang' => 1]) }}">Surat Masuk</a>
                atau <a href="{{ route('surat-keluar.index', ['usang' => 1]) }}">Surat Keluar</a>.
            </p>
        </div>
    </div>
@else
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Tanggal Diajukan</th>
                        <th>Jumlah Arsip</th>
                        <th>Alasan</th>
                        <th>Diajukan Oleh</th>
                        <th>Status</th>
                        <th class="text-end">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($pemusnahan as $item)
                        <tr>
                            <td class="small">
                                {{ $item->created_at->translatedFormat('d M Y') }}
                                <div class="text-secondary">{{ $item->created_at->diffForHumans() }}</div>
                            </td>
                            <td><span class="badge text-bg-light">{{ $item->items->count() }} surat</span></td>
                            <td class="small text-muted">{{ Str::limit($item->alasan ?? '—', 60) }}</td>
                            <td class="small">{{ $item->pengaju?->nama_lengkap ?? '—' }}</td>
                            <td>
                                <span class="badge rounded-pill {{ $item->status === 'disetujui' ? 'text-bg-danger' : ($item->status === 'ditolak' ? 'text-bg-secondary' : 'text-bg-warning') }}">
                                    {{ ucfirst($item->status) }}
                                </span>
                                @if($item->nomor_berita_acara)
                                    <div class="small text-muted mt-1">{{ $item->nomor_berita_acara }}</div>
                                @endif
                            </td>
                            <td class="text-end">
                                <a href="{{ route('pemusnahan-arsip.show', $item) }}" class="btn btn-sm btn-outline-primary rounded-pill">
                                    <i class="fas fa-eye me-1"></i> Detail
                                </a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($pemusnahan->hasPages())
        <div class="mt-3">{{ $pemusnahan->links() }}</div>
    @endif
@endif

@endsection
