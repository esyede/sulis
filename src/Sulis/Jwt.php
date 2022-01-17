<?php

namespace Sulis;

use DateTime;
use Exception;
use stdClass;

class Jwt
{
    private int $expiration = 60;
    private int $leeway = 0;
    private array $algorithms = [
        'HS256' => 'SHA256',
        'HS512' => 'SHA512',
        'HS384' => 'SHA384',
    ];

    public function encode(array $payloads, string $secret, string $algorithm = 'HS256', array $headers = []): string
    {
        $payloads['exp'] = time() + $this->expiration;
        $payloads['jti'] = uniqid(time());
        $payloads['iat'] = time();

        $headers = array_merge($headers, ['typ' => 'JWT', 'alg' => $algorithm]);
        $headers = $this->urlSafeBase64Encode($this->jsonEncode($headers));
        $payloads = $this->urlSafeBase64Encode($this->jsonEncode($payloads));
        $message = $headers . '.' . $payloads;
        $signature = $this->urlSafeBase64Encode($this->signature($message, $secret, $algorithm));

        return $headers . '.' . $payloads . '.' . $signature;
    }

    public function decode(string $token, string $secret): stdClass
    {
        if (empty($secret)) {
            throw new Exception('Secret may not be empty');
        }

        $jwt = explode('.', $token);

        if (count($jwt) !== 3) {
            throw new Exception('Wrong number of segments');
        }

        [$headers64, $payloads64, $signature64] = $jwt;

        if (null === ($headers = $this->jsonDecode($this->urlSafeBase64Decode($headers64)))) {
            throw new Exception('Invalid header encoding');
        }

        if (null === ($payloads = $this->jsonDecode($this->urlSafeBase64Decode($payloads64)))) {
            throw new Exception('Invalid claims encoding');
        }

        if (false === ($signature = $this->urlSafeBase64Decode($signature64))) {
            throw new Exception('Invalid signature encoding');
        }

        if (empty($headers->alg)) {
            throw new Exception('Empty algorithm');
        }

        if (empty($this->algorithms[$headers->alg])) {
            throw new Exception('Algorithm not supported');
        }

        if (! $this->verify($headers64 . '.' . $payloads64, $signature, $secret, $headers->alg)) {
            throw new Exception('Signature verification failed');
        }

        if (isset($payloads->nbf) && $payloads->nbf > (time() + $this->leeway)) {
            throw new Exception('Cannot handle token prior to ' . date(DateTime::ISO8601, $payloads->nbf));
        }

        if (isset($payloads->iat) && $payloads->iat > (time() + $this->leeway)) {
            throw new Exception('Cannot handle token prior to ' . date(DateTime::ISO8601, $payloads->iat));
        }

        if (isset($payloads->exp) && (time() - $this->leeway) >= $payloads->exp) {
            throw new Exception('Expired token');
        }

        return $payloads;
    }

    private function signature(string $message, string $secret, string $algorithm): string
    {
        if (! array_key_exists($algorithm, $this->algorithms)) {
            throw new Exception('Algorithm not supported');
        }

        return hash_hmac($this->algorithms[$algorithm], $message, $secret, true);
    }

    private function verify(string $message, string $signature, string $secret, string $algorithm): bool
    {
        if (empty($this->algorithms[$algorithm])) {
            throw new Exception('Algorithm not supported');
        }

        $hash = hash_hmac($this->algorithms[$algorithm], $message, $secret, true);

        return hash_equals($signature, $hash);
    }

    private function urlSafeBase64Encode(string $data): string
    {
        return str_replace('=', '', strtr(base64_encode($data), '+/', '-_'));
    }

    private function urlSafeBase64Decode(string $data): string
    {
        $remainder  = strlen($data) % 4;
        $data .= $remainder ? str_repeat('=', 4 - $remainder) : '';

        return base64_decode(strtr($data, '-_', '+/'));
    }

    private function jsonEncode($data): string
    {
        $json = json_encode($data);

        if (JSON_ERROR_NONE !== json_last_error() && $errno = json_last_error()) {
            $this->handleJsonError($errno);
        } elseif ($json === 'null' && $data !== null) {
            throw new Exception('Null result with non-null input');
        }

        return $json;
    }

    private function jsonDecode(string $data)
    {
        $object = json_decode($data, false, 512, JSON_BIGINT_AS_STRING);

        if (JSON_ERROR_NONE !== json_last_error() && $errno = json_last_error()) {
            static::handleJsonError($errno);
        } elseif ($object === null && $data !== 'null') {
            throw new Exception('Null result with non-null input');
        }

        return $object;
    }

    private function handleJsonError(int $errno)
    {
        $messages = [
            JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
            JSON_ERROR_STATE_MISMATCH => 'Invalid or malformed JSON',
            JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
            JSON_ERROR_SYNTAX => 'Syntax error, malformed JSON',
            JSON_ERROR_UTF8 => 'Malformed UTF-8 characters',
        ];

        $message = isset($messages[$errno])
            ? $messages[$errno]
            : sprintf('Unknown JSON error: %s', $errno);

        throw new Exception($message);
    }
}
