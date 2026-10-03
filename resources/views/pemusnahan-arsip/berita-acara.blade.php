<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Berita Acara Pemusnahan Arsip {{ $pemusnahan->nomor_berita_acara }}</title>
<style>
    body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; color: #000; }
    .kop { width: 100%; border-bottom: 3px solid #000; padding-bottom: 6px; margin-bottom: 2px; }
    .kop table { width: 100%; }
    .kop .logo { width: 80px; }
    .kop .logo img { width: 70px; }
    .kop .nama-instansi { font-size: 16pt; font-weight: bold; text-transform: uppercase; margin: 0; }
    .kop .alamat-instansi { font-size: 10pt; margin: 2px 0 0; }
    .kop-garis-bawah { border-bottom: 1px solid #000; margin-bottom: 24px; }
    .judul { text-align: center; margin-bottom: 4px; }
    .judul h2 { font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 0; letter-spacing: .5px; }
    .judul .nomor { font-size: 12pt; margin: 2px 0 0; }
    .isi p { text-align: justify; margin: 0 0 10px; }
    table.rincian { width: 100%; border-collapse: collapse; margin: 12px 0 18px; }
    table.rincian th, table.rincian td { border: 1px solid #000; padding: 4px 6px; font-size: 11pt; vertical-align: top; }
    table.rincian th { background: #f0f0f0; text-transform: uppercase; font-size: 10pt; }
    .ttd { width: 100%; margin-top: 30px; }
    .ttd td { width: 33.33%; text-align: center; vertical-align: top; font-size: 11pt; }
    .ttd .spasi { height: 70px; }
    .ttd p { margin: 0; }
    .catatan { font-size: 10pt; margin-top: 24px; }
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
                <p class="nama-instansi">{{ $instansi->nama_instansi ?? 'PEMERINTAH DESA/KELURAHAN' }}</p>
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

<div class="judul">
    <h2>Berita Acara Pemusnahan Arsip</h2>
    <p class="nomor">Nomor: {{ $pemusnahan->nomor_berita_acara }}</p>
</div>

<div class="isi">
    <p>
        Pada hari ini, <strong>{{ $hari }}</strong>, tanggal <strong>{{ $tanggalPelaksanaan }}</strong>,
        bertempat di {{ $instansi->nama_instansi ?? 'kantor desa/kelurahan' }}, kami yang bertanda tangan
        di bawah ini telah melakukan pemusnahan arsip dengan rincian sebagai berikut:
    </p>
</div>

<table class="rincian">
    <thead>
        <tr>
            <th style="width:32px;">No</th>
            <th style="width:90px;">Jenis Arsip</th>
            <th style="width:150px;">Nomor Surat</th>
            <th>Perihal</th>
            <th style="width:95px;">Tanggal Surat</th>
            <th style="width:80px;">Jumlah Lampiran</th>
        </tr>
    </thead>
    <tbody>
        @foreach($pemusnahan->items as $index => $item)
        <tr>
            <td class="text-center">{{ $index + 1 }}</td>
            <td>{{ $item->adalahSuratMasuk() ? 'Surat Masuk' : 'Surat Keluar' }}</td>
            <td>{{ $item->nomor_surat_snapshot ?? '-' }}</td>
            <td>{{ $item->perihal_snapshot ?? '-' }}</td>
            <td>{{ $item->tanggal_surat_snapshot?->translatedFormat('d M Y') ?? '-' }}</td>
            <td class="text-center">{{ $item->jumlah_lampiran_snapshot }} berkas</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="isi">
    <p>
        Pemusnahan dilakukan terhadap {{ $pemusnahan->items->count() }} berkas arsip beserta seluruh
        lampirannya, dengan cara menghapus berkas digital dari Sistem Informasi Kearsipan PRADANA
        beserta seluruh lampiran yang tersimpan, dan memusnahkan berkas fisik (kertas) bila ada.
    </p>

    @if($pemusnahan->alasan)
    <p>
        Arsip tersebut dimusnahkan karena telah melewati jangka waktu retensi dan dengan pertimbangan:
        {!! nl2br(e($pemusnahan->alasan)) !!}
    </p>
    @endif

    <p>
        Demikian Berita Acara ini dibuat dengan sebenarnya untuk dapat dipergunakan sebagaimana mestinya,
        dan menjadi bukti bahwa arsip tersebut di atas tidak lagi tersimpan di Unit Kearsipan
        {{ $instansi->nama_instansi ?? '' }}.
    </p>
</div>

<table class="ttd">
    <tr>
        <td>
            <p>Mengetahui,</p>
            <p>{{ $instansi->nama_instansi ?? '' }}</p>
            <p style="text-transform: uppercase;">Kepala {{ $instansi->jenis_instansi ?? 'Desa' }}</p>
            <div class="spasi"></div>
            <p style="text-decoration: underline; font-weight: bold;">{{ $pemusnahan->pemroses?->nama_lengkap ?? '' }}</p>
        </td>
        <td>
            <p>Petugas Kearsipan,</p>
            <p>&nbsp;</p>
            <p>&nbsp;</p>
            <div class="spasi"></div>
            <p style="text-decoration: underline; font-weight: bold;">{{ $pemusnahan->pengaju?->nama_lengkap ?? '' }}</p>
        </td>
        <td>
            <p>Saksi,</p>
            <p>&nbsp;</p>
            <p>&nbsp;</p>
            <div class="spasi"></div>
            <p style="text-decoration: underline;">(.........................)</p>
        </td>
    </tr>
</table>

<div class="catatan">
    <p>
        Catatan: daftar di atas dibuat otomatis oleh Sistem Informasi Kearsipan PRADANA.
        Pengajuan #{{ $pemusnahan->id }} disetujui pada
        {{ $pemusnahan->diproses_pada?->translatedFormat('d M Y H:i') ?? '-' }} oleh
        {{ $pemusnahan->pemroses?->nama_lengkap ?? 'admin' }}.
    </p>
</div>

</body>
</html>
