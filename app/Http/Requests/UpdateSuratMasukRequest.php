<?php

namespace App\Http\Requests;

/**
 * Sengaja extends langsung TANPA override rules() apa pun — sesuai AGENTS.md
 * 12.6: "UpdateSuratMasukRequest extends StoreSuratMasukRequest karena
 * aturannya identik", beda dengan UpdateSuratKeluarRequest yang butuh rule
 * nomor_surat tambahan (unique, ignore self) karena nomor surat keluar
 * auto-generate & unique, sedangkan nomor surat masuk manual & tidak unique.
 * Validasi hierarki klasifikasi di withValidator() ikut terwarisi otomatis.
 */
class UpdateSuratMasukRequest extends StoreSuratMasukRequest
{
    //
}
