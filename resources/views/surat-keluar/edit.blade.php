@extends('layouts.app')

@section('title', 'Edit Surat Keluar - PRADANA')
@section('page-title', 'Edit Surat Keluar')

@section('content')

<div class="row justify-content-center">
    <div class="col-xl-9 col-lg-10">

        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb small">
                <li class="breadcrumb-item"><a href="{{ route('surat-keluar.index') }}">Surat Keluar</a></li>
                <li class="breadcrumb-item"><a href="{{ route('surat-keluar.show', $suratKeluar) }}">{{ $suratKeluar->nomor_surat }}</a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>

        <div class="alert alert-warning rounded-3 border-0 shadow-sm small mb-4">
            <i class="fas fa-exclamation-triangle me-1"></i>
            <strong>Perhatian:</strong> Nomor surat <code>{{ $suratKeluar->nomor_surat }}</code> bisa dikoreksi manual jika ada kesalahan,
            namun harus tetap unik. Mengubah klasifikasi <strong>tidak</strong> mengubah nomor surat yang sudah terbit.
        </div>

        <div class="card">
            <div class="card-header">
                <i class="fas fa-pen me-2 text-primary"></i>Edit Surat Keluar
                <span class="text-secondary small fw-normal ms-2">— {{ $suratKeluar->nomor_surat }}</span>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('surat-keluar.update', $suratKeluar) }}" id="formSuratKeluar">
                    @csrf
                    @method('PUT')

                    {{-- SEKSI 1: Penerima --}}
                    <div class="mb-4">
                        <h6 class="text-secondary text-uppercase fw-bold small mb-3">
                            <i class="fas fa-user me-1"></i> Identitas Penerima
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label small fw-bold">Nama Penerima <span class="text-danger">*</span></label>
                                <input type="text" name="penerima"
                                       class="form-control @error('penerima') is-invalid @enderror"
                                       value="{{ old('penerima', $suratKeluar->penerima) }}" required>
                                @error('penerima') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Jabatan Penerima</label>
                                <input type="text" name="jabatan_penerima"
                                       class="form-control @error('jabatan_penerima') is-invalid @enderror"
                                       value="{{ old('jabatan_penerima', $suratKeluar->jabatan_penerima) }}">
                                @error('jabatan_penerima') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Instansi Penerima</label>
                                <input type="text" name="instansi_penerima"
                                       class="form-control @error('instansi_penerima') is-invalid @enderror"
                                       value="{{ old('instansi_penerima', $suratKeluar->instansi_penerima) }}">
                                @error('instansi_penerima') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Kota Tujuan</label>
                                <input type="text" name="kota_tujuan"
                                       class="form-control @error('kota_tujuan') is-invalid @enderror"
                                       value="{{ old('kota_tujuan', $suratKeluar->kota_tujuan) }}">
                                @error('kota_tujuan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold">Provinsi Tujuan</label>
                                <input type="text" name="provinsi_tujuan"
                                       class="form-control @error('provinsi_tujuan') is-invalid @enderror"
                                       value="{{ old('provinsi_tujuan', $suratKeluar->provinsi_tujuan) }}">
                                @error('provinsi_tujuan') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <hr class="text-secondary opacity-25">

                    {{-- SEKSI 2: Data Surat (termasuk nomor manual) --}}
                    <div class="mb-4">
                        <h6 class="text-secondary text-uppercase fw-bold small mb-3">
                            <i class="fas fa-file-alt me-1"></i> Data Surat
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label small fw-bold">Nomor Surat <span class="text-danger">*</span></label>
                                <input type="text" name="nomor_surat"
                                       class="form-control @error('nomor_surat') is-invalid @enderror"
                                       value="{{ old('nomor_surat', $suratKeluar->nomor_surat) }}" required>
                                @error('nomor_surat') <div class="invalid-feedback">{{ $message }}</div> @enderror
                                <div class="form-text">Ubah hanya jika ada kesalahan — harus unik.</div>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Tanggal Surat <span class="text-danger">*</span></label>
                                <input type="date" name="tanggal_surat"
                                       class="form-control @error('tanggal_surat') is-invalid @enderror"
                                       value="{{ old('tanggal_surat', \Carbon\Carbon::parse($suratKeluar->tanggal_surat)->format('Y-m-d')) }}" required>
                                @error('tanggal_surat') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Perihal <span class="text-danger">*</span></label>
                                <input type="text" name="perihal"
                                       class="form-control @error('perihal') is-invalid @enderror"
                                       value="{{ old('perihal', $suratKeluar->perihal) }}" required>
                                @error('perihal') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-12">
                                <label class="form-label small fw-bold">Ringkasan</label>
                                <textarea name="ringkasan" rows="3"
                                          class="form-control @error('ringkasan') is-invalid @enderror">{{ old('ringkasan', $suratKeluar->ringkasan) }}</textarea>
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
                                            {{ old('klasifikasi_primer_id', $suratKeluar->klasifikasi_primer_id) == $primer->id ? 'selected' : '' }}>
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
                                    @foreach(['biasa' => 'Biasa', 'penting' => 'Penting', 'mendesak' => 'Mendesak', 'rahasia' => 'Rahasia'] as $val => $label)
                                        <option value="{{ $val }}"
                                            {{ old('sifat', $suratKeluar->sifat) === $val ? 'selected' : '' }}>
                                            {{ $label }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('sifat') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <hr class="text-secondary opacity-25">

                    {{-- SEKSI 4: Berkas, Status Arsip & Lokasi --}}
                    <div class="mb-4">
                        <h6 class="text-secondary text-uppercase fw-bold small mb-3">
                            <i class="fas fa-folder me-1"></i> Berkas, Status Arsip & Lokasi
                        </h6>
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Status Berkas <span class="text-danger">*</span></label>
                                <select name="status_berkas" class="form-select @error('status_berkas') is-invalid @enderror" required>
                                    <option value="asli"    {{ old('status_berkas', $suratKeluar->status_berkas) === 'asli'    ? 'selected' : '' }}>Asli</option>
                                    <option value="salinan" {{ old('status_berkas', $suratKeluar->status_berkas) === 'salinan' ? 'selected' : '' }}>Salinan</option>
                                </select>
                                @error('status_berkas') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-3">
                                <label class="form-label small fw-bold">Status Arsip <span class="text-danger">*</span></label>
                                <select name="status_arsip" class="form-select @error('status_arsip') is-invalid @enderror" required>
                                    <option value="aktif"   {{ old('status_arsip', $suratKeluar->status_arsip) === 'aktif'   ? 'selected' : '' }}>Aktif</option>
                                    <option value="inaktif" {{ old('status_arsip', $suratKeluar->status_arsip) === 'inaktif' ? 'selected' : '' }}>Inaktif</option>
                                </select>
                                @error('status_arsip') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                            <div class="col-md-5">
                                <label class="form-label small fw-bold">Lokasi Fisik</label>
                                <input type="text" name="lokasi_fisik"
                                       class="form-control @error('lokasi_fisik') is-invalid @enderror"
                                       value="{{ old('lokasi_fisik', $suratKeluar->lokasi_fisik) }}">
                                @error('lokasi_fisik') <div class="invalid-feedback">{{ $message }}</div> @enderror
                            </div>
                        </div>
                    </div>

                    <div class="d-flex gap-2 justify-content-end border-top pt-3 mt-2">
                        <a href="{{ route('surat-keluar.show', $suratKeluar) }}" class="btn btn-outline-secondary rounded-pill px-4">
                            <i class="fas fa-times me-1"></i> Batal
                        </a>
                        <button type="submit" class="btn btn-primary rounded-pill px-4">
                            <i class="fas fa-save me-1"></i> Simpan Perubahan
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

@endsection

@push('scripts')
@include('surat-keluar._klasifikasi_cascade_js', [
    'oldPrimerId' => old('klasifikasi_primer_id', $suratKeluar->klasifikasi_primer_id),
    'oldSekId'    => old('klasifikasi_sekunder_id', $suratKeluar->klasifikasi_sekunder_id),
    'oldTerId'    => old('klasifikasi_tersier_id', $suratKeluar->klasifikasi_tersier_id),
])
@endpush
