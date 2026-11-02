@extends('layouts.app')

@section('title', 'Dashboard - PRADANA')
@section('page-title', 'Dashboard')

@section('content')
@php
    /*
     * Identitas kantor di kepala dashboard: logo + nama instansi dari
     * Pengaturan Instansi, bukan lambang generik. Satu sumber yang sama dengan kop
     * PDF dan sidebar, diambil dari cache (PengaturanInstansi::untukTampilan())
     * supaya layar ini tidak bertambah query hanya untuk sebuah gambar.
     *
     * Tanggal hari ini ikut ditulis karena dashboard dipakai untuk menandai
     * "sampai kapan angka ini berlaku" — dan sejak zona aplikasi diubah ke WIB,
     * tanggalnya adalah tanggal menurut jam dinding kantor.
     */
    $merek = App\Models\PengaturanInstansi::untukTampilan();
@endphp

<div class="card border-0 shadow-sm mb-4">
    <div class="card-body d-flex align-items-center gap-3 py-3">
        <span class="hero-logo">
            @if (! empty($merek['logo']))
                <img src="{{ $merek['logo'] }}" alt="Logo instansi">
            @else
                <i class="fas fa-building"></i>
            @endif
        </span>
        <div class="flex-grow-1 overflow-hidden">
            <div class="fw-bold fs-5 text-truncate" title="{{ $merek['nama'] ?? '' }}">
                {{ $merek['nama'] ?: 'PRADANA — Arsip Surat Desa' }}
            </div>
            <div class="small text-secondary text-truncate">
                PRADANA · Arsip surat masuk &amp; keluar · {{ now()->translatedFormat('l, d F Y') }}
            </div>
        </div>
        <div class="d-none d-md-flex align-items-center gap-2 small text-secondary">
            <span class="badge rounded-pill text-bg-light border">{{ number_format($stats['total_surat_masuk']) }} masuk</span>
            <span class="badge rounded-pill text-bg-light border">{{ number_format($stats['total_surat_keluar']) }} keluar</span>
        </div>
    </div>
</div>

<div class="container-fluid px-0">
    {{-- Baris Kartu Statistik Utama --}}
    <div class="row g-3 mb-4">
        {{-- Total Surat Masuk --}}
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Surat Masuk</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-primary-subtle text-primary" style="width: 42px; height: 42px;">
                            <i class="fas fa-inbox fa-lg"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark">{{ number_format($stats['total_surat_masuk']) }}</h3>
                    <div class="d-flex align-items-center justify-content-between text-muted small">
                        <span><i class="fas fa-calendar-alt me-1"></i>Bulan ini: <strong>{{ $stats['surat_masuk_bulan_ini'] }}</strong></span>
                        <span class="badge bg-success-subtle text-success">{{ $stats['surat_masuk_aktif'] }} aktif</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Total Surat Keluar --}}
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Surat Keluar</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-success-subtle text-success" style="width: 42px; height: 42px;">
                            <i class="fas fa-paper-plane fa-lg"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-dark">{{ number_format($stats['total_surat_keluar']) }}</h3>
                    <div class="d-flex align-items-center justify-content-between text-muted small">
                        <span><i class="fas fa-calendar-alt me-1"></i>Bulan ini: <strong>{{ $stats['surat_keluar_bulan_ini'] }}</strong></span>
                        <span class="badge bg-success-subtle text-success">{{ $stats['surat_keluar_aktif'] }} aktif</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Surat Mendesak (Perlu Perhatian) --}}
        <div class="col-12 col-sm-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Perlu Perhatian</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-danger-subtle text-danger" style="width: 42px; height: 42px;">
                            <i class="fas fa-exclamation-circle fa-lg"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-danger">{{ number_format($stats['surat_mendesak_aktif']) }}</h3>
                    <div class="text-muted small">
                        <span>Surat sifat <strong class="text-danger">Mendesak</strong> (arsip aktif)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{--
        Blok "Aksi Cepat" (pintasan Catat Surat Masuk / Buat Surat Keluar / Cari Arsip /
        Pengaturan Instansi) sengaja TIDAK ada: pintasan itu duplikat dengan sidebar kiri
        yang selalu tampil, dan penggalian isi surat memang hidup di kotak cari tiap daftar.
    --}}

    {{-- Baris Tabel: Surat Masuk & Surat Keluar Terbaru --}}
    <div class="row g-3">
        {{-- Surat Masuk Terbaru --}}
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                    <span class="fw-bold"><i class="fas fa-inbox text-primary me-2"></i>Surat Masuk Terbaru</span>
                    <a href="{{ route('surat-masuk.index') }}" class="btn btn-sm btn-link text-decoration-none p-0">
                        Lihat Semua <i class="fas fa-arrow-right small"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nomor &amp; Perihal</th>
                                    <th>Pengirim</th>
                                    <th>Tanggal</th>
                                    <th>Sifat</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($suratMasukTerbaru as $masuk)
                                    <tr>
                                        <td>
                                            <a href="{{ route('surat-masuk.show', $masuk->id) }}" class="fw-semibold text-decoration-none text-dark d-block">
                                                {{ $masuk->nomor_surat }}
                                            </a>
                                            <small class="text-muted d-inline-block text-truncate" style="max-width: 240px;">
                                                {{ $masuk->perihal }}
                                            </small>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold">{{ $masuk->pengirim }}</div>
                                            <small class="text-muted">{{ $masuk->instansi_pengirim ?? '-' }}</small>
                                        </td>
                                        <td class="small text-nowrap">
                                            {{ $masuk->tanggal_diterima ? $masuk->tanggal_diterima->format('d/m/Y') : '-' }}
                                        </td>
                                        <td>
                                            @if($masuk->sifat === 'mendesak')
                                                <span class="badge bg-danger-subtle text-danger">Mendesak</span>
                                            @elseif($masuk->sifat === 'penting')
                                                <span class="badge bg-warning-subtle text-warning">Penting</span>
                                            @elseif($masuk->sifat === 'rahasia')
                                                <span class="badge bg-dark-subtle text-dark">Rahasia</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary">Biasa</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="fas fa-inbox fa-2x mb-2 d-block opacity-50"></i>
                                            Belum ada data surat masuk
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Surat Keluar Terbaru --}}
        <div class="col-12 col-xl-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                    <span class="fw-bold"><i class="fas fa-paper-plane text-success me-2"></i>Surat Keluar Terbaru</span>
                    <a href="{{ route('surat-keluar.index') }}" class="btn btn-sm btn-link text-decoration-none p-0">
                        Lihat Semua <i class="fas fa-arrow-right small"></i>
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Nomor &amp; Perihal</th>
                                    <th>Tujuan</th>
                                    <th>Tanggal</th>
                                    <th>Sifat</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($suratKeluarTerbaru as $keluar)
                                    <tr>
                                        <td>
                                            <a href="{{ route('surat-keluar.show', $keluar->id) }}" class="fw-semibold text-decoration-none text-dark d-block">
                                                {{ $keluar->nomor_surat }}
                                            </a>
                                            <small class="text-muted d-inline-block text-truncate" style="max-width: 240px;">
                                                {{ $keluar->perihal }}
                                            </small>
                                        </td>
                                        <td>
                                            <div class="small fw-semibold">{{ $keluar->penerima }}</div>
                                            <small class="text-muted">{{ $keluar->instansi_penerima ?? '-' }}</small>
                                        </td>
                                        <td class="small text-nowrap">
                                            {{ $keluar->tanggal_surat ? $keluar->tanggal_surat->format('d/m/Y') : '-' }}
                                        </td>
                                        <td>
                                            @if($keluar->sifat === 'mendesak')
                                                <span class="badge bg-danger-subtle text-danger">Mendesak</span>
                                            @elseif($keluar->sifat === 'penting')
                                                <span class="badge bg-warning-subtle text-warning">Penting</span>
                                            @elseif($keluar->sifat === 'rahasia')
                                                <span class="badge bg-dark-subtle text-dark">Rahasia</span>
                                            @else
                                                <span class="badge bg-secondary-subtle text-secondary">Biasa</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">
                                            <i class="fas fa-paper-plane fa-2x mb-2 d-block opacity-50"></i>
                                            Belum ada data surat keluar
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection
