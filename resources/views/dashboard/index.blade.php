@extends('layouts.app')

@section('title', 'Dashboard - PRADANA')
@section('page-title', 'Dashboard Statistik')

@section('content')
<div class="container-fluid px-0">
    {{-- Baris Kartu Statistik Utama --}}
    <div class="row g-3 mb-4">
        {{-- Total Surat Masuk --}}
        <div class="col-12 col-sm-6 col-xl-3">
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
        <div class="col-12 col-sm-6 col-xl-3">
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
        <div class="col-12 col-sm-6 col-xl-3">
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

        {{-- Status Arsip Inaktif --}}
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card h-100 border-0 shadow-sm">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted small fw-semibold text-uppercase">Arsip Inaktif</span>
                        <div class="rounded-circle d-flex align-items-center justify-content-center bg-secondary-subtle text-secondary" style="width: 42px; height: 42px;">
                            <i class="fas fa-archive fa-lg"></i>
                        </div>
                    </div>
                    <h3 class="fw-bold mb-1 text-secondary">
                        {{ number_format($stats['surat_masuk_inaktif'] + $stats['surat_keluar_inaktif']) }}
                    </h3>
                    <div class="d-flex justify-content-between text-muted small">
                        <span>Masuk: {{ $stats['surat_masuk_inaktif'] }}</span>
                        <span>Keluar: {{ $stats['surat_keluar_inaktif'] }}</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Menu Pintas / Quick Actions --}}
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-3">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-2">
                <span class="fw-bold text-secondary small text-uppercase">
                    <i class="fas fa-bolt me-1 text-warning"></i> Aksi Cepat
                </span>
                <div class="d-flex flex-wrap gap-2">
                    <a href="{{ route('surat-masuk.create') }}" class="btn btn-sm btn-primary rounded-pill px-3">
                        <i class="fas fa-plus me-1"></i> Catat Surat Masuk
                    </a>
                    <a href="{{ route('surat-keluar.create') }}" class="btn btn-sm btn-success rounded-pill px-3">
                        <i class="fas fa-plus me-1"></i> Buat Surat Keluar
                    </a>
                    <a href="{{ route('pencarian.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        <i class="fas fa-search me-1"></i> Cari Arsip
                    </a>
                    <a href="{{ route('pengaturan-instansi.edit') }}" class="btn btn-sm btn-outline-secondary rounded-pill px-3">
                        <i class="fas fa-building me-1"></i> Pengaturan Instansi
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Baris Tabel: Surat Masuk & Surat Keluar Terbaru --}}
    <div class="row g-3 mb-4">
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

    {{-- Baris Bawah: Log Aktivitas & Klasifikasi Terpopuler --}}
    <div class="row g-3">
        {{-- Log Aktivitas Terbaru --}}
        <div class="col-12 col-xl-7">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex align-items-center justify-content-between py-3">
                    <span class="fw-bold"><i class="fas fa-history text-secondary me-2"></i>Log Aktivitas Terbaru</span>
                    <small class="text-muted">10 aktivitas terakhir</small>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        @forelse($aktivitasTerbaru as $aktivitas)
                            <li class="list-group-item py-3 px-4">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="me-3">
                                        <div class="fw-semibold text-dark small mb-1">
                                            <i class="fas fa-user-circle me-1 text-primary"></i>
                                            {{ $aktivitas->user->nama_lengkap ?? 'Sistem' }}
                                        </div>
                                        <p class="mb-0 text-secondary small">{{ $aktivitas->aksi }}</p>
                                    </div>
                                    <span class="badge bg-light text-muted border small">
                                        {{ $aktivitas->created_at ? $aktivitas->created_at->diffForHumans() : '-' }}
                                    </span>
                                </div>
                            </li>
                        @empty
                            <li class="list-group-item text-center py-4 text-muted">
                                <i class="fas fa-clipboard-list fa-2x mb-2 d-block opacity-50"></i>
                                Belum ada log aktivitas tercatat
                            </li>
                        @endforelse
                    </ul>
                </div>
            </div>
        </div>

        {{-- Klasifikasi Terpopuler --}}
        <div class="col-12 col-xl-5">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white py-3">
                    <span class="fw-bold"><i class="fas fa-chart-bar text-info me-2"></i>Klasifikasi Surat Terbanyak</span>
                </div>
                <div class="card-body">
                    @forelse($klasifikasiTerpopuler as $populer)
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1 small">
                                <span class="fw-semibold text-dark">
                                    {{ $populer->primer ? $populer->primer->kode . ' - ' . $populer->primer->nama : 'Tanpa Klasifikasi' }}
                                </span>
                                <span class="badge bg-primary-subtle text-primary fw-bold">
                                    {{ $populer->total }} surat
                                </span>
                            </div>
                            @php
                                $maxTotal = $klasifikasiTerpopuler->first()->total ?? 1;
                                $percentage = $maxTotal > 0 ? min(100, round(($populer->total / $maxTotal) * 100)) : 0;
                            @endphp
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar bg-primary" role="progressbar" style="width: {{ $percentage }}%"></div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-4 text-muted">
                            <i class="fas fa-chart-pie fa-2x mb-2 d-block opacity-50"></i>
                            Belum ada data klasifikasi
                        </div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
