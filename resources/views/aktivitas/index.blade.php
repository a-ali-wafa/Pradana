@extends('layouts.app')

@section('title', 'Log Aktivitas - PRADANA')
@section('page-title', 'Log Aktivitas')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-history me-2 text-primary"></i>Log Aktivitas</h4>
        <p class="text-secondary small mb-0">
            {{ $aktivitas->total() }} catatan. Read-only — jejak ini tidak bisa diubah dari layar;
            catatan lebih dari 2 tahun dibersihkan otomatis kecuali yang berkaitan pemusnahan arsip.
        </p>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('aktivitas.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary mb-1">Cari teks aksi</label>
                <input type="text" name="cari" value="{{ $filter['cari'] }}" class="form-control"
                       placeholder="mis. Memusnahkan, Mengunggah lampiran">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary mb-1">Oleh</label>
                <select name="user_id" class="form-select">
                    <option value="">Semua user</option>
                    @foreach($pilihanUser as $u)
                        <option value="{{ $u->id }}" {{ (string) $filter['user_id'] === (string) $u->id ? 'selected' : '' }}>
                            {{ $u->nama_lengkap }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Dari</label>
                <input type="date" name="dari" value="{{ $filter['dari'] }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Sampai</label>
                <input type="date" name="sampai" value="{{ $filter['sampai'] }}" class="form-control">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100" title="Terapkan">
                    <i class="fas fa-search"></i>
                </button>
            </div>
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
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th class="ps-4" style="width:170px;">Waktu</th>
                        <th style="width:180px;">Oleh</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($aktivitas as $catatan)
                        <tr>
                            <td class="ps-4 small text-secondary">
                                {{ $catatan->created_at->translatedFormat('d M Y') }}
                                <span class="d-block">{{ $catatan->created_at->format('H:i') }}</span>
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
        <div class="px-4 py-3 border-top">
            {{ $aktivitas->withQueryString()->links('pagination::bootstrap-5') }}
        </div>
    </div>
@endif

@endsection
