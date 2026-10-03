@extends('errors.layout')

@section('title', 'Akses ditolak - PRADANA')
@section('kode', '403')
@section('ikon', 'fa-lock')
@section('judul', 'Halaman ini tidak boleh dibuka dari akun Anda')
@section('pesan', 'Fitur yang Anda buka dikhususkan untuk admin (kepala desa/lurah): menyetujui penghapusan, pemusnahan arsip, data user, klasifikasi, dan pengaturan instansi.')

@section('tindakan')
    <ul class="mb-0 ps-3">
        <li>Kalau memang perlu membuka halaman ini, mintalah admin yang membukanya — bukan minta dibukakan aksesnya.</li>
        <li>Kalau Anda=admin dan tadi bisa membuka halaman ini, kemungkinan Anda login dengan akun staf. Cek nama di kanan atas.</li>
        <li>Sebutkan kode <strong>403</strong> kalau melapor ke petugas aplikasi.</li>
    </ul>
@endsection

@section('tautan')
    <a href="/dashboard" class="btn btn-primary rounded-pill px-4">Kembali ke Dashboard</a>
    <a href="/login" class="btn btn-outline-secondary rounded-pill px-4">Masuk dengan akun lain</a>
@endsection
