@extends('layouts.app')

@section('title', 'Klasifikasi Tersier - PRADANA')
@section('page-title', 'Klasifikasi Tersier')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-tags me-2 text-primary"></i>Klasifikasi Tersier</h4>
        <p class="text-secondary small mb-0">Total {{ $klasifikasiTersier->total() }} entri</p>
    </div>
    @auth @if(Auth::user()->isAdmin())
    <a href="{{ route('klasifikasi-tersier.create') }}" class="btn btn-primary rounded-pill px-4">
        <i class="fas fa-plus me-1"></i> Tambah
    </a>
    @endif @endauth
</div>

<div class="card">
    <div class="card-body p-0">
        @if($klasifikasiTersier->isEmpty())
            <div class="text-center py-5 text-secondary">
                <i class="fas fa-tags fa-3x mb-3 opacity-25"></i>
                <p class="mb-0">Belum ada klasifikasi tersier.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width:130px;">Kode</th>
                            <th>Nama</th>
                            <th style="width:180px;">Sekunder</th>
                            <th style="width:180px;">Primer</th>
                            @auth @if(Auth::user()->isAdmin())
                            <th class="text-center pe-4" style="width:110px;">Aksi</th>
                            @endif @endauth
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($klasifikasiTersier as $item)
                        <tr>
                            <td class="ps-4">
                                <span class="badge text-bg-light border fw-semibold font-monospace fs-6 px-3">{{ $item->kode }}</span>
                            </td>
                            <td class="fw-semibold">{{ $item->nama }}</td>
                            <td>
                                @if($item->sekunder)
                                    <span class="badge text-bg-primary opacity-75 rounded-pill px-2">{{ $item->sekunder->kode }}</span>
                                    <span class="small ms-1 text-secondary">{{ $item->sekunder->nama }}</span>
                                @else
                                    <span class="text-secondary small">—</span>
                                @endif
                            </td>
                            <td>
                                @if($item->sekunder?->primer)
                                    <span class="badge text-bg-secondary opacity-75 rounded-pill px-2">{{ $item->sekunder->primer->kode }}</span>
                                    <span class="small ms-1 text-secondary">{{ $item->sekunder->primer->nama }}</span>
                                @else
                                    <span class="text-secondary small">—</span>
                                @endif
                            </td>
                            @auth @if(Auth::user()->isAdmin())
                            <td class="text-center pe-4">
                                <div class="d-flex gap-1 justify-content-center">
                                    <a href="{{ route('klasifikasi-tersier.edit', $item) }}"
                                       class="btn btn-sm btn-outline-primary" title="Edit">
                                        <i class="fas fa-pen"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-danger" title="Hapus"
                                        onclick="pradanaConfirmHapus(
                                            '{{ route('klasifikasi-tersier.destroy', $item) }}',
                                            'Hapus klasifikasi tersier &ldquo;{{ addslashes($item->kode) }} – {{ addslashes($item->nama) }}&rdquo;?<br>Tidak bisa dihapus jika masih dipakai surat.'
                                        )">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                            @endif @endauth
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-top">
                {{ $klasifikasiTersier->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>

@endsection

@push('scripts')
<form id="formHapusKlasifikasi" method="POST" style="display:none;">
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
        confirmButtonText: 'Ya, Hapus',
        cancelButtonText: 'Batal',
    }).then((r) => {
        if (r.isConfirmed) {
            const f = document.getElementById('formHapusKlasifikasi');
            f.action = url;
            f.submit();
        }
    });
}
</script>
@endpush
