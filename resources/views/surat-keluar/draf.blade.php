@extends('layouts.app')

@section('title', 'Draf Surat Keluar - PRADANA')
@section('page-title', 'Isi Draf Surat Keluar')

@section('content')

<nav aria-label="breadcrumb">
    <ol class="breadcrumb small">
        <li class="breadcrumb-item"><a href="{{ route('surat-keluar.index') }}">Surat Keluar</a></li>
        <li class="breadcrumb-item"><a href="{{ route('surat-keluar.show', $suratKeluar) }}">{{ $suratKeluar->nomor_surat }}</a></li>
        <li class="breadcrumb-item active" aria-current="page">Draf Konten</li>
    </ol>
</nav>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="fas fa-pen-nib me-2"></i>Kop &amp; Isi Surat</span>
                @if($draf->exists)
                    <span class="badge text-bg-success rounded-pill">Sudah diisi</span>
                @else
                    <span class="badge text-bg-warning rounded-pill">Belum pernah diisi</span>
                @endif
            </div>
            <div class="card-body">
                @if(session('success'))
                    <div class="alert alert-success py-2 small">{{ session('success') }}</div>
                @endif

                <form method="POST" action="{{ route('surat-keluar.draf.update', $suratKeluar) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-3">
                        <label class="form-label fw-bold" for="alamat_tujuan">Alamat Tujuan</label>
                        <textarea name="alamat_tujuan" id="alamat_tujuan" rows="2"
                                  class="form-control @error('alamat_tujuan') is-invalid @enderror"
                                  placeholder="Contoh: Jl. Merdeka No. 1, Malang">{{ old('alamat_tujuan', $draf->alamat_tujuan) }}</textarea>
                        <div class="form-text">Kosongkan kalau cukup pakai kota/provinsi dari data surat.</div>
                        @error('alamat_tujuan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="salam_pembuka">Salam Pembuka</label>
                            <input type="text" name="salam_pembuka" id="salam_pembuka" maxlength="100"
                                   value="{{ old('salam_pembuka', $draf->salam_pembuka) }}"
                                   class="form-control @error('salam_pembuka') is-invalid @enderror"
                                   placeholder="Dengan hormat,">
                            @error('salam_pembuka') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="salam_penutup">Salam Penutup</label>
                            <input type="text" name="salam_penutup" id="salam_penutup" maxlength="100"
                                   value="{{ old('salam_penutup', $draf->salam_penutup) }}"
                                   class="form-control @error('salam_penutup') is-invalid @enderror"
                                   placeholder="Demikian disampaikan, terima kasih.">
                            @error('salam_penutup') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-bold" for="isi_surat">Isi Surat <span class="text-danger">*</span></label>
                        <textarea name="isi_surat" id="isi_surat" rows="14" required
                                  class="form-control @error('isi_surat') is-invalid @enderror"
                                  placeholder="Tulis isi surat di sini. Baris baru akan ikut tercetak.">{{ old('isi_surat', $draf->isi_surat) }}</textarea>
                        @error('isi_surat') <div class="invalid-feedback d-block">{{ $message }}</div> @enderror
                    </div>

                    <hr class="my-4">

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label fw-bold" for="atas_nama">Atas Nama (penandatangan)</label>
                            <input type="text" name="atas_nama" id="atas_nama" maxlength="150"
                                   value="{{ old('atas_nama', $draf->atas_nama) }}"
                                   class="form-control @error('atas_nama') is-invalid @enderror"
                                   placeholder="Nama Kepala Desa/Lurah">
                            @error('atas_nama') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold" for="jabatan_penandatangan">Jabatan Penandatangan</label>
                            <input type="text" name="jabatan_penandatangan" id="jabatan_penandatangan" maxlength="100"
                                   value="{{ old('jabatan_penandatangan', $draf->jabatan_penandatangan) }}"
                                   class="form-control @error('jabatan_penandatangan') is-invalid @enderror"
                                   placeholder="LURAH UREK-UREK">
                            @error('jabatan_penandatangan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-bold" for="nip_nik">NIP / NIK</label>
                            <input type="text" name="nip_nik" id="nip_nik" maxlength="50"
                                   value="{{ old('nip_nik', $draf->nip_nik) }}"
                                   class="form-control @error('nip_nik') is-invalid @enderror">
                            @error('nip_nik') <div class="invalid-feedback">{{ $message }}</div> @enderror
                        </div>
                    </div>

                    <div class="mt-3">
                        <label class="form-label fw-bold" for="tembusan">Tembusan</label>
                        <textarea name="tembusan" id="tembusan" rows="3" maxlength="2000"
                                  class="form-control @error('tembusan') is-invalid @enderror"
                                  placeholder="Satu baris per pihak">{{ old('tembusan', $draf->tembusan) }}</textarea>
                        @error('tembusan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="d-flex flex-wrap gap-2 mt-4">
                        <button type="submit" name="aksi" value="simpan" class="btn btn-outline-primary rounded-pill px-4">
                            <i class="fas fa-save me-1"></i> Simpan
                        </button>
                        <button type="submit" name="aksi" value="simpan_cetak" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-file-pdf me-1"></i> Simpan &amp; Pratinjau PDF
                        </button>
                        <a href="{{ route('surat-keluar.show', $suratKeluar) }}" class="btn btn-link text-decoration-none">
                            Kembali
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card mb-3">
            <div class="card-header"><i class="fas fa-info-circle me-2"></i>Data Surat (baca-saja)</div>
            <div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th class="text-secondary small">Nomor</th><td class="small">{{ $suratKeluar->nomor_surat }}</td></tr>
                    <tr><th class="text-secondary small">Perihal</th><td class="small">{{ $suratKeluar->perihal }}</td></tr>
                    <tr><th class="text-secondary small">Kepada</th><td class="small">{{ $suratKeluar->penerima }}</td></tr>
                    <tr><th class="text-secondary small">Jabatan</th><td class="small">{{ $suratKeluar->jabatan_penerima ?? '—' }}</td></tr>
                    <tr><th class="text-secondary small">Instansi</th><td class="small">{{ $suratKeluar->instansi_penerima ?? '—' }}</td></tr>
                    <tr><th class="text-secondary small">Tanggal</th><td class="small">{{ \Carbon\Carbon::parse($suratKeluar->tanggal_surat)->translatedFormat('d F Y') }}</td></tr>
                    <tr><th class="text-secondary small">Sifat</th><td class="small">{{ ucfirst($suratKeluar->sifat) }}</td></tr>
                </table>
                <a href="{{ route('surat-keluar.edit', $suratKeluar) }}" class="btn btn-sm btn-outline-secondary rounded-pill w-100 mt-3">
                    Ubah data surat
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><i class="fas fa-paperclip me-2"></i>Lampiran pada PDF</div>
            <div class="card-body">
                <p class="small mb-1">Baris "Lampiran" pada kop PDF ditulis otomatis:</p>
                <div class="fs-4 fw-bold text-primary">{{ $jumlahLampiran }} Berkas</div>
                <p class="small text-secondary mb-2">
                    Diambil dari jumlah file yang benar-benar terunggah (keputusan P6), jadi tidak bisa
                    tertulis "3 Berkas" padahal file-nya satu.
                </p>
                <a href="{{ route('surat-keluar.show', $suratKeluar) }}" class="btn btn-sm btn-link text-decoration-none p-0">
                    Kelola lampiran
                </a>
            </div>
        </div>
    </div>
</div>

@endsection
