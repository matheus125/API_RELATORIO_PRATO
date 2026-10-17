<?php
use Firebase\JWT\JWT;
use Firebase\JWT\Key;


function authenticateUser() {
    // 1. Sessão PHP
    if (isset($_SESSION[\Hcode\Model\Funcionarios::SESSION]) && !empty($_SESSION[\Hcode\Model\Funcionarios::SESSION])) {
        return (object) ["funcionarios" => $_SESSION[\Hcode\Model\Funcionarios::SESSION]];
    }

    // 2. Token JWT
    $headers = array_change_key_case(getallheaders(), CASE_LOWER);

    if (!isset($headers['authorization'])) {
        http_response_code(401);
        echo json_encode(["error" => "Token ou sessão não enviados"]);
        exit;
    }

    $token = preg_replace('/^Bearer\s+/i', '', (string)$headers['authorization']);
    $secretKey = function_exists('portal_env') ? trim((string)portal_env('JWT_SECRET', '')) : trim((string)getenv('JWT_SECRET'));
    if ($secretKey === '') {
        http_response_code(503);
        echo json_encode(["error" => "Autenticação JWT não configurada."], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $decoded = JWT::decode($token, new Key($secretKey, 'HS256'));
        return $decoded;
    } catch (Exception $e) {
        http_response_code(401);
        echo json_encode(["error" => "Token inválido: " . $e->getMessage()]);
        exit;
    }
}
