@extends('layouts.app')

@section('title', 'Klasifikasi Primer - PRADANA')
@section('page-title', 'Klasifikasi Primer')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-tags me-2 text-primary"></i>Klasifikasi Primer</h4>
        <p class="text-secondary small mb-0">Total {{ $klasifikasiPrimer->total() }} entri</p>
    </div>
    @auth @if(Auth::user()->isAdmin())
    <a href="{{ route('klasifikasi-primer.create') }}" class="btn btn-primary rounded-pill px-4">
        <i class="fas fa-plus me-1"></i> Tambah
    </a>
    @endif @endauth
</div>

<div class="card">
    <div class="card-body p-0">
        @if($klasifikasiPrimer->isEmpty())
            <div class="text-center py-5 text-secondary">
                <i class="fas fa-tags fa-3x mb-3 opacity-25"></i>
                <p class="mb-0">Belum ada klasifikasi primer.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width:130px;">Kode</th>
                            <th>Nama</th>
                            <th class="text-center" style="width:110px;">Jml Sekunder</th>
                            @auth @if(Auth::user()->isAdmin())
                            <th class="text-center pe-4" style="width:110px;">Aksi</th>
                            @endif @endauth
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($klasifikasiPrimer as $item)
                        <tr>
                            <td class="ps-4">
                                <span class="badge text-bg-light border fw-semibold font-monospace fs-6 px-3">{{ $item->kode }}</span>
                            </td>
                            <td class="fw-semibold">{{ $item->nama }}</td>
                            <td class="text-center">
                                <span class="badge bg-primary rounded-pill">{{ $item->sekunder_count }}</span>
                            </td>
                            @auth @if(Auth::user()->isAdmin())
                            <td class="text-center pe-4">
                                <div class="d-flex gap-1 justify-content-center">
                                    <a href="{{ route('klasifikasi-primer.edit', $item) }}"
                                       class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <form method="POST" action="{{ route('klasifikasi-primer.destroy', $item) }}"
                                          class="d-inline"
                                          data-konfirmasi="Hapus klasifikasi primer &raquo;{{ $item->kode }} – {{ $item->nama }}&raquo;?"
                                          data-konfirmasi-judul="Konfirmasi Hapus"
                                          data-konfirmasi-catatan="Tidak bisa dihapus jika masih dipakai surat."
                                          data-konfirmasi-ya="Ya, Hapus">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                            @endif @endauth
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-top">
                {{ $klasifikasiPrimer->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>

@endsection


