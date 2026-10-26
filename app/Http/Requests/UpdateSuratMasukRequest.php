<?php

namespace App\Http\Requests;

/**
 * Sengaja extends langsung TANPA override rules() apa pun: aturannya identik dengan
 * create. Beda dengan UpdateSuratKeluarRequest yang butuh rule
 * nomor_surat tambahan (unique, ignore self) karena nomor surat keluar
 * auto-generate & unique, sedangkan nomor surat masuk manual & tidak unique.
 * Validasi hierarki klasifikasi di withValidator() ikut terwarisi otomatis.
 */
class UpdateSuratMasukRequest extends StoreSuratMasukRequest
{
    //
}
