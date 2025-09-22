<?php
require_once 'JWT.php';
require_once 'Key.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "your_secret_key";

header("Content-Type: application/json");

$headers = getallheaders();
$authHeader = $headers['Authorization'] ?? '';

if (preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
    $jwt = $matches[1];
    try {
        $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
        echo json_encode(["message" => "トークン検証成功", "data" => $decoded]);
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["message" => "トークン検証失敗", "error" => $e->getMessage()]);
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "トークンが提供されていません"]);
}
