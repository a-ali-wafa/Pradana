<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>{{ $surat->nomor_surat }}</title>
<style>
    body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; color: #000; }
    .kop { width: 100%; border-bottom: 3px solid #000; padding-bottom: 6px; margin-bottom: 2px; }
    .kop table { width: 100%; }
    .kop .logo { width: 80px; }
    .kop .logo img { width: 70px; }
    .kop .nama-instansi { font-size: 16pt; font-weight: bold; text-transform: uppercase; margin: 0; }
    .kop .alamat-instansi { font-size: 10pt; margin: 2px 0 0; }
    .kop-garis-bawah { border-bottom: 1px solid #000; margin-bottom: 18px; }
    .tanggal-surat { text-align: right; }
    .isi-surat { text-align: justify; margin-top: 14px; }
    .isi-surat p { margin: 0 0 10px; }
    .ttd { width: 50%; margin-left: 50%; text-align: center; margin-top: 24px; }
    .ttd .spasi-ttd { height: 70px; }
    .ttd p { margin: 0; }
    .tembusan { margin-top: 24px; font-size: 11pt; }
    table.info td { padding: 1px 4px; vertical-align: top; }
</style>
</head>
<body>

<div class="kop">
    <table>
        <tr>
            @if($logoPath)
            <td class="logo"><img src="{{ $logoPath }}" alt="Logo"></td>
            @endif
            <td>
                <p class="nama-instansi">{{ $instansi->nama_instansi ?? '-' }}</p>
                <p class="alamat-instansi">
                    {{ $instansi->jenis_instansi ?? '' }}<br>
                    {{ $instansi->alamat_instansi ?? '' }}
                    @if($instansi?->no_telp) &middot; Telp. {{ $instansi->no_telp }} @endif
                    @if($instansi?->email) &middot; Email: {{ $instansi->email }} @endif
                </p>
            </td>
        </tr>
    </table>
</div>
<div class="kop-garis-bawah"></div>

<table style="width: 100%; margin-bottom: 16px;">
    <tr>
        <td style="width: 60%; vertical-align: top;">
            <table class="info">
                <tr><td>Nomor</td><td>:</td><td>{{ $surat->nomor_surat }}</td></tr>
                <tr><td>Sifat</td><td>:</td><td>{{ ucfirst($surat->sifat) }}</td></tr>
                <tr><td>Lampiran</td><td>:</td><td>{{ $draf->lampiran ?? '-' }}</td></tr>
                <tr><td>Perihal</td><td>:</td><td><strong>{{ $surat->perihal }}</strong></td></tr>
            </table>
        </td>
        <td class="tanggal-surat" style="width: 40%; vertical-align: top;">
            {{ $tanggalSurat }}
        </td>
    </tr>
</table>

<p style="margin-bottom: 20px;">
    Kepada Yth.<br>
    {{ $surat->jabatan_penerima }}<br>
    {{ $surat->penerima }}<br>
    @if($surat->instansi_penerima){{ $surat->instansi_penerima }}<br>@endif
    {{ $draf->alamat_tujuan ?? trim(($surat->kota_tujuan ?? '').', '.($surat->provinsi_tujuan ?? ''), ', ') }}
</p>

<div class="isi-surat">
    @if($draf->salam_pembuka)
        <p>{{ $draf->salam_pembuka }}</p>
    @endif

    <p>{!! nl2br(e($draf->isi_surat ?? '')) !!}</p>

    @if($draf->salam_penutup)
        <p>{{ $draf->salam_penutup }}</p>
    @endif
</div>

<div class="ttd">
    <p>{{ $draf->jabatan_penandatangan ?? '' }}</p>
    <div class="spasi-ttd"></div>
    <p style="text-decoration: underline; font-weight: bold;">{{ $draf->atas_nama ?? ($surat->petugas->nama_lengkap ?? '') }}</p>
    @if($draf->nip_nik)
        <p>NIP/NIK. {{ $draf->nip_nik }}</p>
    @endif
</div>

@if($draf->tembusan)
<div class="tembusan">
    <p style="margin: 0 0 4px;">Tembusan:</p>
    <div>{!! nl2br(e($draf->tembusan)) !!}</div>
</div>
@endif

</body>
</html>
