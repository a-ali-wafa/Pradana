@extends('layouts.app')

@section('title', 'Ajukan Pemusnahan Arsip - PRADANA')
@section('page-title', 'Ajukan Pemusnahan Arsip')

@section('content')

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-fire me-2 text-danger"></i>Daftar Arsip yang Memenuhi Syarat Dimusnahkan</span>
                <a href="{{ route('pemusnahan-arsip.index') }}" class="btn btn-sm btn-outline-secondary rounded-pill">
                    <i class="fas fa-arrow-left me-1"></i> Kembali
                </a>
            </div>
            <div class="card-body">
                <div class="alert alert-warning small rounded-3">
                    <i class="fas fa-info-circle me-1"></i>
                    Daftar ini hanya memuat arsip yang <strong>sudah lewat retensi 5 tahun</strong>
                    <strong>dan sudah dinonaktifkan</strong>. Setelah pengajuan disetujui admin,
                    surat beserta seluruh lampirannya <strong>dihapus permanen</strong> dan tidak bisa
                    dipulihkan — Berita Acara pemusnahan dicetak sebagai bukti.
                </div>

                @if(count($kandidat) === 0)
                    <div class="text-center py-5 text-secondary">
                        <i class="fas fa-shield-alt fa-3x mb-3 opacity-25"></i>
                        <p class="fw-semibold mb-1">Tidak ada arsip yang bisa dimusnahkan sekarang.</p>
                        <p class="small mb-0">
                            Arsip harus berumur lebih dari 5 tahun (dihitung dari tanggal surat)
                            dan sudah dinonaktifkan lewat tombol "Nonaktifkan" di halaman surat.
                        </p>
                    </div>
                @else
                    <form method="POST" action="{{ route('pemusnahan-arsip.store') }}" id="form-pemusnahan"
                          data-konfirmasi="Belum ada arsip yang dipilih."
                          data-konfirmasi-judul="Ajukan pemusnahan?"
                          data-konfirmasi-catatan="Setelah disetujui admin, surat dan lampirannya tidak bisa dipulihkan."
                          data-konfirmasi-ya="Ya, Ajukan">
                        @csrf

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="cek-semua">
                                <label class="form-check-label small fw-semibold" for="cek-semua">Pilih semua</label>
                            </div>
                            <div class="small text-secondary">
                                Dipilih: <strong id="jumlah-terpilih">0</strong> arsip
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-hover align-middle">
                                <thead>
                                    <tr>
                                        <th style="width:40px;"></th>
                                        <th>Jenis</th>
                                        <th>Nomor Surat</th>
                                        <th>Perihal</th>
                                        <th>Tanggal Surat</th>
                                        <th>Umur</th>
                                        <th class="text-center">Lampiran</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($kandidat as $arsip)
                                        <tr>
                                            <td>
                                                <input class="form-check-input cek-arsip" type="checkbox"
                                                       name="arsip[]" value="{{ $arsip->jenis }}:{{ $arsip->id }}">
                                            </td>
                                            <td>
                                                <span class="badge text-bg-{{ $arsip->jenis === 'masuk' ? 'info' : 'primary' }}">
                                                    {{ ucfirst($arsip->jenis) }}
                                                </span>
                                            </td>
                                            <td class="small fw-semibold">{{ $arsip->nomor_surat }}</td>
                                            <td class="small">{{ Str::limit($arsip->perihal, 60) }}</td>
                                            <td class="small">{{ $arsip->tanggal_surat->translatedFormat('d M Y') }}</td>
                                            <td class="small text-secondary">{{ $arsip->tanggal_surat->diffForHumans() }}</td>
                                            <td class="text-center small">{{ $arsip->lampiran }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="row g-3 mt-1">
                            <div class="col-md-6">
                                <label for="alasan" class="form-label small fw-semibold">Alasan Pemusnahan</label>
                                <textarea class="form-control" id="alasan" name="alasan" rows="3" maxlength="2000"
                                          placeholder="Misal: telah melewati jadwal retensi 5 tahun sesuai keputusan bersama Kepala Desa dan..."
                                          >{{ old('alasan') }}</textarea>
                            </div>
                            <div class="col-md-3">
                                <label for="tanggal_pelaksanaan" class="form-label small fw-semibold">Rencana Tanggal Pelaksanaan</label>
                                <input type="date" class="form-control" id="tanggal_pelaksanaan"
                                       name="tanggal_pelaksanaan" value="{{ old('tanggal_pelaksanaan') }}">
                            </div>
                        </div>

                        <div class="d-flex justify-content-end gap-2 mt-4">
                            <a href="{{ route('pemusnahan-arsip.index') }}" class="btn btn-outline-secondary rounded-pill">Batal</a>
                            <button type="submit" class="btn btn-danger rounded-pill" id="btn-ajukan">
                                <i class="fas fa-paper-plane me-1"></i> Ajukan ke Admin
                            </button>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
(function () {
    const cekSemua = document.getElementById('cek-semua');
    const daftar = Array.from(document.querySelectorAll('.cek-arsip'));
    const penjumlah = document.getElementById('jumlah-terpilih');
    const form = document.getElementById('form-pemusnahan');

    if (! form) return;

    function perbaruiJumlah() {
        const n = daftar.filter((c) => c.checked).length;
        penjumlah.textContent = n;
        if (cekSemua) {
            cekSemua.checked = n > 0 && n === daftar.length;
            cekSemua.indeterminate = n > 0 && n < daftar.length;
        }

        // Helper bersama (public/js/pradana-arsip.js) yang bertanya; halaman ini
        // hanya menulis angkanya ke atribut. Dulu tempat ini punya listener submit
        // sendiri dengan dua dialog konfirmasi — itu membuat jumlah arsip yang akan
        // dimusnahkan hanya diketahui JavaScript, dan tanpa JS tombolnya tetap
        // mengirim form kosong (server yang menolak, itu benar). Sekarang pesannya
        // ada di HTML dan JS cuma memperbarui angkanya.
        form.setAttribute(
            'data-konfirmasi',
            n === 0
                ? 'Belum ada arsip yang dipilih — centang minimal satu baris.'
                : n + ' arsip akan diajukan ke admin untuk dimusnahkan.'
        );
    }

    daftar.forEach((c) => c.addEventListener('change', perbaruiJumlah));
    if (cekSemua) {
        cekSemua.addEventListener('change', function () {
            daftar.forEach((c) => { c.checked = cekSemua.checked; });
            perbaruiJumlah();
        });
    }

    perbaruiJumlah();
})();
</script>
@endpush
