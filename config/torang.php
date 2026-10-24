<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Akun super admin awal
    |--------------------------------------------------------------------------
    |
    | Dipakai oleh RolesAndAdminSeeder. Bila email atau password kosong,
    | seeder tidak membuat akun.
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Super Admin'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | LibreOffice (konversi laporan UKOM ke PDF)
    |--------------------------------------------------------------------------
    |
    | Nama perintah di PATH atau path lengkap ke soffice. Bila tidak ditemukan,
    | laporan UKOM hanya tersedia dalam format Word (.docx).
    |
    */

    'libreoffice' => [
        'binary' => env('LIBREOFFICE_BINARY', 'soffice'),
    ],

];
