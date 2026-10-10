@extends('layouts.app')

@section('title', 'Laporan & Buku Agenda - PRADANA')
@section('page-title', 'Laporan & Buku Agenda')

@section('content')

<div class="mb-4">
    <h4 class="fw-bold mb-1"><i class="fas fa-file-alt me-2 text-primary"></i>Laporan &amp; Buku Agenda</h4>
    <p class="text-secondary small mb-0">
        Rekap per periode (CSV, bisa dibuka di Excel/LibreOffice) dan Buku Agenda Surat
        (PDF siap cetak). Dua-duanya mengikuti filter di bawah.
    </p>
</div>

<div class="card mb-4">
    <div class="card-body">
        @php
            // Dua tombol unduh di bawah memakai filter yang sama dengan form,
            // tapi periodenya selalu dieksplisitkan (bulan berjalan kalau kosong).
            $argumenUnduh = array_merge(request()->query(), [
                'dari' => $periode[0]->toDateString(),
                'sampai' => $periode[1]->toDateString(),
            ]);
        @endphp
        <form method="GET" action="{{ route('laporan.index') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Dari Tanggal</label>
                <input type="date" name="dari" value="{{ request('dari', $periode[0]->toDateString()) }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Sampai Tanggal</label>
                <input type="date" name="sampai" value="{{ request('sampai', $periode[1]->toDateString()) }}" class="form-control">
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Jenis</label>
                <select name="jenis" class="form-select">
                    <option value="semua"  {{ ($filter['jenis'] ?? 'semua') === 'semua' ? 'selected' : '' }}>Semua jenis</option>
                    <option value="masuk"  {{ ($filter['jenis'] ?? '') === 'masuk' ? 'selected' : '' }}>Surat Masuk</option>
                    <option value="keluar" {{ ($filter['jenis'] ?? '') === 'keluar' ? 'selected' : '' }}>Surat Keluar</option>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-secondary mb-1">Klasifikasi Primer</label>
                <select name="klasifikasi_primer_id" class="form-select">
                    <option value="">Semua klasifikasi</option>
                    @foreach($klasifikasiPrimer as $kp)
                        <option value="{{ $kp->id }}" {{ (string) ($filter['klasifikasi_primer_id'] ?? '') === (string) $kp->id ? 'selected' : '' }}>
                            {{ $kp->kode }} – {{ $kp->nama }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small fw-bold text-secondary mb-1">Status Arsip</label>
                <select name="status_arsip" class="form-select">
                    <option value="">Semua</option>
                    <option value="aktif"   {{ ($filter['status_arsip'] ?? '') === 'aktif' ? 'selected' : '' }}>Aktif</option>
                    <option value="inaktif" {{ ($filter['status_arsip'] ?? '') === 'inaktif' ? 'selected' : '' }}>Inaktif</option>
                </select>
            </div>
            <div class="col-md-12 d-flex flex-wrap gap-2 pt-2 align-items-center">
                <button type="submit" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="fas fa-filter me-1"></i> Terapkan Filter
                </button>
                <a href="{{ route('laporan.rekap', $argumenUnduh) }}"
                   class="btn btn-success rounded-pill px-4">
                    <i class="fas fa-file-csv me-1"></i> Unduh Rekap CSV
                </a>
                <a href="{{ route('laporan.agenda', $argumenUnduh) }}"
                   target="_blank"
                   class="btn btn-primary rounded-pill px-4">
                    <i class="fas fa-book me-1"></i> Cetak Buku Agenda (PDF)
                </a>
                <span class="ms-2 text-secondary small">
                    {{ $jumlah }} surat cocok dengan filter ini.
                </span>
            </div>
        </form>
    </div>
</div>

@if($jumlah === 0)
    <div class="alert alert-warning small">
        <i class="fas fa-exclamation-triangle me-1"></i>
        Tidak ada surat pada periode {{ $periode[0]->translatedFormat('d F Y') }}
        sampai {{ $periode[1]->translatedFormat('d F Y') }} dengan filter ini.
        Rekap CSV dan Buku Agenda tetap bisa diunduh, tapi isinya kosong —
        perluas rentang tanggal atau ubah filternya.
    </div>
@endif

{{-- Plafon Buku Agenda (config/laporan.php). Dinyatakan DI LAYAR lebih dulu supaya
     orang kantor tidak menekan tombol lalu menunggu lama untuk sesuatu yang akan
     ditolak. Rekap CSV tidak dibatasi: barisnya dialirkan dan kolomnya sudah
     dipangkas, jadi ia tidak menabrak memory_limit dompdf. --}}
@if($jumlah > $agendaBatas)
    <div class="alert alert-danger small">
        <i class="fas fa-ban me-1"></i>
        Buku Agenda <strong>tidak akan dicetak</strong> untuk periode ini:
        {{ $jumlah }} surat melewati batas {{ $agendaBatas }} baris.
        Persempit periodenya — misalnya satu semester (Januari–Juni atau Juli–Desember) —
        lalu unduh lagi. Rekap CSV tetap bisa diunduh.
    </div>
@elseif($jumlah > ($agendaBatas / 2))
    <div class="alert alert-warning small">
        <i class="fas fa-hourglass-half me-1"></i>
        {{ $jumlah }} surat akan masuk Buku Agenda. Dokumen sepanjang ini butuh waktu
        lama dan memakan memori server; kalau terasa lambat atau gagal, cetak per
        semester saja (batasnya {{ $agendaBatas }} baris).
    </div>
@endif

<div class="alert alert-light border small">
    <i class="fas fa-info-circle me-1"></i>
    Periode bawaan adalah bulan berjalan. Rekap CSV memakai pemisah <code>;</code>
    supaya angka dan tanggal tidak tertukar saat dibuka Excel versi Indonesia.
</div>

@endsection
