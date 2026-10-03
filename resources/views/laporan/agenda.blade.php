<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Buku Agenda Surat {{ $dari }} s.d. {{ $sampai }}</title>
<style>
    body { font-family: 'Times New Roman', Times, serif; font-size: 11pt; color: #000; }
    .kop { width: 100%; border-bottom: 3px solid #000; padding-bottom: 6px; margin-bottom: 2px; }
    .kop table { width: 100%; }
    .kop .nama-instansi { font-size: 15pt; font-weight: bold; text-transform: uppercase; margin: 0; }
    .kop .alamat-instansi { font-size: 9pt; margin: 2px 0 0; }
    .kop-garis-bawah { border-bottom: 1px solid #000; margin-bottom: 16px; }
    .judul { text-align: center; margin-bottom: 12px; }
    .judul h2 { font-size: 13pt; font-weight: bold; text-transform: uppercase; margin: 0; }
    .judul p { font-size: 10pt; margin: 3px 0 0; }
    table.agenda { width: 100%; border-collapse: collapse; }
    table.agenda th, table.agenda td { border: 1px solid #000; padding: 3px 5px; font-size: 9.5pt; vertical-align: top; }
    table.agenda th { background: #eeeeee; text-transform: uppercase; font-size: 8.5pt; }
    .ttd { width: 100%; margin-top: 28px; font-size: 10pt; }
    .ttd td { text-align: center; vertical-align: top; width: 50%; }
    .ttd .spasi { height: 65px; }
    .ttd p { margin: 0; }
    .subjudul { font-weight: bold; text-transform: uppercase; font-size: 10pt; margin: 14px 0 4px; }
</style>
</head>
<body>

<div class="kop">
    <table>
        <tr>
            <td>
                <p class="nama-instansi">{{ $instansi->nama_instansi ?? 'PEMERINTAH DESA/KELURAHAN' }}</p>
                <p class="alamat-instansi">
                    {{ $instansi->jenis_instansi ?? '' }}
                    @if($instansi?->alamat_instansi) &middot; {{ $instansi->alamat_instansi }} @endif
                </p>
            </td>
        </tr>
    </table>
</div>
<div class="kop-garis-bawah"></div>

<div class="judul">
    <h2>Buku Agenda Surat</h2>
    <p>Periode {{ $dari }} sampai {{ $sampai }}</p>
</div>

@php
    $masuk = $items->where('jenis', 'masuk')->values();
    $keluar = $items->where('jenis', 'keluar')->values();
@endphp

@if($jenis !== 'keluar')
<div class="subjudul">A. Surat Masuk ({{ $masuk->count() }} lembar)</div>
<table class="agenda">
    <thead>
        <tr>
            <th style="width:26px;">No</th>
            <th style="width:72px;">Tgl Terima</th>
            <th style="width:72px;">Tgl Surat</th>
            <th style="width:140px;">Nomor Surat</th>
            <th style="width:130px;">Pengirim</th>
            <th>Perihal</th>
            <th style="width:110px;">Klasifikasi</th>
            <th style="width:52px;">Sifat</th>
            <th style="width:70px;">Keterangan</th>
        </tr>
    </thead>
    <tbody>
        @forelse($masuk as $i => $s)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $s['tanggal_diterima'] ?? '-' }}</td>
            <td>{{ $s['tanggal_surat'] }}</td>
            <td>{{ $s['nomor_surat'] }}</td>
            <td>{{ $s['lawan'] }}</td>
            <td>{{ $s['perihal'] }}</td>
            <td>{{ $s['klasifikasi'] }}</td>
            <td>{{ strtoupper(substr($s['sifat'], 0, 4)) }}</td>
            <td>{{ (int) $s['jumlah_lampiran'] }} berkas</td>
        </tr>
        @empty
        <tr><td colspan="9" style="text-align:center;">Tidak ada surat masuk pada periode ini.</td></tr>
        @endforelse
    </tbody>
</table>
@endif

@if($jenis !== 'masuk')
<div class="subjudul">B. Surat Keluar ({{ $keluar->count() }} lembar)</div>
<table class="agenda">
    <thead>
        <tr>
            <th style="width:26px;">No</th>
            <th style="width:72px;">Tgl Surat</th>
            <th style="width:140px;">Nomor Surat</th>
            <th style="width:130px;">Penerima</th>
            <th>Perihal</th>
            <th style="width:110px;">Klasifikasi</th>
            <th style="width:52px;">Sifat</th>
            <th style="width:90px;">Penandatangan</th>
        </tr>
    </thead>
    <tbody>
        @forelse($keluar as $i => $s)
        <tr>
            <td>{{ $i + 1 }}</td>
            <td>{{ $s['tanggal_surat'] }}</td>
            <td>{{ $s['nomor_surat'] }}</td>
            <td>{{ $s['lawan'] }}</td>
            <td>{{ $s['perihal'] }}</td>
            <td>{{ $s['klasifikasi'] }}</td>
            <td>{{ strtoupper(substr($s['sifat'], 0, 4)) }}</td>
            <td>{{ $s['petugas'] }}</td>
        </tr>
        @empty
        <tr><td colspan="8" style="text-align:center;">Tidak ada surat keluar pada periode ini.</td></tr>
        @endforelse
    </tbody>
</table>
@endif

<table class="ttd">
    <tr>
        <td>
            <p>Mengetahui,</p>
            <p>{{ $instansi->nama_instansi ?? '' }}</p>
            <p style="text-transform: uppercase;">Kepala {{ $instansi->jenis_instansi ?? 'Desa' }}</p>
            <div class="spasi"></div>
            <p style="text-decoration: underline; font-weight: bold;">(.............................)</p>
        </td>
        <td>
            <p>Petugas Arsip,</p>
            <p>&nbsp;</p>
            <p>&nbsp;</p>
            <div class="spasi"></div>
            <p style="text-decoration: underline; font-weight: bold;">(.............................)</p>
        </td>
    </tr>
</table>

</body>
</html>
