<?php

return [

    'default' => env('MAIL_MAILER', 'brevo'),

    'mailers' => [

        'smtp' => [
            'transport'  => 'smtp',
            'host'       => env('MAIL_HOST', 'smtp.gmail.com'),
            'port'       => env('MAIL_PORT', 465),
            'encryption' => env('MAIL_ENCRYPTION', 'ssl'),
            'username'   => env('MAIL_USERNAME'),
            'password'   => env('MAIL_PASSWORD'),
            'timeout'    => (int) env('MAIL_TIMEOUT', 10),
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path'      => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel'   => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'brevo' => [
            'transport'  => 'smtp',
            'host'       => 'smtp-relay.brevo.com',
            'port'       => 587,
            'encryption' => 'tls',
            'username'   => env('BREVO_SMTP_LOGIN'),
            'password'   => env('BREVO_SMTP_KEY'),
            'timeout'    => 10,
        ],

    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@scan2order.com'),
        'name'    => env('MAIL_FROM_NAME', 'Scan2Order'),
    ],

];
