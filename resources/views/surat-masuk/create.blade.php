@extends('layouts.app')

@section('title', 'Tambah Surat Masuk - PRADANA')
@section('page-title', 'Tambah Surat Masuk')

@section('content')

<div class="row justify-content-center">
    <div class="col-xl-9 col-lg-10">

        {{-- Breadcrumb --}}
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="{{ route('surat-masuk.index') }}">Surat Masuk</a></li>
                <li class="breadcrumb-item active">Tambah</li>
            </ol>
        </nav>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-plus me-2 text-primary"></i>Form Surat Masuk Baru
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('surat-masuk.store') }}" id="formSuratMasuk">
                    @csrf

                    {{-- SEKSI 1: Identitas Pengirim --}}
                    <div class="mb-4">
                        <h6 class="text-secondary text-uppercase fw-bold small mb-3">
                            <i class="fas fa-user me-1"></i> Identitas Pengirim
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label small fw-bold">Nama Pengirim <span class="text-danger">*</span></label>
                                <input type="text" name="pengirim" class="form-control @error('pengirim') is-invalid @enderror"
                                       value="{{ old('pengirim') }}" placeholder="Nama pengirim / penanggung jawab" required>
                                @error('pengirim') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Jabatan Pengirim</label>
                                <input type="text" name="jabatan_pengirim" class="form-control @error('jabatan_pengirim') is-invalid @enderror"
                                       value="{{ old('jabatan_pengirim') }}" placeholder="Opsional">
                                @error('jabatan_pengirim') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Instansi Pengirim</label>
                                <input type="text" name="instansi_pengirim" class="form-control @error('instansi_pengirim') is-invalid @enderror"
                                       value="{{ old('instansi_pengirim') }}" placeholder="Opsional">
                                @error('instansi_pengirim') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Kota Asal</label>
                                <input type="text" name="kota_asal" class="form-control @error('kota_asal') is-invalid @enderror"
                                       value="{{ old('kota_asal') }}" placeholder="Opsional">
                                @error('kota_asal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Provinsi Asal</label>
                                <input type="text" name="provinsi_asal" class="form-control @error('provinsi_asal') is-invalid @enderror"
                                       value="{{ old('provinsi_asal') }}" placeholder="Opsional">
                                @error('provinsi_asal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <hr class="text-secondary opacity-25">

                    {{-- SEKSI 2: Data Surat --}}
                    <div class="mb-4">
                        <h6 class="text-secondary text-uppercase fw-bold small mb-3">
                            <i class="fas fa-file-alt me-1"></i> Data Surat
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label small fw-bold">Nomor Surat <span class="text-danger">*</span></label>
                                <input type="text" name="nomor_surat" class="form-control @error('nomor_surat') is-invalid @enderror"
                                       value="{{ old('nomor_surat') }}" placeholder="Contoh: 001/DS/I/2026" required>
                                @error('nomor_surat') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Tanggal Surat <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_surat" id="tanggal_surat"
                                       class="form-control @error('tanggal_surat') is-invalid @enderror"
                                       value="{{ old('tanggal_surat') }}" required>
                                @error('tanggal_surat') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Tanggal Diterima <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_diterima" id="tanggal_diterima"
                                       class="form-control @error('tanggal_diterima') is-invalid @enderror"
                                       value="{{ old('tanggal_diterima', date('Y-m-d')) }}" required>
                                @error('tanggal_diterima') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">Tidak boleh lebih awal dari tanggal surat</div>
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Perihal <span class="text-danger">*</span></label>
                                <input type="text" name="perihal" class="form-control @error('perihal') is-invalid @enderror"
                                       value="{{ old('perihal') }}" placeholder="Perihal/pokok surat" required>
                                @error('perihal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Ringkasan Isi Surat</label>
                                <textarea name="ringkasan" rows="3"
                                          class="form-control @error('ringkasan') is-invalid @enderror"
                                          placeholder="Ringkasan singkat isi surat (opsional)">{{ old('ringkasan') }}</textarea>
                                @error('ringkasan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <hr class="text-secondary opacity-25">

                    {{-- SEKSI 3: Klasifikasi & Sifat --}}
                    <div class="mb-4">
                        <h6 class="text-secondary text-uppercase fw-bold small mb-3">
                            <i class="fas fa-tags me-1"></i> Klasifikasi & Sifat
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Klasifikasi Primer <span class="text-danger">*</span></label>
                                <select name="klasifikasi_primer_id" id="klasifikasi_primer_id"
                                        class="form-select @error('klasifikasi_primer_id') is-invalid @enderror" required>
                                    <option value="">-- Pilih Primer --</option>
                                    @foreach($klasifikasiPrimer as $primer)
                                        <option value="{{ $primer->id }}"
                                            data-sekunder="{{ $primer->sekunder->toJson() }}"
                                            {{ old('klasifikasi_primer_id') == $primer->id ? 'selected' : '' }}>
                                            {{ $primer->kode }} – {{ $primer->nama }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('klasifikasi_primer_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Klasifikasi Sekunder</label>
                                <select name="klasifikasi_sekunder_id" id="klasifikasi_sekunder_id"
                                        class="form-select @error('klasifikasi_sekunder_id') is-invalid @enderror">
                                    <option value="">-- Pilih Sekunder (opsional) --</option>
                                </select>
                                @error('klasifikasi_sekunder_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Klasifikasi Tersier</label>
                                <select name="klasifikasi_tersier_id" id="klasifikasi_tersier_id"
                                        class="form-select @error('klasifikasi_tersier_id') is-invalid @enderror">
                                    <option value="">-- Pilih Tersier (opsional) --</option>
                                </select>
                                @error('klasifikasi_tersier_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Sifat Surat <span class="text-danger">*</span></label>
                                <select name="sifat" class="form-select @error('sifat') is-invalid @enderror" required>
                                    <option value="">-- Pilih Sifat --</option>
                                    @foreach(['biasa' => 'Biasa', 'penting' => 'Penting', 'mendesak' => 'Mendesak', 'rahasia' => 'Rahasia'] as $val => $label)
                                        <option value="{{ $val }}" {{ old('sifat') === $val ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('sifat') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <hr class="text-secondary opacity-25">

                    {{-- SEKSI 4: Berkas & Lokasi --}}
                    <div class="mb-4">
                        <h6 class="text-secondary text-uppercase fw-bold small mb-3">
                            <i class="fas fa-folder me-1"></i> Berkas & Lokasi Fisik
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Status Berkas <span class="text-danger">*</span></label>
                                <select name="status_berkas" class="form-select @error('status_berkas') is-invalid @enderror" required>
                                    <option value="">-- Pilih --</option>
                                    <option value="asli"    {{ old('status_berkas') === 'asli'    ? 'selected' : '' }}>Asli</option>
                                    <option value="salinan" {{ old('status_berkas') === 'salinan' ? 'selected' : '' }}>Salinan</option>
                                </select>
                                @error('status_berkas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-bold">Lokasi Fisik</label>
                                <input type="text" name="lokasi_fisik" class="form-control @error('lokasi_fisik') is-invalid @enderror"
                                       value="{{ old('lokasi_fisik') }}" placeholder="Contoh: Lemari A, Box 3 (opsional)">
                                @error('lokasi_fisik') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end border-top pt-3 mt-2">
                        <a href="{{ route('surat-masuk.index') }}" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="fas fa-times me-1"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-save me-1"></i> Simpan Surat Masuk
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

@endsection

@push('scripts')
@include('surat-masuk._klasifikasi_cascade_js', [
    'oldPrimerId'   => old('klasifikasi_primer_id'),
    'oldSekId'      => old('klasifikasi_sekunder_id'),
    'oldTerId'      => old('klasifikasi_tersier_id'),
])
@endpush
