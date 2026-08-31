<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_instansi', function (Blueprint $table) {
            $table->id();
            $table->string('nama_instansi', 150);
            $table->string('jenis_instansi', 150)->nullable();
            $table->string('alamat_instansi', 255)->nullable();
            $table->string('no_telp', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->string('logo_path')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengaturan_instansi');
    }
};
