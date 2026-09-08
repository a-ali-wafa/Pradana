@extends('layouts.app')

@section('title', 'Surat Keluar - PRADANA')
@section('page-title', 'Surat Keluar')

@push('styles')
<style>
    .badge-sifat-mendesak  { background: #fee2e2; color: #dc2626; }
    .badge-sifat-penting   { background: #fef3c7; color: #d97706; }
    .badge-sifat-rahasia   { background: #ede9fe; color: #7c3aed; }
    .badge-sifat-biasa     { background: #f0fdf4; color: #16a34a; }
    .badge-arsip-aktif     { background: #dcfce7; color: #15803d; }
    .badge-arsip-inaktif   { background: #f1f5f9; color: #64748b; }
</style>
@endpush

@section('content')

{{-- Toolbar --}}
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-paper-plane me-2 text-primary"></i>Daftar Surat Keluar</h4>
        <p class="text-secondary small mb-0">Total {{ $suratKeluar->total() }} surat terdaftar</p>
    </div>
    <a href="{{ route('surat-keluar.create') }}" class="btn btn-primary rounded-pill px-4">
        <i class="fas fa-plus me-1"></i> Tambah Surat Keluar
    </a>
</div>

{{-- Filter --}}
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('surat-keluar.index') }}" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary mb-1">Cari Perihal / Nomor / Penerima</label>
                <input type="text" name="cari" class="form-control"
                       placeholder="Ketik kata kunci..." value="{{ request('cari') }}">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Tahun</label>
                <select name="tahun" class="form-select">
                    <option value="">Semua tahun</option>
                    @for($y = date('Y'); $y >= 2020; $y--)
                        <option value="{{ $y }}" {{ request('tahun') == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary mb-1">Klasifikasi Primer</label>
                <select name="klasifikasi_primer_id" class="form-select">
                    <option value="">Semua klasifikasi</option>
                    @foreach($klasifikasiPrimer as $kp)
                        <option value="{{ $kp->id }}" {{ request('klasifikasi_primer_id') == $kp->id ? 'selected' : '' }}>
                            {{ $kp->kode }} – {{ $kp->nama }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Status Arsip</label>
                <select name="status_arsip" class="form-select">
                    <option value="">Semua status</option>
                    <option value="aktif"   {{ request('status_arsip') === 'aktif'   ? 'selected' : '' }}>Aktif</option>
                    <option value="inaktif" {{ request('status_arsip') === 'inaktif' ? 'selected' : '' }}>Inaktif</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search me-1"></i> Cari
                </button>
                <a href="{{ route('surat-keluar.index') }}" class="btn btn-outline-secondary w-100" title="Reset">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>
</div>

{{-- Tabel --}}
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-table me-2"></i>Hasil Pencarian</span>
        <span class="badge bg-primary rounded-pill">{{ $suratKeluar->count() }} dari {{ $suratKeluar->total() }}</span>
    </div>
    <div class="card-body p-0">
        @if($suratKeluar->isEmpty())
            <div class="text-center py-5 text-secondary">
                <i class="fas fa-paper-plane fa-3x mb-3 opacity-25"></i>
                <p class="mb-0">Belum ada surat keluar yang terdaftar.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width:120px;">Tgl Surat</th>
                            <th>No. Surat</th>
                            <th>Penerima</th>
                            <th>Perihal</th>
                            <th style="width:100px;">Sifat</th>
                            <th style="width:90px;">Status</th>
                            <th class="text-center pe-4" style="width:120px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($suratKeluar as $surat)
                        <tr>
                            <td class="ps-4 text-secondary small">
                                {{ \Carbon\Carbon::parse($surat->tanggal_surat)->format('d M Y') }}
                            </td>
                            <td>
                                <a href="{{ route('surat-keluar.show', $surat) }}"
                                   class="fw-semibold text-decoration-none text-primary">
                                    {{ $surat->nomor_surat }}
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold">{{ $surat->penerima }}</div>
                                @if($surat->instansi_penerima)
                                    <div class="small text-secondary">{{ $surat->instansi_penerima }}</div>
                                @endif
                            </td>
                            <td>
                                <div>{{ Str::limit($surat->perihal, 55) }}</div>
                                @if($surat->primer)
                                    <span class="badge text-bg-light border small">
                                        {{ $surat->primer->kode }}
                                        @if($surat->sekunder) · {{ $surat->sekunder->kode }} @endif
                                    </span>
                                @endif
                            </td>
                            <td>
                                <span class="badge rounded-pill px-2 py-1 badge-sifat-{{ $surat->sifat }}">
                                    {{ ucfirst($surat->sifat) }}
                                </span>
                            </td>
                            <td>
                                <span class="badge rounded-pill px-2 py-1 badge-arsip-{{ $surat->status_arsip }}">
                                    {{ ucfirst($surat->status_arsip) }}
                                </span>
                            </td>
                            <td class="text-center pe-4">
                                <div class="d-flex gap-1 justify-content-center">
                                    <a href="{{ route('surat-keluar.show', $surat) }}"
                                       class="btn btn-sm btn-outline-secondary" title="Detail">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('surat-keluar.cetak', $surat) }}"
                                       class="btn btn-sm btn-outline-success" title="Cetak PDF" target="_blank">
                                        <i class="fas fa-print"></i>
                                    </a>
                                    <a href="{{ route('surat-keluar.edit', $surat) }}"
                                       class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    @auth
                                        @if(Auth::user()->isAdmin())
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus"
                                            onclick="pradanaConfirmHapus(
                                                '{{ route('surat-keluar.destroy', $surat) }}',
                                                'Hapus surat keluar &ldquo;{{ addslashes($surat->nomor_surat) }}&rdquo;?'
                                            )">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        @endif
                                    @endauth
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="px-4 py-3 border-top d-flex justify-content-between align-items-center">
                <div class="text-secondary small">
                    Menampilkan {{ $suratKeluar->firstItem() }}–{{ $suratKeluar->lastItem() }}
                    dari {{ $suratKeluar->total() }} surat
                </div>
                {{ $suratKeluar->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
        @endif
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
        html: pesan,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-trash me-1"></i>Ya, Hapus',
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
