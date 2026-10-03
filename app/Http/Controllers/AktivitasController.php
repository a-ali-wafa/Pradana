<?php

namespace App\Http\Controllers;

use App\Models\Aktivitas;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Halaman log aktivitas untuk admin (L-22 / P3=a).
 *
 * Log ini satu-satunya jejak "siapa mengubah apa" di aplikasi tanpa sistem
 * kepemilikan (L-10: user boleh dihapus walau punya surat; B8: "log aktivitas
 * hanya membantu untuk berjaga jika terjadi sesuatu"). Karena itu halamannya
 * read-only — tidak ada edit/hapus dari UI. Baris lama dibersihkan lewat
 * perintah `arsip:bersihkan-log` (E6), bukan dari layar.
 */
class AktivitasController extends Controller
{
    public function __construct()
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(Request $request): View
    {
        $query = Aktivitas::query()->with('user');

        if ($request->filled('cari')) {
            $kata = $request->input('cari');
            $query->where('aksi', 'like', "%{$kata}%");
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('dari')) {
            $query->whereDate('created_at', '>=', $request->input('dari'));
        }

        if ($request->filled('sampai')) {
            $query->whereDate('created_at', '<=', $request->input('sampai'));
        }

        return view('aktivitas.index', [
            'aktivitas' => $query->latest()->latest('id')->paginate(30)->withQueryString(),
            'pilihanUser' => User::orderBy('nama_lengkap')->get(['id', 'nama_lengkap']),
            'filter' => [
                'cari' => $request->input('cari'),
                'user_id' => $request->input('user_id'),
                'dari' => $request->input('dari'),
                'sampai' => $request->input('sampai'),
            ],
        ]);
    }
}
