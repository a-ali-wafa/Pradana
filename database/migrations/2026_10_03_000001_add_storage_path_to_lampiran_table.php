<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Keputusan L-01: file arsip pindah ke disk lokal sebagai sumber utama,
 * Google Drive turun status jadi backup terjadwal. Kolom Drive tetap ada
 * supaya (a) data lama yang cuma punya file Drive masih bisa diunduh, dan
 * (b) perintah sinkronisasi punya tempat mencatat hasil upload backup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lampiran', function (Blueprint $table) {
            $table->string('disk', 50)->nullable()->after('google_drive_folder_id');
            $table->string('path', 512)->nullable()->after('disk');
            $table->char('hash_file', 64)->nullable()->after('path');

            $table->string('google_drive_file_id')->nullable()->change();

            $table->index('hash_file');
        });
    }

    public function down(): void
    {
        Schema::table('lampiran', function (Blueprint $table) {
            $table->dropIndex(['hash_file']);
            $table->dropColumn(['disk', 'path', 'hash_file']);
        });
    }
};
