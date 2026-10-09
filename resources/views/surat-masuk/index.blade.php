@extends('layouts.app')

@php
    use App\Support\CariArsip;
    /*
     * sorot() = tandai kata kunci dengan <mark>. Ia men-escape teks LEBIH DULU
     * baru menyisipkan tag, jadi `{!! !!}` di halaman ini aman untuk isi surat
     * buatan user (jangan menambah `{!! !!}` lain tanpa alasan yang sama).
     */
@endphp

@section('title', 'Surat Masuk - PRADANA')
@section('page-title', 'Surat Masuk')

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
        <h4 class="fw-bold mb-1">
            <i class="fas fa-{{ $melihatSampah ? 'trash' : ($gabung ? 'layer-group' : 'inbox') }} me-2 text-{{ $melihatSampah ? 'danger' : 'primary' }}"></i>
            {{ $melihatSampah ? 'Tempat Sampah Surat Masuk' : ($gabung ? 'Semua Arsip' : 'Daftar Surat Masuk') }}
        </h4>
        <p class="text-secondary small mb-0">
            @if($melihatSampah)
                {{ $arsip->total() }} surat dihapus lunak — belum dimusnahkan, masih bisa dipulihkan.
            @elseif($gabung)
                {{ $arsip->total() }} surat cocok (surat masuk + surat keluar jadi satu urutan).
            @else
                Total {{ $arsip->total() }} surat terdaftar
            @endif
        </p>
    </div>
    <div class="d-flex gap-2">
        @if (Auth::user()->isAdmin())
            <a href="{{ route('surat-masuk.index', $melihatSampah ? [] : ['sampah' => 1]) }}"
               class="btn btn-outline-{{ $melihatSampah ? 'secondary' : 'danger' }} rounded-pill px-3">
                <i class="fas fa-{{ $melihatSampah ? 'inbox' : 'trash' }} me-1"></i>
                {{ $melihatSampah ? 'Kembali ke arsip' : 'Tempat sampah' }}
            </a>
        @endif
        @if(! $melihatSampah && ! $gabung)
            <a href="{{ route('surat-masuk.create') }}" class="btn btn-primary rounded-pill px-4">
                <i class="fas fa-plus me-1"></i> Tambah Surat Masuk
            </a>
        @endif
    </div>
</div>

{{-- Tab Surat Masuk | Surat Keluar | Semua arsip. Tidak ada route baru: tab
     ketiga cuma menambah ?jenis=semua pada halaman ini. --}}
@if(! $melihatSampah)
    @include('partials.tab-arsip', ['aktif' => $gabung ? 'semua' : 'masuk', 'routeAsal' => 'surat-masuk.index'])
@endif

@if($melihatSampah)
    <div class="alert alert-warning small rounded-3">
        <i class="fas fa-info-circle me-1"></i>
        Surat di tempat sampah masih menyimpan seluruh lampirannya. Pemusnahan permanen
        yang resmi (dengan Berita Acara) dilakukan lewat
        <a href="{{ route('pemusnahan-arsip.index') }}" class="fw-semibold">Pemusnahan Arsip</a>.
    </div>
@endif

{{-- Filter --}}
@if(! $melihatSampah)
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('surat-masuk.index') }}" class="row g-2 align-items-end">
            {{-- Filter tidak boleh diam-diam mengeluarkan user dari tab "Semua arsip". --}}
            @if($gabung)
                <input type="hidden" name="jenis" value="semua">
            @endif
            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary mb-1">Cari Nomor / Perihal / Pengirim / Isi</label>
                <input type="text" name="cari" class="form-control"
                       placeholder="Ketik kata kunci..." value="{{ request('cari') }}">
                <div class="form-text">
                    Tiap kata harus cocok (boleh di kolom berbeda), tidak harus berurutan.
                    Yang digali: nomor, perihal, pengirim/instansi, <strong>ringkasan</strong>,
                    <strong>teks hasil baca isi lampiran</strong>, dan nama berkas lampiran (L-15).
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Sifat</label>
                <select name="sifat" class="form-select">
                    <option value="">Semua sifat</option>
                    @foreach(['mendesak' => 'Mendesak', 'penting' => 'Penting', 'rahasia' => 'Rahasia', 'biasa' => 'Biasa'] as $val => $label)
                        <option value="{{ $val }}" {{ request('sifat') === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
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
                    <option value="aktif" {{ request('status_arsip') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                    <option value="inaktif" {{ request('status_arsip') === 'inaktif' ? 'selected' : '' }}>Inaktif</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Tahun Surat</label>
                <select name="tahun" class="form-select">
                    <option value="">Semua tahun</option>
                    @for($y = date('Y'); $y >= 2020; $y--)
                        <option value="{{ $y }}" {{ request('tahun') == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endfor
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-center">
                <div class="form-check mt-3">
                    <input class="form-check-input" type="checkbox" value="1" name="usang" id="filterUsang"
                           {{ $usang ? 'checked' : '' }}>
                    <label class="form-check-label small fw-bold text-secondary" for="filterUsang">
                        Lewat retensi 5 tahun
                    </label>
                </div>
            </div>
            <div class="col-md-8 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-search me-1"></i> Cari
                </button>
                <a href="{{ route('surat-masuk.index') }}" class="btn btn-outline-secondary" title="Reset">
                    <i class="fas fa-times me-1"></i> Reset
                </a>
            </div>
        </form>
    </div>
</div>
@endif

{{-- Tabel --}}
@if($gabung)
    @include('partials.daftar-gabung')
@else
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-table me-2"></i>{{ $melihatSampah ? 'Tempat Sampah' : ($cari ? 'Hasil Pencarian' : 'Daftar Surat') }}</span>
        <span class="badge bg-primary rounded-pill">{{ $arsip->count() }} dari {{ $arsip->total() }}</span>
    </div>
    <div class="card-body p-0">
        @if($arsip->isEmpty())
            <div class="text-center py-5 text-secondary">
                <i class="fas fa-{{ $melihatSampah ? 'trash' : 'inbox' }} fa-3x mb-3 opacity-25"></i>
                <p class="mb-0">
                    @if($melihatSampah)
                        Tempat sampah kosong — tidak ada surat masuk yang dihapus lunak.
                    @elseif($usang)
                        Tidak ada surat masuk yang lewat retensi 5 tahun dengan filter ini.
                        Surat yang muncul di sini bisa dinonaktifkan, lalu diajukan ke
                        <a href="{{ route('pemusnahan-arsip.create') }}">Pemusnahan Arsip</a>.
                    @else
                        Belum ada surat masuk yang cocok dengan filter.
                    @endif
                </p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width:130px;">Tgl Diterima</th>
                            <th>No. Surat</th>
                            <th>Pengirim</th>
                            <th>Perihal</th>
                            <th style="width:100px;">Sifat</th>
                            <th style="width:90px;">Status</th>
                            <th class="text-center pe-4" style="width:150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($arsip as $surat)
                        <tr>
                            <td class="ps-4 text-secondary small">
                                {{ \Carbon\Carbon::parse($surat->tanggal_diterima)->format('d M Y') }}
                            </td>
                            <td>
                                <a href="{{ route('surat-masuk.show', $surat) }}"
                                   class="fw-semibold text-decoration-none text-primary">
                                    {!! CariArsip::sorot($surat->nomor_surat, $cari) !!}
                                </a>
                            </td>
                            <td>
                                <div class="fw-semibold">{!! CariArsip::sorot($surat->pengirim, $cari) !!}</div>
                                @if($surat->instansi_pengirim)
                                    <div class="small text-secondary">{!! CariArsip::sorot($surat->instansi_pengirim, $cari) !!}</div>
                                @endif
                            </td>
                            <td>
                                <div>{!! CariArsip::sorot(Str::limit($surat->perihal, 60), $cari) !!}</div>
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
                                <div class="d-flex gap-1 justify-content-center flex-wrap">
                                    @if($melihatSampah)
                                        <form method="POST" action="{{ route('surat-masuk.restore', $surat) }}" class="d-inline">
                                            @csrf @method('PATCH')
                                            <button type="submit" class="btn btn-sm btn-outline-success" title="Pulihkan">
                                                <i class="fas fa-undo"></i>
                                            </button>
                                        </form>
                                    @else
                                        <a href="{{ route('surat-masuk.show', $surat) }}"
                                           class="btn btn-sm btn-outline-secondary" title="Detail">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="{{ route('surat-masuk.edit', $surat) }}"
                                           class="btn btn-sm btn-outline-primary" title="Edit">
                                            <i class="fas fa-pen"></i>
                                        </a>
                                        {{-- E8/E9 "Nyahkan" = nonaktifkan arsip, bukan hapus --}}
                                        <form method="POST"
                                              action="{{ route('surat-masuk.status-arsip', $surat) }}"
                                              class="d-inline">
                                            @csrf @method('PATCH')
                                            <input type="hidden" name="status_arsip"
                                                   value="{{ $surat->status_arsip === 'aktif' ? 'inaktif' : 'aktif' }}">
                                            <button type="submit"
                                                    class="btn btn-sm btn-outline-{{ $surat->status_arsip === 'aktif' ? 'warning' : 'success' }}"
                                                    title="{{ $surat->status_arsip === 'aktif' ? 'Nyahkan (nonaktifkan)' : 'Aktifkan kembali' }}">
                                                <i class="fas fa-{{ $surat->status_arsip === 'aktif' ? 'toggle-off' : 'toggle-on' }}"></i>
                                            </button>
                                        </form>
                                        @if(Auth::user()->isAdmin())
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Nyahkan ke tempat sampah"
                                            onclick="pradanaConfirmHapus(
                                                '{{ route('surat-masuk.destroy', $surat) }}',
                                                'Pindahkan surat masuk &ldquo;{{ addslashes($surat->nomor_surat) }}&rdquo; ke tempat sampah? Lampirannya tetap tersimpan dan bisa dipulihkan admin.'
                                            )">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        @endif
                                    @endif
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
                    Menampilkan {{ $arsip->firstItem() }}–{{ $arsip->lastItem() }}
                    dari {{ $arsip->total() }} surat
                </div>
                {{ $arsip->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>
@endif

@endsection

@push('scripts')
{{-- Form hapus hidden --}}
<form id="formHapusSuratMasuk" method="POST" style="display:none;">
    @csrf @method('DELETE')
</form>

<script>
function pradanaConfirmHapus(url, pesan) {
    Swal.fire({
        title: 'Pindahkan ke Tempat Sampah?',
        html: pesan,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#64748b',
        confirmButtonText: '<i class="fas fa-trash me-1"></i>Ya, Nyahkan',
        cancelButtonText: 'Batal',
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.getElementById('formHapusSuratMasuk');
            form.action = url;
            form.submit();
        }
    });
}
</script>
@endpush
