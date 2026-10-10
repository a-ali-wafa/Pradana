/**
 * Verifikasi perilaku public/js/pradana-arsip.js tanpa browser.
 *
 * Cara jalan: `node bin/uji-pradana-arsip.js` (butuh Node di mesin pengembang saja —
 * ini ALAT UJI, bukan bagian aplikasi: tidak ada `package.json`, tidak ada dependency,
 * tidak ada langkah build saat deploy, jadi keputusan X1=a tetap utuh. GitHub Actions
 * menjalankan ini sebagai langkah terakhir karena runner-nya memang punya Node.)
 *
 * Kenapa ada: PHPUnit sudah membuktikan markup-nya benar (form + `data-konfirmasi`),
 * tapi tidak bisa menjalankan JavaScript. Jalur yang justru rawan ada di sisi JS:
 * fallback saat SweetAlert2 hilang, pesan yang tidak boleh masuk HTML, dan guard
 * klik-do-kali. Guard terakhir itu nyatanya BOCOR saat pertama kali diuji di sini —
 * dua klik cepat berarti dua dialog dan dua submit untuk aksi DELETE — dan tidak ada
 * tes PHP yang bisa menemukannya.
 *
 * Stub DOM di bawah sengaja minimal: kalau helper berubah memakai API lain, skrip ini
 * gagal keras, bukan lulus diam-diam.
 */
const fs = require('fs');

function elemen(ATTR) {
  const el = {
    attrs: Object.assign({}, ATTR || {}),
    dataset: {},
    classes: new Set(),
    submitted: 0,
    requestSubmitted: 0,
    focusCalled: 0,
    listeners: {},
  };
  el.getAttribute = (k) => (k in el.attrs ? el.attrs[k] : null);
  el.setAttribute = (k, v) => { el.attrs[k] = String(v); };
  el.matches = (sel) => {
    const m = /^form\[data-konfirmasi\]$/.exec(sel);
    return m ? 'data-konfirmasi' in el.attrs : false;
  };
  el.classList = {
    add: (c) => el.classes.add(c),
    toggle: (c) => {
      if (el.classes.has(c)) { el.classes.delete(c); return false; }
      el.classes.add(c); return true;
    },
    contains: (c) => el.classes.has(c),
  };
  el.submit = () => { el.submitted++; };
  el.requestSubmit = () => { el.requestSubmitted++; };
  el.focus = () => { el.focusCalled++; };
  el.querySelector = () => el;
  el.closest = () => null;
  return el;
}

const state = { submit: [], click: [], ready: [], all: {} };

const kode = fs.readFileSync(
    process.argv[2] || require('path').resolve(__dirname, '..', 'public/js/pradana-arsip.js'),
    'utf8'
);

function jalankan(swat) {
  state.submit = []; state.click = []; state.ready = []; state.all = {};
  const document = {
    readyState: 'loading',
    addEventListener: (jenis, fn) => {
      if (jenis === 'submit') state.submit.push(fn);
      else if (jenis === 'click') state.click.push(fn);
      else state.ready.push(fn);
    },
    querySelectorAll: (sel) => state.all[sel] || [],
    querySelector: (sel) => state.all[sel] ? state.all[sel][0] : null,
  };
  const window = { confirm: () => true, setTimeout: (fn, ms) => setTimeout(fn, ms) };
  const konteks = { document, window, Promise, Object, Array, String, console, global: window };
  const vm = require('vm');
  vm.createContext(konteks);
  vm.runInContext(kode, konteks);
  return { document, window };
}

function kirim(form) {
  const event = { target: form, preventDefault: () => { event.dicegah = true; } };
  state.submit.forEach((fn) => fn(event));
  return event;
}

const hasil = [];
const cek = (nama, benar) => hasil.push((benar ? 'OK   ' : 'GAGAL') + ' ' + nama);
const tunggu = () => new Promise((r) => setTimeout(r, 5));

(async () => {
  // 1. Tanpa SweetAlert2 -> window.confirm dipakai, dan form-submit jalan.
  {
    const { window } = jalankan();
    const form = elemen({ 'data-konfirmasi': 'Pindahkan ke tempat sampah?', 'data-konfirmasi-judul': 'Sampah' });
    let ditanya = '';
    window.confirm = (t) => { ditanya = t; return true; };
    const ev = kirim(form);
    await tunggu();
    cek('tanpa Swal: preventDimakai', ev.dicegah === true);
    cek('tanpa Swal: window.confirm menyebut judul + pesan', ditanya.includes('Sampah') && ditanya.includes('Pindahkan ke tempat sampah?'));
    cek('tanpa Swal: requestSubmit dipakai sekali', form.requestSubmitted === 1 && form.submitted === 0);
  }

  // 2. Without Swal and user REFUSES -> tidak ada submit sama sekali.
  {
    const { window } = jalankan();
    const form = elemen({ 'data-konfirmasi': 'Musnahkan?' });
    window.confirm = () => false;
    kirim(form);
    await tunggu();
    cek('tanpa Swal + tolak: tidak submit', form.submitted === 0 && form.requestSubmitted === 0);
  }

  // 3. With SweetAlert2 -> pesan lewat `text`, BUKAN `html`/`footer` (jalur XSS).
  {
    const { window } = jalankan();
    let opsi = null;
    window.Swal = { fire: (o) => { opsi = o; return Promise.resolve({ isConfirmed: true }); } };
    const form = elemen({
      'data-konfirmasi': 'Hapus «<img src=x onerror=alert(1)>»',
      'data-konfirmasi-catatan': 'catatan <b>tebal</b>',
      'data-konfirmasi-ya': 'Ya, Hapus',
    });
    kirim(form);
    await tunggu();
    cek('dengan Swal: dialog dibuat', !!opsi);
    cek('dengan Swal: teks utuh, tidak jadi markup', opsi && opsi.text.includes('<img') && opsi.text.includes('<b>tebal</b>'));
    cek('dengan Swal: tidak memakai html/footer', opsi && !('html' in opsi) && !('footer' in opsi));
    cek('dengan Swal: tombol yes pakai label halaman', opsi && opsi.confirmButtonText === 'Ya, Hapus');
    cek('dengan Swal: requestSubmit jalan', form.requestSubmitted === 1);
  }

  // 4. Dua klik cepat -> flag membuat event kedua lolos tanpa konfirmasi ulang.
  {
    const { window } = jalankan();
    let tanya = 0;
    window.Swal = { fire: () => { tanya++; return Promise.resolve({ isConfirmed: true }); } };
    const form = elemen({ 'data-konfirmasi': 'X' });
    kirim(form); kirim(form);
    await tunggu();
    cek('dua klik: hanya satu dialog', tanya === 1);
    cek('dua klik: hanya satu submit', form.requestSubmitted === 1);
  }

  // 4b. Dibatalkan lalu ditekan lagi -> tetap bertanya (flag tidak boleh tertinggal).
  {
    const { window } = jalankan();
    let tanya = 0;
    window.Swal = { fire: () => { tanya++; return Promise.resolve({ isConfirmed: false }); } };
    const form = elemen({ 'data-konfirmasi': 'X' });
    kirim(form);
    await tunggu();
    cek('batal: tidak submit', form.requestSubmitted === 0);
    kirim(form);
    await tunggu();
    cek('batal lalu coba lagi: ditanya lagi', tanya === 2);
  }

  // 4c. Validasi HTML5 menahan requestSubmit -> status harus bersih lagi.
  {
    const { window } = jalankan();
    let tanya = 0;
    window.Swal = { fire: () => { tanya++; return Promise.resolve({ isConfirmed: true }); } };
    const form = elemen({ 'data-konfirmasi': 'X' });
    form.requestSubmit = () => { form.requestSubmitted++; /* browser menolak: kolom wajib kosong */ };
    kirim(form);
    await tunggu();
    await tunggu();
    kirim(form);
    await tunggu();
    cek('setelah terkirim/ditolak browser: masih mau bertanya lagi', tanya === 2);
  }

  // 5. Form tanpa data-konfirmasi tidak disentuh.
  {
    jalankan();
    const form = elemen({});
    const ev = kirim(form);
    await tunggu();
    cek('form biasa: tidak dicegah', ev.dicegah === undefined);
  }

  // 6. Blok data-pradana-tertutup ditutup OLEH script, lalu dibuka oleh tombol.
  {
    jalankan();
    const blok = elemen({ id: 'form-tolak-1' });
    blok.classes.delete('d-none');
    const tombol = elemen({ 'data-pengubah': '#form-tolak-1' });
    tombol.closest = (sel) => (sel === '[data-pengubah]' ? tombol : null);
    state.all['[data-pradana-tertutup]'] = [blok];
    state.all['#form-tolak-1'] = [blok];
    state.ready.forEach((fn) => fn());
    cek('init: blok tertutup disembunyikan', blok.classes.has('d-none') && blok.getAttribute('aria-hidden') === 'true');
    const ev = { target: tombol };
    state.click.forEach((fn) => fn(ev));
    cek('klik tombol: blok terbuka lagi', !blok.classes.has('d-none') && blok.getAttribute('aria-hidden') === 'false');
    cek('klik tombol: fokus ke isian', blok.focusCalled === 1);
  }

  console.log(hasil.join('\n'));

  const gagal = hasil.filter((h) => h.startsWith('GAGAL'));

  console.log(gagal.length ? '\n' + gagal.length + ' jalur helper GAGAL' : '\nsemua jalur helper terverifikasi');

  // Keluar dengan kode bukan-nol supaya CI benar-benar gagal, bukan cuma mencetak.
  process.exitCode = gagal.length ? 1 : 0;
})();
