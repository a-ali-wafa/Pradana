#!/usr/bin/env python3
"""
Pemeriksa berkas font hasil subset (dipakai bin/buat-subset-ikon.php).

Kenapa perlu: pyftsubset tidak menjamin setiap codepoint yang diminta benar-benar
ada di font sumber. Font Awesome memuat nama ikon yang glyph-nya kosong, dan
kalau codepoint itu diminta tapi tidak dihasilkan, ikonnya hilang di layar tanpa
satu pun pesan -- kelas bug yang sudah dua kali terjadi di proyek ini (ikon
"Manajemen User" 12 Okt).

Cara pakai:
    python bin/cek-kodepoin-font.py <berkas-font.woff2> U+F002,U+F0C0,...

Keluaran: "OK <jumlah-glyph>" kalau lengkap, "KURANG: ..." kalau tidak.
"""

import sys

from fontTools.ttLib import TTFont


def utama():
    if len(sys.argv) != 3:
        print("PAKAI: python bin/cek-kodepoin-font.py <font> U+XXXX,U+YYYY,...")
        return 2

    font = TTFont(sys.argv[1])
    peta = font.getBestCmap()
    minta = []
    for bagian in sys.argv[2].split(","):
        bagian = bagian.strip()
        if not bagian:
            continue
        if not bagian.upper().startswith("U+"):
            print("BENTUK TIDAK DIKENAL: " + bagian)
            return 1
        minta.append(int(bagian[2:], 16))

    hilang = [code for code in minta if code not in peta]
    if hilang:
        print("KURANG: " + ", ".join("U+%04X" % code for code in hilang))
        return 1

    print("OK {} glyph".format(len(font.getGlyphOrder())))
    return 0


if __name__ == "__main__":
    sys.exit(utama())
