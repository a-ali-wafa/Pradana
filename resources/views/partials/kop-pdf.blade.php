{{--
    Kop surat untuk SEMUA keluaran PDF (surat keluar, Berita Acara Pemusnahan,
    Buku Agenda) — dibuat 5 Okt 2026 saat kop diseragamkan ke tata naskah dinas
    desa (Permendagri 1/2023). Dulu ketiga template menulis kop masing-masing,
    akibatnya kop Buku Agenda beda dua baris dengan kop surat resmi kantor.

    Variabel yang dibaca:
      $instansi  ?PengaturanInstansi  — null berarti belum di-seed, kop tetap dicetak
                                        dengan placeholder netral supaya PDF tidak 500.
      $logoPath  ?string              — path fisik di disk `public` (bukan URL), sudah
                                        dicek exists oleh PengaturanInstansi::logoPathUntukPdf().

    ⚠️ Semua gaya ditulis INLINE. DomPDF memang menerima <style>, tapi partial ini
    dipakai oleh tiga dokumen dengan CSS yang berbeda-beda; inline membuat kop tidak
    bisa tertimpa/rusak oleh style pemanggilnya.
--}}
@php
    // `?? null` di sini bukan gaya: partial ini dipakai bersama oleh tiga dokumen,
    // dan tidak semua controllernya kirim `$instansi`/`$logoPath`. Di Laravel,
    // variabel yang tidak ada = ErrorException, bukan null diam-diam.
    $instansi = $instansi ?? null;
    $logo = $logoPath ?? null;

    $barisAtas = $instansi?->kopBarisAtas() ?? [];
    $barisAlamat = $instansi?->kopAlamat() ?? [];
    $namaUtama = $instansi?->nama_instansi ?: 'PEMERINTAH DESA / KELURAHAN';
@endphp
<table style="width: 100%; border-collapse: collapse;">
    <tr>
        @if ($logo)
            <td style="width: 92px; vertical-align: middle; padding-right: 8px;">
                <img src="{{ $logo }}" alt="" style="width: 78px;">
            </td>
        @endif
        <td style="text-align: center; vertical-align: middle;">
            @foreach ($barisAtas as $baris)
                <div style="font-size: 11pt; font-weight: bold; text-transform: uppercase; letter-spacing: .4px; line-height: 1.25; margin: 0;">{{ $baris }}</div>
            @endforeach
            <div style="font-size: 16pt; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; line-height: 1.2;">{{ $namaUtama }}</div>
            @foreach ($barisAlamat as $baris)
                <div style="font-size: 9.5pt; line-height: 1.35; margin: 1px 0 0;">{!! nl2br(e($baris)) !!}</div>
            @endforeach
        </td>
        @if ($logo)
            {{-- Sel penyeimbang supaya nama instansi tetap tengah terhadap lebar kertas. --}}
            <td style="width: 92px;">&nbsp;</td>
        @endif
    </tr>
</table>
<div style="border-bottom: 3px solid #000; margin-top: 4px;"></div>
<div style="border-bottom: 1px solid #000; margin-top: 2.5px; margin-bottom: 20px;"></div>
