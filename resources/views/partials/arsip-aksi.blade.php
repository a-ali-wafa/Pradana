{{--
    Tombol aksi arsip di halaman show surat masuk/keluar (parsial bersama).

    "Nyahkan" = mengubah status_arsip jadi inaktif, BUKAN menghapus.
    Tombol sampah = soft delete: baris & lampiran tetap ada, admin bisa
    memulihkan. Pemusnahan sungguhan tidak ada di layar ini — hanya lewat modul
    Pemusnahan Arsip yang menerbitkan Berita Acara.

    Parameter: $surat (model), $jenis ('masuk' | 'keluar').
    Dipakai oleh surat-masuk/show.blade.php dan surat-keluar/show.blade.php
    supaya kedua halaman tidak punya salinan logika yang bisa lari sendiri —
    uploader yang dulu dobel pernah sampai salah nama field.
--}}

@php
    $isAdmin = auth()->user()->isAdmin();
    $inaktif = $surat->status_arsip === 'inaktif';
@endphp

{{-- Tombol "Nyahkan" / "Aktifkan Kembali" --}}
<form method="POST" action="{{ route("surat-{$jenis}.status-arsip", $surat) }}">
    @csrf @method('PATCH')
    <input type="hidden" name="status_arsip" value="{{ $inaktif ? 'aktif' : 'inaktif' }}">
    <button type="submit"
            class="btn btn-outline-{{ $inaktif ? 'success' : 'warning' }} rounded-pill px-3"
            title="{{ $inaktif ? 'Kembalikan jadi arsip aktif' : 'Nonaktifkan arsip ini (tidak menghapus apa pun)' }}">
        <i class="fas fa-toggle-{{ $inaktif ? 'on' : 'off' }} me-1"></i>
        {{ $inaktif ? 'Aktifkan Kembali' : 'Nyahkan' }}
    </button>
</form>

@if($isAdmin)
    {{-- Form Hapus ditulis sebagai form sungguhan (10 Okt): sebelumnya tombolnya
        `type="button"` + `onclick="pradanaConfirmHapus(...)"` yang merakit form di
        JavaScript, jadi tanpa JS tombolnya tidak melakukan APA-APA. Sekarang JS
        hanya menambahkan konfirmasi lewat public/js/pradana-arsip.js. --}}
    <form method="POST" action="{{ route("surat-{$jenis}.destroy", $surat) }}"
          data-konfirmasi="Pindahkan surat &raquo;{{ $surat->nomor_surat }}&raquo; ke tempat sampah?"
          data-konfirmasi-judul="Pindahkan ke Tempat Sampah?"
          data-konfirmasi-catatan="Seluruh lampiran tetap tersimpan dan surat ini bisa dipulihkan oleh admin. Pemusnahan permanen hanya lewat menu Pemusnahan Arsip."
          data-konfirmasi-ya="Ya, Pindahkan">
        @csrf @method('DELETE')
        <button type="submit" class="btn btn-outline-danger rounded-pill px-3"
                title="Pindahkan ke tempat sampah (baris & berkas tidak ada yang hilang)">
            <i class="fas fa-trash me-1"></i> Hapus
        </button>
    </form>
@endif
