<?php

return [
    // Path ke JSON key Service Account. JANGAN commit file ini ke git.
    // Taruh di storage/app/google/service-account.json (sudah otomatis di-.gitignore Laravel).
    'credentials_path' => env('GOOGLE_DRIVE_CREDENTIALS_PATH', storage_path('app/google/service-account.json')),

    // ID folder root tempat semua arsip disimpan.
    // Folder ini WAJIB sudah di-share (akses Editor) ke email Service Account,
    // atau berupa folder di dalam Shared Drive -- Service Account non-Workspace
    // tidak punya kuota "My Drive" sendiri.
    'root_folder_id' => env('GOOGLE_DRIVE_ROOT_FOLDER_ID'),
];
