<?php

namespace App\Console\Concerns;

/**
 * Membaca PIN 8 digit di terminal tanpa menuliskannya di baris perintah.
 *
 * `secret()` adalah jalur utama dan itu yang paling aman. Variabel lingkungan
 * `PRADANA_PIN` ada sebagai jalur kedua karena satu kenyataan operasi: di server
 * yang benar, semua artisan dijalankan sebagai `www-data` (`sudo -u www-data`),
 * sementara pseudo-terminal milik user yang login. Penyembunyian Symfony bekerja
 * dengan `stty -echo` pada terminal itu — dan user yang bukan pemilik tty tidak
 * boleh melakukannya, jadi pembacaan mengembalikan STRING KOSONG tanpa pesan apa
 * pun yang menyebut penyebabnya. Gejalanya di layar petugas: "PIN harus 8 digit
 * angka" walaupun PIN-nya sudah diketik.
 *
 * Cara operator yang benar (nilainya tidak masuk argv, tidak masuk riwayat shell,
 * tidak masuk chat):
 *
 *   read -s "P?PIN 8 digit: "
 *   sudo -u www-data env HOME=/tmp PRADANA_PIN="$P" php artisan arsip:akun-pertama ...
 *   unset P
 *
 * Bedanya dengan `--pin=`: argumen proses bisa dibaca siapa pun lewat `ps aux`
 * selama perintah hidup, sedangkan environment hanya terbaca oleh user yang sama
 * dan root. `getenv` di sini adalah input perintah console, bukan konfigurasi
 * aplikasi — aturan "nilai konfigurasi lewat config()" tetap berlaku.
 */
trait MintaPin
{
    protected function mintaPin(string $pertanyaan = 'PIN 8 digit'): string
    {
        $dariEnv = trim((string) getenv('PRADANA_PIN'));

        if ($dariEnv !== '') {
            // Jangan ditinggal: anak proses berikutnya (artisan lain, mysqldump)
            // tidak perlu ikut memegang PIN ini.
            putenv('PRADANA_PIN');

            return $dariEnv;
        }

        $tertulis = trim((string) $this->secret($pertanyaan));

        if ($tertulis === '') {
            $this->error('Prompt tersembunyi tidak menerima masukan — bukan PIN-nya yang salah.');
            $this->line('  Ini terjadi kalau perintah jalan sebagai user lain (mis. `sudo -u www-data`):');
            $this->line('  terminalnya milik user login, jadi `stty -echo` tidak bisa dipakai.');
            $this->line('  Isi PIN tanpa menulisnya di baris perintah:');
            $this->line('    read -s "P?PIN 8 digit: "');
            $this->line('    sudo -u www-data env HOME=/tmp PRADANA_PIN="$P" php artisan <perintah> ; unset P');
        }

        return $tertulis;
    }
}
