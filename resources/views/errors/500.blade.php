@extends('errors.layout')

@section('title', 'Sistem sedang bermasalah - PRADANA')
@section('kode', '500')
@section('ikon', 'fa-plug-circle-exclamation')
@section('judul', 'Sistem sedang bermasalah')
@section('pesan', 'Aplikasi gagal menyelesaikan perintah terakhir. Ini kesalahan di sisi server, bukan salah Anda — dan data yang sudah tersimpan sebelumnya tetap aman.')

@section('tindakan')
    <ul class="mb-0 ps-3">
        <li>Tunggu sebentar, lalu coba lagi satu kali. Kalau gagal lagi, jangan diulang-ulang.</li>
        <li>Catat <strong>jam kejadian</strong> dan <strong>sedang membuka apa</strong> (mis. "unggah lampiran surat 012/..."), lalu laporkan ke petugas aplikasi — jejak lengkapnya ada di Log Aktivitas dan file log server.</li>
        <li>Jangan menyalin/memindah/menghapus file di folder arsip computer server sendiri; itu satu-satunya salinan dokumen Anda.</li>
        <li>Sebutkan kode <strong>500</strong> kalau melapor ke petugas aplikasi.</li>
    </ul>
@endsection

@section('tautan')
    <a href="/dashboard" class="btn btn-primary rounded-pill px-4">Coba lagi</a>
    <a href="/login" class="btn btn-outline-secondary rounded-pill px-4">Masuk ulang</a>
@endsection
