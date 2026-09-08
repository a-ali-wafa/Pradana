@extends('layouts.app')

@section('title', 'Manajemen User - PRADANA')
@section('page-title', 'Manajemen User')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-users-cog me-2 text-primary"></i>Manajemen User</h4>
        <p class="text-secondary small mb-0">Total {{ $users->total() }} akun terdaftar</p>
    </div>
    <a href="{{ route('users.create') }}" class="btn btn-primary rounded-pill px-4">
        <i class="fas fa-user-plus me-1"></i> Tambah User
    </a>
</div>

<div class="card">
    <div class="card-body p-0">
        @if($users->isEmpty())
            <div class="text-center py-5 text-secondary">
                <i class="fas fa-users fa-3x mb-3 opacity-25"></i>
                <p class="mb-0">Belum ada user terdaftar.</p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4">Nama Lengkap</th>
                            <th>Email</th>
                            <th style="width:120px;">Role</th>
                            <th style="width:110px;">Status</th>
                            <th class="text-center pe-4" style="width:80px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($users as $user)
                        <tr>
                            <td class="ps-4">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="rounded-circle bg-primary bg-opacity-10 text-primary fw-bold d-flex align-items-center justify-content-center flex-shrink-0"
                                         style="width:36px;height:36px;font-size:0.85rem;">
                                        {{ strtoupper(substr($user->nama_lengkap, 0, 1)) }}
                                    </div>
                                    <div>
                                        <div class="fw-semibold">{{ $user->nama_lengkap }}</div>
                                        @if($user->id === Auth::id())
                                            <span class="badge text-bg-light border small">Anda</span>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="text-secondary">{{ $user->email }}</td>
                            <td>
                                @php
                                    $roleColor = match($user->role) {
                                        'admin'    => 'danger',
                                        'kepala'   => 'warning',
                                        'perangkat'=> 'secondary',
                                        default    => 'secondary',
                                    };
                                @endphp
                                <span class="badge text-bg-{{ $roleColor }} rounded-pill px-3">
                                    {{ ucfirst($user->role) }}
                                </span>
                            </td>
                            <td>
                                @if($user->deleted_at)
                                    <span class="badge text-bg-danger rounded-pill px-2">Non-aktif</span>
                                @else
                                    <span class="badge text-bg-success rounded-pill px-2">Aktif</span>
                                @endif
                            </td>
                            <td class="text-center pe-4">
                                {{--
                                    Edit / reset PIN belum diimplementasikan (A4/A5 masih WAJIB TANYA USER).
                                    Hapus: admin tidak boleh hapus dirinya sendiri.
                                --}}
                                @if($user->id !== Auth::id())
                                <button type="button" class="btn btn-sm btn-outline-danger" title="Non-aktifkan / Hapus"
                                    onclick="pradanaConfirmHapus(
                                        '{{ route('users.destroy', $user) }}',
                                        'Hapus akun &ldquo;{{ addslashes($user->nama_lengkap) }}&rdquo;? Aksi ini tidak dapat dibatalkan.'
                                    )">
                                    <i class="fas fa-trash"></i>
                                </button>
                                @else
                                <span class="text-secondary small">—</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-top">
                {{ $users->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>

{{--
    Catatan untuk session berikutnya:
    - Edit user (ganti nama/email/role) dan Reset PIN admin → A4/A5 masih WAJIB TANYA USER.
    - Soft-delete (kolom deleted_at ada di skema) — belum ada route destroy di controller,
      perlu ditambah dulu sebelum tombol hapus di atas bisa berfungsi.
--}}

@endsection

@push('scripts')
<form id="formHapusUser" method="POST" style="display:none;">
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
            const f = document.getElementById('formHapusUser');
            f.action = url;
            f.submit();
        }
    });
}
</script>
@endpush
