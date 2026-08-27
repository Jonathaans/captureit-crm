<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Company Information
    |--------------------------------------------------------------------------
    */

    'address' => env('COMPANY_ADDRESS'),

    'phone' => env('COMPANY_PHONE'),

    'email' => env('COMPANY_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | Payment / Bank Information
    |--------------------------------------------------------------------------
    */

    'bank_name' => env('COMPANY_BANK_NAME'),

    'bank_account_number' => env('COMPANY_BANK_ACCOUNT_NUMBER'),

    'bank_account_name' => env('COMPANY_BANK_ACCOUNT_NAME'),

    /*
    |--------------------------------------------------------------------------
    | Text yang akan ditampilkan di PDF
    |--------------------------------------------------------------------------
    */

    'payment_info' => implode("\n", array_filter([
        env('COMPANY_BANK_NAME')
            ? 'Bank: '.env('COMPANY_BANK_NAME')
            : null,

        env('COMPANY_BANK_ACCOUNT_NUMBER')
            ? 'Account No: '.env('COMPANY_BANK_ACCOUNT_NUMBER')
            : null,

        env('COMPANY_BANK_ACCOUNT_NAME')
            ? 'Account Name: '.env('COMPANY_BANK_ACCOUNT_NAME')
            : null,
    ])),
];