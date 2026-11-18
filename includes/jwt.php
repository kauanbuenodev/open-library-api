<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/bootstrap.php';
require_once __DIR__ . '/permissoes.php';

function jwtSecret(): string
{
    $secret = config()['jwt']['secret'];
    if ($secret !== '') {
        if (strlen($secret) < 32) {
            throw new RuntimeException('JWT_SECRET precisa de pelo menos 32 bytes.');
        }
        return $secret;
    }

    // Chave local persistida fora da pasta pública; compartilhada pelos processos PHP.
    $path = sys_get_temp_dir() . '/open-library-jwt-' . hash('sha256', __DIR__) . '.key';
    $file = fopen($path, 'c+b');
    if ($file === false) {
        throw new RuntimeException('Não foi possível abrir a chave JWT local.');
    }
    try {
        if (!flock($file, LOCK_EX)) {
            throw new RuntimeException('Não foi possível bloquear a chave JWT local.');
        }
        $secret = stream_get_contents($file);
        if ($secret === '') {
            $secret = bin2hex(random_bytes(32));
            if (fwrite($file, $secret) !== strlen($secret) || !fflush($file)) {
                throw new RuntimeException('Não foi possível salvar a chave JWT local.');
            }
            @chmod($path, 0600);
        }
        if (!is_string($secret) || strlen($secret) < 32) {
            throw new RuntimeException('Chave JWT local inválida.');
        }
        return $secret;
    } finally {
        fclose($file);
    }
}

function jwtBase64Encode(string $value): string
{
    return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
}

function jwtBase64Decode(string $value): string
{
    if ($value === '' || !preg_match('/^[A-Za-z0-9_-]+$/D', $value)) {
        throw new ApiException('Token inválido ou expirado.', 401);
    }
    $decoded = base64_decode(strtr($value, '-_', '+/'), true);
    if ($decoded === false || jwtBase64Encode($decoded) !== $value) {
        throw new ApiException('Token inválido ou expirado.', 401);
    }
    return $decoded;
}

function criarJwt(array $usuario): string
{
    $settings = config()['jwt'];
    $now = time();
    $header = jwtBase64Encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
    $payload = jwtBase64Encode(json_encode([
        'iss' => $settings['issuer'],
        'aud' => $settings['audience'],
        'sub' => (string) $usuario['id'],
        'nome' => $usuario['nome'],
        'email' => $usuario['email'],
        'perfil' => $usuario['perfil'],
        'permissoes' => permissoesDoPerfil($usuario['perfil']),
        'iat' => $now,
        'nbf' => $now,
        'exp' => $now + $settings['ttl_seconds'],
        'jti' => bin2hex(random_bytes(16)),
    ], JSON_THROW_ON_ERROR));
    $signed = $header . '.' . $payload;
    return $signed . '.' . jwtBase64Encode(hash_hmac('sha256', $signed, jwtSecret(), true));
}

function validarJwt(string $token): array
{
    if (strlen($token) > 8192 || count($parts = explode('.', $token)) !== 3) {
        throw new ApiException('Token inválido ou expirado.', 401);
    }
    [$headerPart, $payloadPart, $signaturePart] = $parts;
    try {
        $header = json_decode(jwtBase64Decode($headerPart), true, 32, JSON_THROW_ON_ERROR);
        $payload = json_decode(jwtBase64Decode($payloadPart), true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new ApiException('Token inválido ou expirado.', 401);
    }
    if (!is_array($header) || ($header['alg'] ?? '') !== 'HS256'
        || ($header['typ'] ?? '') !== 'JWT' || isset($header['crit'])
        || !is_array($payload)) {
        throw new ApiException('Token inválido ou expirado.', 401);
    }
    $signature = hash_hmac('sha256', $headerPart . '.' . $payloadPart, jwtSecret(), true);
    if (!hash_equals($signature, jwtBase64Decode($signaturePart))) {
        throw new ApiException('Token inválido ou expirado.', 401);
    }
    $settings = config()['jwt'];
    $now = time();
    if (($payload['iss'] ?? null) !== $settings['issuer']
        || ($payload['aud'] ?? null) !== $settings['audience']
        || !is_string($payload['sub'] ?? null)
        || !ctype_digit($payload['sub']) || (int) $payload['sub'] < 1
        || !is_int($payload['exp'] ?? null) || $payload['exp'] <= $now
        || !is_int($payload['nbf'] ?? null) || $payload['nbf'] > $now
        || !is_int($payload['iat'] ?? null) || $payload['iat'] > $now
        || $payload['exp'] <= $payload['iat']) {
        throw new ApiException('Token inválido ou expirado.', 401);
    }
    if (!is_string($payload['nome'] ?? null) || trim($payload['nome']) === ''
        || !is_string($payload['email'] ?? null) || !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)
        || !in_array($payload['perfil'] ?? null, ['usuario', 'admin'], true)
        || !is_array($payload['permissoes'] ?? null)
        || array_values($payload['permissoes']) !== $payload['permissoes']) {
        throw new ApiException('Token inválido ou expirado. Faça login novamente.', 401);
    }
    foreach ($payload['permissoes'] as $permissao) {
        if (!is_string($permissao) || !in_array($permissao, permissoesDoPerfil($payload['perfil']), true)) {
            throw new ApiException('Token inválido ou expirado.', 401);
        }
    }
    return $payload;
}
