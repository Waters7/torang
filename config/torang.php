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

];
