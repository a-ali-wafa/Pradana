@extends('layouts.app')

@section('title', 'Pengajuan Hapus Lampiran - PRADANA')
@section('page-title', 'Pengajuan Hapus Lampiran')

@push('styles')
<style>
    .card-pengajuan {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        background: white;
        transition: box-shadow .15s;
    }
    .card-pengajuan:hover { box-shadow: 0 4px 15px rgba(0,0,0,.06); }
    .lampiran-meta { font-size: .82rem; color: #64748b; }
    .badge-menunggu { background: #fef3c7; color: #d97706; }
</style>
@endpush

@section('content')

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="fw-bold mb-1">
            <i class="fas fa-trash-alt me-2 text-danger"></i>Pengajuan Hapus Lampiran
        </h4>
        <p class="text-secondary small mb-0">
            @if($statusAktif === 'menunggu')
                <span class="fw-semibold text-danger">{{ $pengajuanList->total() }}</span>
                pengajuan menunggu persetujuan Anda.
            @else
                Menampilkan pengajuan berstatus <strong>{{ $statusAktif }}</strong>
                ({{ $pengajuanList->total() }} data).
            @endif
        </p>
    </div>

    {{-- L-23: riwayat yang sudah diputuskan tetap bisa dibuka, tidak hilang dari layar. --}}
    <div class="btn-group" role="group" aria-label="Filter status pengajuan">
        @foreach(['semua' => 'Semua', 'menunggu' => 'Menunggu', 'disetujui' => 'Disetujui', 'ditolak' => 'Ditolak'] as $nilai => $label)
            <a href="{{ route('pengajuan-hapus-lampiran.index', ['status' => $nilai]) }}"
               class="btn btn-sm {{ $statusAktif === $nilai ? 'btn-primary' : 'btn-outline-secondary' }}">
                {{ $label }}
            </a>
        @endforeach
    </div>
</div>

@if($pengajuanList->isEmpty())
    <div class="card">
        <div class="card-body text-center py-5 text-secondary">
            <i class="fas fa-check-circle fa-3x mb-3 text-success opacity-50"></i>
            <p class="mb-0 fw-semibold">Tidak ada pengajuan berstatus "{{ $statusAktif }}".</p>
            <p class="small mt-1">Ganti filter di kanan atas untuk melihat riwayat yang sudah diproses.</p>
        </div>
    </div>
@else
    <div class="d-flex flex-column gap-3">
        @foreach($pengajuanList as $item)
        @php
            $lampiran = $item->lampiran;
            $surat    = $lampiran?->lampiranable;
            $isMasuk  = $surat instanceof \App\Models\SuratMasuk;
        @endphp
        <div class="card-pengajuan p-4">
            <div class="row g-3 align-items-start">

                {{-- Kolom kiri: info lampiran & surat --}}
                <div class="col-lg-8">
                    <div class="d-flex align-items-start gap-3">
                        <div class="rounded-circle bg-danger bg-opacity-10 text-danger d-flex align-items-center justify-content-center flex-shrink-0"
                             style="width:44px;height:44px;">
                            <i class="fas fa-file fa-lg"></i>
                        </div>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-bold text-truncate">
                                {{ $lampiran?->nama_file ?? $item->nama_file_snapshot }}
                            </div>
                            @if(! $lampiran)
                                <span class="badge text-bg-danger small mt-1">
                                    File lampiran sudah tidak ada di database
                                </span>
                            @endif

                            {{-- Surat induk --}}
                            @if($surat)
                            <div class="lampiran-meta mt-2">
                                <i class="fas fa-{{ $isMasuk ? 'inbox' : 'paper-plane' }} me-1"></i>
                                <strong>Surat {{ $isMasuk ? 'Masuk' : 'Keluar' }}:</strong>
                                @if($isMasuk)
                                    <a href="{{ route('surat-masuk.show', $surat) }}" class="text-decoration-none">
                                        {{ $surat->nomor_surat }}
                                    </a>
                                    — {{ Str::limit($surat->perihal, 50) }}
                                    <span class="ms-2 text-secondary">
                                        (diterima {{ \Carbon\Carbon::parse($surat->tanggal_diterima)->translatedFormat('d M Y') }},
                                        {{ \Carbon\Carbon::parse($surat->tanggal_diterima)->diffForHumans() }})
                                    </span>
                                @else
                                    <a href="{{ route('surat-keluar.show', $surat) }}" class="text-decoration-none">
                                        {{ $surat->nomor_surat }}
                                    </a>
                                    — {{ Str::limit($surat->perihal, 50) }}
                                    <span class="ms-2 text-secondary">
                                        ({{ \Carbon\Carbon::parse($surat->tanggal_surat)->translatedFormat('d M Y') }},
                                        {{ \Carbon\Carbon::parse($surat->tanggal_surat)->diffForHumans() }})
                                    </span>
                                @endif
                            </div>
                            @else
                            <div class="lampiran-meta mt-1">
                                <span class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Surat induk tidak ditemukan</span>
                            </div>
                            @endif

                            {{-- Pengaju & waktu --}}
                            <div class="lampiran-meta mt-2">
                                <i class="fas fa-user me-1"></i>
                                Diajukan oleh <strong>{{ $item->pengaju?->nama_lengkap ?? '—' }}</strong>
                                · {{ $item->created_at->translatedFormat('d M Y, H:i') }}
                                ({{ $item->created_at->diffForHumans() }})
                            </div>

                            {{-- Alasan --}}
                            @if($item->alasan)
                            <div class="mt-2 p-2 rounded" style="background:#f8fafc; border-left:3px solid #e2e8f0;">
                                <span class="lampiran-meta fw-semibold"><i class="fas fa-comment-alt me-1"></i>Alasan:</span>
                                <span class="lampiran-meta ms-1">{{ $item->alasan }}</span>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Kolom kanan: aksi (hanya untuk pengajuan yang belum diputuskan) --}}
                <div class="col-lg-4">
                    <div class="d-flex flex-column gap-2">
                        @if($item->status !== 'menunggu')
                            <span class="badge rounded-pill {{ $item->status === 'disetujui' ? 'text-bg-danger' : 'text-bg-secondary' }} align-self-start">
                                <i class="fas fa-{{ $item->status === 'disetujui' ? 'trash' : 'ban' }} me-1"></i>
                                Sudah {{ $item->status }}
                            </span>
                            <div class="lampiran-meta">
                                Diproses oleh <strong>{{ $item->pemroses?->nama_lengkap ?? '—' }}</strong>
                                · {{ $item->diproses_pada?->translatedFormat('d M Y, H:i') ?? '—' }}
                            </div>
                            @if($item->catatan_admin)
                                <div class="p-2 rounded" style="background:#f8fafc; border-left:3px solid #cbd5e1;">
                                    <span class="lampiran-meta fw-semibold">Catatan admin:</span>
                                    <div class="lampiran-meta">{{ $item->catatan_admin }}</div>
                                </div>
                            @endif
                        @else
                        {{-- Konfirmasi tidak lagi ditulis per halaman. Nama berkas
                             masuk ke `data-konfirmasi` sebagai TEKS (helper bersama
                             menampilkannya lewat `text` SweetAlert2), bukan ke `html:`
                             seperti bentuk lama — padahal nama berkas adalah input
                             pengguna, jadi sisipan HTML di situ adalah jalur XSS. --}}
                        <form method="POST"
                              action="{{ route('pengajuan-hapus-lampiran.setujui', $item) }}"
                              data-konfirmasi="File &raquo;{{ $lampiran?->nama_file ?? $item->nama_file_snapshot }}&raquo; akan dihapus dari penyimpanan arsip dan tidak bisa dipulihkan."
                              data-konfirmasi-judul="Setujui Penghapusan?"
                              data-konfirmasi-ya="Ya, Hapus Permanen">
                            @csrf
                            <button type="submit" class="btn btn-danger w-100 rounded-pill">
                                <i class="fas fa-check me-1"></i> Setujui & Hapus Lampiran
                            </button>
                        </form>

                        {{-- Form Tolak: bloknya dibuka oleh helper bersama, dan tanpa
                             JavaScript form ini tetap terlihat sehingga staf bisa menolak. --}}
                        <button type="button"
                                class="btn btn-outline-secondary w-100 rounded-pill"
                                data-pengubah="#form-tolak-{{ $item->id }}" aria-expanded="false">
                            <i class="fas fa-times me-1"></i> Tolak
                        </button>

                        <div id="form-tolak-{{ $item->id }}" data-pradana-tertutup>
                            <form method="POST"
                                  action="{{ route('pengajuan-hapus-lampiran.tolak', $item) }}"
                                  class="mt-1">
                                @csrf
                                <textarea name="catatan_admin" class="form-control form-control-sm mb-2"
                                          rows="2" placeholder="Alasan penolakan (opsional)"></textarea>
                                <button type="submit" class="btn btn-secondary btn-sm w-100 rounded-pill">
                                    <i class="fas fa-times me-1"></i> Konfirmasi Tolak
                                </button>
                            </form>
                        </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
        @endforeach
    </div>

    @if($pengajuanList->hasPages())
        <div class="mt-4">{{ $pengajuanList->links() }}</div>
    @endif
@endif

@endsection

