<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Pemilik akun Google Calendar pusat
    |--------------------------------------------------------------------------
    |
    | ID 3 adalah Jonathan berdasarkan screenshot.
    | Gunakan user yang sudah menghubungkan calendercaptureit@gmail.com.
    |
    */

    'calendar_account_user_id' => 3,

    /*
    |--------------------------------------------------------------------------
    | Warna event berdasarkan pemilik Activity
    |--------------------------------------------------------------------------
    */

    'sales_colors' => [
        3  => '6',  // Jonathan - oranye
        5  => '9',  // Nicho - biru
        6  => '3',  // Diana - ungu
        7  => '7',  // Jorgie - biru muda
        8  => '4',  // Salsa - merah muda
        9  => '10', // Rudy - hijau
        10 => '5',  // Tiara - kuning
    ],

    // Warna default untuk user yang belum didaftarkan.
    'default_color_id' => '8',
];