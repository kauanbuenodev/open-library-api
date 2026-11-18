<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/usuarios.php';

try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        responder(405, ['success' => false, 'error' => 'Método não permitido. Use POST.']);
    }
    $contentType = strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0]));
    if ($contentType !== 'application/json') {
        responder(415, ['success' => false, 'error' => 'Use Body > raw > JSON e Content-Type: application/json.']);
    }
    $input = lerJson();
    $email = is_string($input['email'] ?? null) ? trim($input['email']) : '';
    $senha = is_string($input['senha'] ?? null) ? $input['senha'] : '';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
        responder(422, ['success' => false, 'error' => 'Informe email válido e senha como texto.']);
    }
    $usuario = buscarUsuarioPorEmail(database(), $email);
    // Hash fictício evita pular o cálculo quando o e-mail não está cadastrado.
    $hash = $usuario['senha_hash'] ?? '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';
    $valid = password_verify($senha, $hash);
    if (!$usuario || !$valid) {
        responder(401, ['success' => false, 'error' => 'E-mail ou senha inválidos.']);
    }
    responder(200, [
        'success' => true,
        'access_token' => criarJwt($usuario),
        'token_type' => 'Bearer',
        'expires_in' => config()['jwt']['ttl_seconds'],
        'usuario' => [
            'id' => (int) $usuario['id'], 'nome' => $usuario['nome'],
            'email' => $usuario['email'], 'perfil' => $usuario['perfil'],
            'permissoes' => permissoesDoPerfil($usuario['perfil']),
        ],
    ]);
} catch (ApiException $exception) {
    responder($exception->getHttpStatus(), ['success' => false, 'error' => $exception->getMessage()]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    responder(500, ['success' => false, 'error' => 'Não foi possível realizar o login. Confira o banco e a configuração JWT.']);
}
