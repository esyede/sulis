<?php

require 'src/Sulis/Sulis.php';

Sulis::route('/', function () {
    $validator = Sulis::validator();

    $validator->make($_REQUEST)
        ->rule('required', ['user_name', 'user_email'])
        ->rule('email', 'user_email')
        ->rule('alpha', 'user_name');

    $response = [
        'success' => true,
        'message' => 'Validation passes',
        'errors' => [],
        'data' => [],
    ];

    if (! $validator->validate()) {
        $response = [
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
            'data' => [],
        ];
    }

    return Sulis::json($response);
});

Sulis::start();
