{{--
    Berita Acara Pemusnahan Arsip — kop-nya ikut partial `partials.kop-pdf.blade.php`
    sejak 5 Okt 2026 supaya identik dengan surat keluar (dulu kedua template menulis
    kop masing-masing dan bentuknya beda). Blok hari/tanggal diganti ke gaya baku
    berita acara: angka tanggal & tahun ditulis dalam huruf (Terbilang), karena
    dokumen pernyataan resmi selalu begitu di kantor desa.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Berita Acara Pemusnahan Arsip {{ $pemusnahan->nomor_berita_acara }}</title>
<style>
    @page { margin: 2.5cm 2.5cm 2.5cm 3cm; }
    body { font-family: 'Times New Roman', Times, serif; font-size: 12pt; color: #000; line-height: 1.35; }
    .judul { text-align: center; margin-bottom: 4px; }
    .judul h2 { font-size: 14pt; font-weight: bold; text-transform: uppercase; margin: 0; letter-spacing: .5px; }
    .judul .nomor { font-size: 12pt; margin: 2px 0 0; }
    .garis-judul { width: 40%; border-bottom: 1px solid #000; margin: 0 auto 16px; }
    table.keterangan { border-collapse: collapse; margin: 0 0 12px 0; }
    table.keterangan td { padding: 1px 4px 1px 0; vertical-align: top; font-size: 12pt; }
    table.keterangan td.tokoh { width: 130px; }
    table.keterangan td.titik { width: 10px; }
    .isi p { text-align: justify; margin: 0 0 10px; }
    .isi { margin-left: 0; }
    table.rincian { width: 100%; border-collapse: collapse; margin: 12px 0 18px; }
    table.rincian th, table.rincian td { border: 1px solid #000; padding: 4px 6px; font-size: 11pt; vertical-align: top; }
    table.rincian th { background: #f0f0f0; text-transform: uppercase; font-size: 10pt; }
    table.rincian td.tengah { text-align: center; }
    .ttd { width: 100%; margin-top: 30px; page-break-inside: avoid; }
    .ttd td { width: 33.33%; text-align: center; vertical-align: top; font-size: 11pt; }
    .ttd .spasi { height: 70px; }
    .ttd p { margin: 0; }
    .catatan { font-size: 10pt; margin-top: 24px; }
</style>
</head>
<body>

@include('partials.kop-pdf')

<div class="judul">
    <h2>Berita Acara Pemusnahan Arsip</h2>
    <p class="nomor">Nomor: {{ $pemusnahan->nomor_berita_acara }}</p>
</div>
<div class="garis-judul"></div>

<div class="isi">
    <p>Yang bertanda tangan di bawah ini:</p>
</div>

<table class="keterangan">
    <tr>
        <td class="tokoh">Nama</td>
        <td class="titik">:</td>
        <td>{{ $pemusnahan->pemroses?->nama_lengkap ?? '-' }} (Pejabat yang menyetujui), dan<br>{{ $pemusnahan->pengaju?->nama_lengkap ?? '-' }} (Petugas Kearsipan)</td>
    </tr>
    <tr>
        <td class="tokoh">Hari / Tanggal</td>
        <td class="titik">:</td>
        <td>{{ $hari }}, tanggal {{ $tanggalHuruf }} bulan {{ $bulanHuruf }} tahun {{ $tahunHuruf }}</td>
    </tr>
    <tr>
        <td class="tokoh">Tempat</td>
        <td class="titik">:</td>
        <td>Kantor {{ $instansi?->nama_instansi ?? 'Desa/Kelurahan' }}</td>
    </tr>
</table>

<div class="isi">
    <p>
        Telah melakukan pemusnahan arsip dengan rincian sebagai berikut:
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
            <td class="tengah">{{ $index + 1 }}</td>
            <td>{{ $item->adalahSuratMasuk() ? 'Surat Masuk' : 'Surat Keluar' }}</td>
            <td>{{ $item->nomor_surat_snapshot ?? '-' }}</td>
            <td>{{ $item->perihal_snapshot ?? '-' }}</td>
            <td>{{ $item->tanggal_surat_snapshot?->translatedFormat('d M Y') ?? '-' }}</td>
            <td class="tengah">{{ $item->jumlah_lampiran_snapshot }} berkas</td>
        </tr>
        @endforeach
    </tbody>
</table>

<div class="isi">
    <p>
        Pemusnahan dilakukan terhadap
        {{ \App\Support\Terbilang::denganAngka($pemusnahan->items->count()) }} berkas arsip
        beserta seluruh lampirannya, dengan cara menghapus berkas digital dari Sistem
        Informasi Kearsipan PRADANA beserta seluruh lampiran yang tersimpan, dan
        memusnahkan berkas fisik (kertas) bila ada.
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
        {{ $instansi?->nama_instansi ?? '' }}.
    </p>
</div>

<p style="text-align: right; margin: 22px 0 0;">
    {{ trim(($instansi?->tempatSurat() ?? '').', '.$tanggalPelaksanaan, ', ') }}
</p>

<table class="ttd">
    <tr>
        <td>
            <p>Mengetahui,</p>
            <p>&nbsp;</p>
            <p>{{ $instansi?->sebutanPemimpin() ?? 'Kepala Instansi' }}</p>
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
