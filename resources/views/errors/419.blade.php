@extends('errors.layout')

@section('title', 'Halaman kedaluwarsa - PRADANA')
@section('kode', '419')
@section('ikon', 'fa-hourglass-end')
@section('judul', 'Halaman ini kedaluwarsa sebelum dikirim')
@section('pesan', 'Ini terjadi kalau formulir dibiarkan terbuka terlalu lama (lebih dari 2 jam tanpa aktivitas), atau computer baru saja tidur/bersambung ulang ke jaringan. Data Anda BELUM tersimpan.')

@section('tindakan')
    <ul class="mb-0 ps-3">
        <li>Muat ulang halaman (tombol Refresh di browser), lalu kirimkan lagi formulirnya.</li>
        <li>JANGAN tekan tombol "Kembali" browser untuk mengirim ulang — hasilnya formulir kosong lagi.</li>
        <li>Untuk isian panjang (misalnya isi surat keluar), lebih aman mengetiknya di Word lalu menempelnya ke kolom isian — kalau kedaluwarsa, isinya tidak hilang.</li>
        <li>Sebutkan kode <strong>419</strong> kalau melapor ke petugas aplikasi.</li>
    </ul>
@endsection

@section('tautan')
    <a href="/dashboard" class="btn btn-primary rounded-pill px-4">Ke Dashboard</a>
    <a href="/login" class="btn btn-outline-secondary rounded-pill px-4">Masuk ulang</a>
@endsection
