<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan L-05: arsip resmi tidak boleh bisa dihapus permanen oleh user.
 * Penghapusan lewat UI jadi soft delete — baris dan lampirannya tetap utuh
 * (termasuk file fisik) dan bisa dipulihkan admin.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['surat_masuk', 'surat_keluar'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->softDeletes();
                $table->index('deleted_at');
            });
        }
    }

    public function down(): void
    {
        foreach (['surat_masuk', 'surat_keluar'] as $tabel) {
            Schema::table($tabel, function (Blueprint $table) {
                $table->dropIndex(['deleted_at']);
                $table->dropSoftDeletes();
            });
        }
    }
};
