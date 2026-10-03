@extends('errors.layout')

@section('title', 'Halaman tidak ditemukan - PRADANA')
@section('kode', '404')
@section('ikon', 'fa-magnifying-glass')
@section('judul', 'Halaman atau arsip ini tidak ditemukan')
@section('pesan', 'Alamatnya salah ketik, tautannya sudah lama, atau surat yang dicari sudah dipindahkan/dimusnahkan.')

@section('tindakan')
    <ul class="mb-0 ps-3">
        <li>Kalau yang dicari adalah <strong>surat</strong>, gunakan <a href="/pencarian">Pencarian Arsip</a> — lebih aman daripada menebak alamatnya.</li>
        <li>Surat yang sedang diproses di "Pemusnahan Arsip" belum hilang; statusnya bisa dilihat admin di menu tersebut.</li>
        <li>Sebutkan kode <strong>404</strong> kalau melapor ke petugas aplikasi.</li>
    </ul>
@endsection

@section('tautan')
    <a href="/dashboard" class="btn btn-primary rounded-pill px-4">Kembali ke Dashboard</a>
    <a href="/pencarian" class="btn btn-outline-secondary rounded-pill px-4">Pencarian Arsip</a>
@endsection
