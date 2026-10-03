@extends('errors.layout')

@section('title', 'Sedang dipelihara - PRADANA')
@section('kode', '503')
@section('ikon', 'fa-screwdriver-wrench')
@section('judul', 'Sedang dipelihara sebentar')
@section('pesan', 'Aplikasi PRADANA sedang diperbarui oleh petugas. Semua data tetap ada seperti semula — hanya belum bisa dibuka saat ini.')

@section('tindakan')
    <ul class="mb-0 ps-3">
        <li>Tunggu beberapa menit, lalu muat ulang halaman (Refresh).</li>
        <li>Kalau lebih dari 1 jam masih terbuka halaman ini, hubungi petugas aplikasi.</li>
    </ul>
@endsection

@section('tautan')
    <a href="/dashboard" class="btn btn-primary rounded-pill px-4">Coba lagi sekarang</a>
@endsection
