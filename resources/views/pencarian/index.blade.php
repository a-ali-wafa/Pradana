@extends('layouts.app')

{{--
    Pencarian arsip gabungan (surat masuk + surat keluar).
    Diperbarui 4 Okt 2026 mengikuti PencarianController yang baru:
    L-15 (isi surat ikut digali), L-14/H1 (filter sama dengan daftar surat),
    H7 (pagination 20, bukan limit 50 yang memotong diam-diam).
--}}

@section('title', 'Pencarian Arsip')
@section('page-title', 'Pencarian Arsip Surat')

@section('content')

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h4 class="fw-bold mb-1"><i class="fas fa-search me-2 text-primary"></i>Pencarian Arsip</h4>
        <p class="text-secondary small mb-0">
            Menggali nomor surat, perihal, pengirim/penerima, <strong>ringkasan</strong>,
            dan <strong>isi surat keluar</strong>.
        </p>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <form method="GET" action="{{ route('pencarian.index') }}" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-secondary mb-1">Kata Kunci</label>
                <input type="text" name="q" value="{{ $kataKunci }}" class="form-control"
                       placeholder="mis. musyawadah, 001/01, atau frasa di dalam surat">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Jenis</label>
                <select name="jenis" class="form-select">
                    <option value="semua"  @selected($jenis === 'semua')>Semua jenis</option>
                    <option value="masuk"  @selected($jenis === 'masuk')>Surat Masuk</option>
                    <option value="keluar" @selected($jenis === 'keluar')>Surat Keluar</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary mb-1">Klasifikasi Primer</label>
                <select name="klasifikasi_primer_id" class="form-select">
                    <option value="">Semua klasifikasi</option>
                    @foreach($klasifikasiPrimer as $kp)
                        <option value="{{ $kp->id }}" {{ (string) $filter['klasifikasi_primer_id'] === (string) $kp->id ? 'selected' : '' }}>
                            {{ $kp->kode }} – {{ $kp->nama }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Sifat</label>
                <select name="sifat" class="form-select">
                    <option value="">Semua sifat</option>
                    @foreach(['mendesak' => 'Mendesak', 'penting' => 'Penting', 'rahasia' => 'Rahasia', 'biasa' => 'Biasa'] as $val => $label)
                        <option value="{{ $val }}" {{ $filter['sifat'] === $val ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Status Arsip</label>
                <select name="status_arsip" class="form-select">
                    <option value="">Semua status</option>
                    <option value="aktif"   {{ $filter['status_arsip'] === 'aktif'   ? 'selected' : '' }}>Aktif</option>
                    <option value="inaktif" {{ $filter['status_arsip'] === 'inaktif' ? 'selected' : '' }}>Inaktif</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Tanggal dari</label>
                <input type="date" name="dari" value="{{ $filter['dari'] }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Sampai</label>
                <input type="date" name="sampai" value="{{ $filter['sampai'] }}" class="form-control">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fas fa-search me-1"></i> Cari
                </button>
                <a href="{{ route('pencarian.index') }}" class="btn btn-outline-secondary">
                    <i class="fas fa-times me-1"></i> Bersihkan
                </a>
            </div>
        </form>
    </div>
</div>

@if(is_null($hasil))
    <div class="card">
        <div class="card-body text-center py-5 text-secondary">
            <i class="fas fa-search fa-3x mb-3 opacity-25"></i>
            <p class="mb-0">Isi kata kunci atau salah satu filter untuk mulai mencari.</p>
        </div>
    </div>
@elseif($hasil->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5 text-secondary">
            <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
            <p class="mb-1 fw-semibold">Tidak ada surat yang cocok.</p>
            <p class="small mb-0">
                Coba kata kunci yang lebih pendek, atau longgarkan filter
                (arsip yang sudah dihapus tidak ikut dicari).
            </p>
        </div>
    </div>
@else
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list me-2"></i>Hasil</span>
            <span class="badge bg-primary rounded-pill">{{ $hasil->total() }} surat</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width:90px;">Jenis</th>
                            <th style="width:150px;">Nomor Surat</th>
                            <th>Perihal &amp; kutipan</th>
                            <th style="width:150px;">Pengirim / Penerima</th>
                            <th style="width:120px;">Klasifikasi</th>
                            <th style="width:100px;">Tgl Surat</th>
                            <th class="text-center pe-4" style="width:70px;"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($hasil as $item)
                            <tr>
                                <td class="ps-4">
                                    <span class="badge text-bg-{{ $item['jenis'] === 'masuk' ? 'info' : 'primary' }}">
                                        {{ ucfirst($item['jenis']) }}
                                    </span>
                                </td>
                                <td class="small fw-semibold">{{ $item['nomor_surat'] }}</td>
                                <td>
                                    <div>{{ Str::limit($item['perihal'], 70) }}</div>
                                    @if($item['ringkasan'])
                                        <div class="small text-secondary">
                                            <i class="fas fa-quote-left opacity-50 me-1"></i>{{ Str::limit($item['ringkasan'], 110) }}
                                        </div>
                                    @endif
                                    @if($item['status_arsip'] === 'inaktif')
                                        <span class="badge text-bg-light border small mt-1">Arsip inaktif</span>
                                    @endif
                                </td>
                                <td class="small">{{ $item['lawan'] }}</td>
                                <td class="small">{{ $item['klasifikasi'] }}</td>
                                <td class="small text-secondary">
                                    {{ \Carbon\Carbon::parse($item['tanggal_surat'])->format('d-m-Y') }}
                                </td>
                                <td class="text-center pe-4">
                                    <a href="{{ $item['route'] }}" class="btn btn-sm btn-outline-secondary" title="Buka surat">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-top d-flex justify-content-between align-items-center">
                <div class="text-secondary small">
                    Halaman {{ $hasil->currentPage() }} dari {{ max(1, $hasil->lastPage()) }}
                </div>
                {{ $hasil->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
        </div>
    </div>
@endif

@endsection
