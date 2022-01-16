<?php

require 'src/Sulis/Sulis.php';

Sulis::route('/', function () {
    $jwt = Sulis::jwt();
    $jwtEncode = $jwt->encode(['name' => 'sulis'], 'test');
    $jwtDecode = $jwt->decode($jwtEncode, 'test');

    $validator = Sulis::validator();
    $validator->make($_REQUEST)
        ->rule('required', ['user_name', 'user_email'])
        ->rule('email', 'user_email')
        ->rule('alpha', 'user_name');

    $response = [
        'success' => true,
        'message' => 'Validation passes',
        'errors' => [],
        'data' => [
            'jwt_encode_test' => $jwtEncode,
            'jwt_decode_test' => $jwtDecode,
        ],
    ];

    if (! $validator->validate()) {
        $response = [
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
            'data' => [
                'jwt_encode_test' => $jwtEncode,
                'jwt_decode_test' => $jwtDecode,
            ],
        ];
    }

    return Sulis::json($response);
});

Sulis::start();
