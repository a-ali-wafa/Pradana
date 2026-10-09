@php
    /*
     * Tabel daftar arsip GABUNGAN (surat masuk + surat keluar dalam satu urutan).
     *
     * Isinya model SuratMasuk atau SuratKeluar, dibedakan lewat atribut
     * `jenis_arsip` yang dipasang DaftarArsipGabungan::muat(). Baris ini sengaja
     * ringkas: tidak ada tombol Nyahkan/Reset PIN di daftar gabungan, karena
     * aksi destruktif butuh konteks halaman suratnya sendiri (L-05). Yang mau
     * menghapus/memusnahkan masuk lewat "Detail".
     *
     * `CariArsip::sorot()` men-escape teks LEBIH DULU baru menyisipkan <mark>,
     * jadi `{!! !!}` di sini aman untuk isi surat buatan user. Pakai `{{ }}`
     * biasa kalau ragu.
     */
    use App\Support\CariArsip;
@endphp

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-layer-group me-2"></i>Semua arsip</span>
        <span class="badge bg-primary rounded-pill">{{ $arsip->count() }} dari {{ $arsip->total() }}</span>
    </div>
    <div class="card-body p-0">
        @if($arsip->isEmpty())
            <div class="text-center py-5 text-secondary">
                <i class="fas fa-inbox fa-3x mb-3 opacity-25"></i>
                <p class="mb-0">
                    @if($cari)
                        Tidak ada surat masuk <strong>maupun</strong> keluar yang cocok dengan
                        "{{ $cari }}". Cek lagi ejaannya, atau buka tab Surat Masuk / Surat Keluar
                        untuk menyaring per jenis.
                    @elseif($usang)
                        Tidak ada arsip yang lewat retensi 5 tahun dengan filter ini.
                    @else
                        Belum ada surat terdaftar. Mulai dari "Tambah Surat Masuk" atau "Surat Keluar".
                    @endif
                </p>
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-4" style="width:120px;">Tgl Surat</th>
                            <th style="width:90px;">Jenis</th>
                            <th style="width:150px;">No. Surat</th>
                            <th>Perihal</th>
                            <th>Pengirim / Penerima</th>
                            <th style="width:100px;">Sifat</th>
                            <th style="width:90px;">Status</th>
                            <th class="text-center pe-4" style="width:90px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($arsip as $surat)
                            @php $masuk = $surat->jenis_arsip === 'masuk'; @endphp
                            <tr>
                                <td class="ps-4 text-secondary small">
                                    {{ $surat->tanggal_surat?->format('d M Y') }}
                                </td>
                                <td>
                                    <span class="badge text-bg-{{ $masuk ? 'info' : 'secondary' }} rounded-pill">
                                        {{ $masuk ? 'Masuk' : 'Keluar' }}
                                    </span>
                                </td>
                                <td>
                                    <a href="{{ route($masuk ? 'surat-masuk.show' : 'surat-keluar.show', $surat) }}"
                                       class="fw-semibold text-decoration-none text-primary">
                                        {!! CariArsip::sorot($surat->nomor_surat, $cari) !!}
                                    </a>
                                </td>
                                <td>
                                    {!! CariArsip::sorot(Str::limit($surat->perihal, 60), $cari) !!}
                                    @if($surat->primer)
                                        <span class="badge text-bg-light border small">
                                            {{ $surat->primer->kode }}
                                            @if($surat->sekunder) · {{ $surat->sekunder->kode }} @endif
                                        </span>
                                    @endif
                                </td>
                                <td>
                                    <div class="fw-semibold">{{ $masuk ? $surat->pengirim : $surat->penerima }}</div>
                                    @php $instansi = $masuk ? $surat->instansi_pengirim : $surat->instansi_penerima; @endphp
                                    @if($instansi)
                                        <div class="small text-secondary">{{ $instansi }}</div>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge rounded-pill px-2 py-1 badge-sifat-{{ $surat->sifat }}">
                                        {{ ucfirst($surat->sifat) }}
                                    </span>
                                </td>
                                <td>
                                    <span class="badge rounded-pill px-2 py-1 badge-arsip-{{ $surat->status_arsip }}">
                                        {{ ucfirst($surat->status_arsip) }}
                                    </span>
                                </td>
                                <td class="text-center pe-4">
                                    <a href="{{ route($masuk ? 'surat-masuk.show' : 'surat-keluar.show', $surat) }}"
                                       class="btn btn-sm btn-outline-secondary" title="Detail">
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
                    Menampilkan {{ $arsip->firstItem() }}–{{ $arsip->lastItem() }}
                    dari {{ $arsip->total() }} surat
                </div>
                {{ $arsip->withQueryString()->links('pagination::bootstrap-5') }}
            </div>
        @endif
    </div>
</div>
