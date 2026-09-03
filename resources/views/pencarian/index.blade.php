@extends('layouts.app')

{{--
    DIPERBARUI — 1 Sep 2026. Versi sebelumnya (standalone Bootstrap sendiri)
    DIGANTI TOTAL jadi extends layouts.app, sekarang layout aslinya sudah
    diupload user — mengikuti @section/@yield yang ada di sana:
    title, page-title, content. Lihat AGENTS_HISTORY.md 12.21 (bagian update).
--}}

@section('title', 'Pencarian Arsip')
@section('page-title', 'Pencarian Arsip Surat')

@section('content')
<div class="card">
    <div class="card-body">
        <form method="GET" class="row g-2 mb-4">
            <div class="col-md-6">
                <input type="text" name="q" value="{{ $kataKunci }}" class="form-control"
                       placeholder="Cari nomor surat, perihal, pengirim/penerima...">
            </div>
            <div class="col-md-3">
                <select name="jenis" class="form-select">
                    <option value="semua" @selected($jenis === 'semua')>Semua Jenis</option>
                    <option value="masuk" @selected($jenis === 'masuk')>Surat Masuk</option>
                    <option value="keluar" @selected($jenis === 'keluar')>Surat Keluar</option>
                </select>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Cari
                </button>
            </div>
        </form>

        @if($kataKunci === '' && $hasil->isEmpty())
            <p class="text-muted mb-0">Masukkan kata kunci untuk mulai mencari.</p>
        @elseif($hasil->isEmpty())
            <p class="text-muted mb-0">Tidak ada hasil ditemukan.</p>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Jenis</th>
                            <th>Nomor Surat</th>
                            <th>Perihal</th>
                            <th>Pengirim/Penerima</th>
                            <th>Klasifikasi</th>
                            <th>Tanggal Surat</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($hasil as $item)
                        <tr>
                            <td>
                                <span class="badge {{ $item['jenis'] === 'masuk' ? 'bg-primary' : 'bg-success' }}">
                                    {{ $item['jenis'] === 'masuk' ? 'Masuk' : 'Keluar' }}
                                </span>
                            </td>
                            <td>{{ $item['nomor_surat'] }}</td>
                            <td>{{ $item['perihal'] }}</td>
                            <td>{{ $item['lawan'] }}</td>
                            <td>{{ $item['klasifikasi'] }}</td>
                            <td>{{ \Carbon\Carbon::parse($item['tanggal_surat'])->format('d-m-Y') }}</td>
                            <td>
                                <a href="{{ $item['route'] }}" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
