<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('surat_keluar', function (Blueprint $table) {
            $table->dropColumn('file_path');
            if (\Illuminate\Support\Facades\Schema::hasColumn('surat_keluar', 'lampiran')) {
                $table->dropColumn('lampiran');
            }
        });

        Schema::table('surat_masuk', function (Blueprint $table) {
            $table->dropColumn('file_path');
            if (\Illuminate\Support\Facades\Schema::hasColumn('surat_masuk', 'lampiran')) {
                $table->dropColumn('lampiran');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('surat_keluar', function (Blueprint $table) {
            $table->string('file_path')->nullable();
        });
        
        Schema::table('surat_masuk', function (Blueprint $table) {
            $table->string('file_path')->nullable();
        });
    }
};
