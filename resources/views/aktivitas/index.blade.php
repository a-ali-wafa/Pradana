@extends('layouts.app')

@section('title', 'Log Aktivitas - PRADANA')
@section('page-title', 'Log Aktivitas')

@section('content')

@php
    // Hanya isi yang benar-benar dipakai yang ikut queryString unduhan, supaya
    // tautan tidak membawa `cari=&user_id=` kosong. Kerangka filternya dibaca dari
    // `AktivitasController::terapkanFilter()` yang sama dengan layar — angka di CSV
    // tidak bisa berbeda dari angka di atas.
    $argumenUnduh = array_filter([
        'cari' => $filter['cari'],
        'user_id' => $filter['user_id'],
        'dari' => $filter['dari'],
        'sampai' => $filter['sampai'],
    ], static fn ($v): bool => $v !== null && $v !== '');
@endphp

{{-- Kepala layar dirapikan 10 Okt: satu baris saja. Dulu dua baris teks penjelasan
     di bawah judul + satu baris catatan di bawah tombol, yang membuat daftar baru
     mulai terlihat setelah menggulir. Penjelasan dipindah ke ikon info (hover),
     dan tombol unduh sudah memuat jumlahnya. --}}
<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div class="d-flex align-items-center gap-2">
        <h5 class="fw-bold mb-0"><i class="fas fa-history me-2 text-primary"></i>Log Aktivitas</h5>
        <span class="badge text-bg-light border rounded-pill">{{ $aktivitas->total() }} catatan</span>
        @include('partials.ikon-info', ['pesan' => 'Read-only: jejak ini tidak bisa diubah dari layar. Catatan lebih dari 2 tahun dibersihkan otomatis, kecuali yang berkaitan pemusnahan arsip.'])
    </div>
    <div class="d-flex align-items-center gap-2">
        <a href="{{ route('aktivitas.rekap', $argumenUnduh) }}"
           class="btn btn-sm btn-outline-primary rounded-pill px-3"
           title="Semua catatan pada filter ini, bukan hanya halaman yang terlihat">
            <i class="fas fa-file-csv me-1"></i> Unduh CSV ({{ $aktivitas->total() }})
        </a>
        @include('partials.ikon-info', ['pesan' => 'Berkas CSV memuat semua catatan pada filter yang sedang aktif (termasuk yang tidak terlihat di halaman lain), dipisah titik-koma sehingga langsung rapi dibuka di Excel kantor.'])
    </div>
</div>

<div class="card mb-3">
    <div class="card-body py-3">
        <form method="GET" action="{{ route('aktivitas.index') }}" class="row g-2 align-items-end">
            <div class="col-12 col-md-4">
                <label class="form-label small fw-bold text-secondary mb-1">Cari teks aksi</label>
                <input type="text" name="cari" value="{{ $filter['cari'] }}" class="form-control form-control-sm"
                       placeholder="mis. Memusnahkan, Mengunggah lampiran">
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label small fw-bold text-secondary mb-1">Oleh</label>
                <select name="user_id" class="form-select form-select-sm">
                    <option value="">Semua user</option>
                    @foreach($pilihanUser as $u)
                        <option value="{{ $u->id }}" {{ (string) $filter['user_id'] === (string) $u->id ? 'selected' : '' }}>
                            {{ $u->nama_lengkap }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Dari</label>
                <input type="date" name="dari" value="{{ $filter['dari'] }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Sampai</label>
                <input type="date" name="sampai" value="{{ $filter['sampai'] }}" class="form-control form-control-sm">
            </div>
            <div class="col-6 col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100" title="Terapkan">
                    <i class="fas fa-search"></i>
                </button>
            </div>
            @if ($argumenUnduh !== [])
                <div class="col-12">
                    <a href="{{ route('aktivitas.index') }}" class="small text-decoration-none">
                        <i class="fas fa-undo me-1"></i>Reset filter
                    </a>
                </div>
            @endif
        </form>
    </div>
</div>

@if($aktivitas->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5 text-secondary">
            <i class="fas fa-clipboard-list fa-3x mb-3 opacity-25"></i>
            <p class="mb-0">Belum ada catatan yang cocok dengan filter ini.</p>
        </div>
    </div>
@else
    {{-- Tabel dirapikan supaya muat lebih banyak baris per layar: `table-sm`,
             waktu satu baris (tanggal + jam), dan kolom petugas dipersempit. --}}
    <div class="card mb-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle table-sm mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width:150px;">Waktu</th>
                        <th style="width:170px;">Oleh</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($aktivitas as $catatan)
                        <tr>
                            <td class="ps-4 small text-secondary text-nowrap"
                                title="{{ $catatan->created_at->translatedFormat('d F Y H:i') }}">
                                {{ $catatan->created_at->translatedFormat('d M Y') }}
                                <span class="fw-semibold text-dark">{{ $catatan->created_at->format('H:i') }}</span>
                            </td>
                            <td class="small">
                                {{ $catatan->user?->nama_lengkap ?? '(user dihapus)' }}
                            </td>
                            <td class="small">{{ $catatan->aksi }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="px-4 py-2 border-top d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div class="small text-secondary">
                Halaman {{ $aktivitas->currentPage() }} dari {{ max(1, $aktivitas->lastPage()) }}
                ({{ $aktivitas->total() }} catatan)
            </div>
            {{ $aktivitas->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endif

@endsection
