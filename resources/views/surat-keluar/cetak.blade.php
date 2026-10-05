{{--
    Template cetak Surat Keluar — DITULIS ULANG 5 Okt 2026 mengikuti struktur naskah
    dinas desa/kelurahan pada umumnya (rujukan: Permendagri 1/2023 tentang Tata Naskah
    Dinas, contoh kop pemerintah desa, dan pedoman tata naskah dinas instansi).

    Yang dulu belum sesuai dan sekarang dibenahi:
    - Kop satu baris + "jenis instansi" di bawah alamat → kop bertingkat
      (Kabupaten → Kecamatan → Desa) lewat partial `partials/kop-pdf.blade.php`,
      dengan baris alamat + kode pos + kontak yang benar (kolom baru 5 Okt 2026).
    - Tanggal surat tanpa nama tempat → "Urek-Urek, 5 Oktober 2026" (kanan, sejajar
      baris Nomor), sesuai kebiasaan kantor.
    - Blok Nomor/Lampiran/Perihal → tabel dengan titik dua sejajar, ditambah baris
      Klasifikasi (kode 3 level) yang selalu ada di naskah dinas desa.
    - Lampiran ditulis `0 (nol) berkas` / `2 (dua) berkas`, bukan "0 Berkas".
    - Isi dipecah per paragraf (baris kosong = paragraf baru), rata kiri-kanan;
      salam pembuka & penutup dicetak sebagai paragraf sendiri.
    - Alamat tujuan mengikuti pola "Kepada Yth. / jabatan / nama / instansi / di / kota".
    - Blok tanda tangan: jabatan (fallback ke sebutan pemimpin instansi dari
      pengaturan), ruang cap/stempel, nama jelas digarisbawahi, NIP/NIK — dan tidak
      boleh terpotong halaman (`page-break-inside: avoid`).
    - Tembusan bernomor 1..n kalau lebih dari satu baris.

    MASIH DITUNGGU (F3=b): kop RESMI dari desa. Selama contoh kop asli belum
    diberikan, template umum ini yang dipakai — ukuran font & margin sengaja
    mendekati ukuran yang lazim supaya tinggal digeser kalau nanti dicocokkan.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $surat->nomor_surat }}</title>
<style>
    @page { margin: 2.5cm 2.5cm 2.5cm 3cm; }
    body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; color: #000; line-height: 1.35; }
    .nomor-perihal td { vertical-align: top; padding: 0 0 2px; }
    .nomor-perihal .label { width: 78px; }
    .nomor-perihal .titik { width: 8px; }
    .nomor-perihal .nilai { }
    .tanggal { text-align: right; vertical-align: top; white-space: nowrap; }
    .alamat-tujuan { margin: 16px 0 4px; }
    .isi { text-align: justify; }
    .isi p { margin: 0 0 10px; }
    .isi p.paragraf-pertama { margin-top: 4px; }
    .ttd-luar { page-break-inside: avoid; }
    .ttd { width: 46%; margin-left: 54%; text-align: left; }
    .ttd .spasi-ttd { height: 72px; }
    .ttd p { margin: 0; }
    .tembusan { margin-top: 26px; font-size: 11pt; }
    .tembusan ol { margin: 0; padding-left: 20px; }
    .tembusan li { margin-bottom: 1px; }
</style>
</head>
<body>

@include('partials.kop-pdf')

<table style="width: 100%; border-collapse: collapse;">
    <tr>
        <td style="width: 60%; vertical-align: top;">
            <table class="nomor-perihal">
                <tr>
                    <td class="label">Nomor</td>
                    <td class="titik">:</td>
                    <td class="nilai">{{ $surat->nomor_surat }}</td>
                </tr>
                @if ($kodeKlasifikasi !== '')
                <tr>
                    <td class="label">Klasifikasi</td>
                    <td class="titik">:</td>
                    <td class="nilai">{{ $kodeKlasifikasi }}</td>
                </tr>
                @endif
                <tr>
                    <td class="label">Sifat</td>
                    <td class="titik">:</td>
                    <td class="nilai">{{ ucfirst($surat->sifat) }}</td>
                </tr>
                <tr>
                    <td class="label">Lampiran</td>
                    <td class="titik">:</td>
                    <td class="nilai">{{ $notasiLampiran }}</td>
                </tr>
                <tr>
                    <td class="label">Perihal</td>
                    <td class="titik">:</td>
                    <td class="nilai"><strong>{{ $surat->perihal }}</strong></td>
                </tr>
            </table>
        </td>
        <td class="tanggal">
            {{ trim(($instansi?->tempatSurat() ?? '').', '.($tanggalSurat ?? ''), ', ') }}
        </td>
    </tr>
</table>

@php
    // Pola alamat tujuan naskah dinas: jabatan lebih dulu, baru nama orang/instansi,
    // ditutup "di <kota>". Semua bagian boleh kosong — yang kosong tidak dicetak.
    $tujuan = collect([
        $surat->jabatan_penerima,
        $surat->penerima,
        $surat->instansi_penerima,
    ])->filter()->values();

    $kotaTujuan = trim($draf->alamat_tujuan ?: collect([
        $surat->kota_tujuan,
        $surat->provinsi_tujuan,
    ])->filter()->implode(', '));
@endphp
<div class="alamat-tujuan">
    <p style="margin: 0;">Kepada Yth.</p>
    @foreach ($tujuan as $baris)
        <p style="margin: 0;">{{ $baris }}</p>
    @endforeach
    @if ($kotaTujuan !== '')
        <p style="margin: 0;">di <span style="text-decoration: underline;">{{ $kotaTujuan }}</span></p>
    @endif
</div>

@php
    // Baris kosong = pemisah paragraf; satu enter tetap jadi baris baru di dalam
    // paragraf yang sama (kebiasaan orang kantor mengetik di form draf).
    $paragraf = collect(preg_split('/\R{2,}/', trim((string) $draf->isi_surat)))
        ->map(fn ($t) => trim($t))
        ->filter();
@endphp
<div class="isi">
    @if ($draf->salam_pembuka)
        <p class="paragraf-pertama">{{ $draf->salam_pembuka }}</p>
    @endif

    @foreach ($paragraf as $i => $satuParagraf)
        <p @if($i === 0 && !$draf->salam_pembuka) class="paragraf-pertama" @endif>{!! nl2br(e($satuParagraf)) !!}</p>
    @endforeach

    @if ($draf->salam_penutup)
        <p>{{ $draf->salam_penutup }}</p>
    @endif
</div>

<div class="ttd-luar">
    <div class="ttd">
        <p>{{ $draf->jabatan_penandatangan ?: ($instansi?->sebutanPemimpin() ?? '') }},</p>
        <div class="spasi-ttd"></div>
        <p style="text-decoration: underline; font-weight: bold;">{{ $draf->atas_nama ?? ($surat->petugas->nama_lengkap ?? '') }}</p>
        @if ($draf->nip_nik)
            <p>NIP/NIK. {{ $draf->nip_nik }}</p>
        @endif
    </div>
</div>

@if ($draf->tembusan)
    @php
        $daftarTembusan = collect(preg_split('/\R+/', trim((string) $draf->tembusan)))
            ->map(fn ($t) => trim($t))
            ->filter();
    @endphp
    <div class="tembusan ttd-luar">
        <p style="margin: 0 0 3px;">Tembusan:</p>
        @if ($daftarTembusan->count() > 1)
            <ol>
                @foreach ($daftarTembusan as $satu)
                    <li>{{ $satu }}</li>
                @endforeach
            </ol>
        @else
            <p style="margin: 0;">{{ $daftarTembusan->first() }}</p>
        @endif
    </div>
@endif

</body>
</html>
