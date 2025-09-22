<?php
class JWT {
    public static function encode($payload, $key) {
        $header = json_encode(['typ' => 'JWT', 'alg' => 'HS256']);
        $base64Header = base64_encode($header);
        $base64Payload = base64_encode(json_encode($payload));
        $signature = hash_hmac('sha256', $base64Header . '.' . $base64Payload, $key, true);
        $base64Signature = base64_encode($signature);
        return $base64Header . '.' . $base64Payload . '.' . $base64Signature;
    }

    public static function decode($jwt, $key) {
        [$header, $payload, $signature] = explode('.', $jwt);
        $base64Signature = base64_encode(hash_hmac('sha256', $header . '.' . $payload, $key, true));

        if ($base64Signature !== $signature) {
            throw new Exception('Invalid signature');
        }

        return json_decode(base64_decode($payload));
    }
}
