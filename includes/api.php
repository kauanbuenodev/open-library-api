<?php

declare(strict_types=1);

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/jwt.php';

function responder(int $status, ?array $payload = null): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    if ($payload !== null && $status !== 204) {
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

function lerJson(): array
{
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || trim($rawBody) === '') {
        throw new ApiException('Envie um corpo JSON.', 400);
    }
    try {
        $data = json_decode($rawBody, false, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new ApiException('O corpo da requisição não contém um JSON válido.', 400);
    }
    if (!$data instanceof stdClass) {
        throw new ApiException('O corpo JSON precisa ser um objeto.', 400);
    }
    return (array) $data;
}

function exigirLoginApi(): array
{
    static $usuario = null;
    if ($usuario !== null) return $usuario;

    $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    if ($authorization === null && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) $authorization = $value;
        }
    }
    if ($authorization !== null) {
        // Um Bearer inválido nunca recorre ao cookie de sessão como alternativa.
        if (!preg_match('/^Bearer\s+(\S+)$/iD', trim($authorization), $matches)) {
            throw new ApiException('Use Authorization: Bearer <token>.', 401);
        }
        $payload = validarJwt($matches[1]);
        $statement = database()->prepare('SELECT id, nome, email, perfil FROM usuarios WHERE id = ?');
        $statement->execute([$payload['sub']]);
        $usuario = $statement->fetch();
        if (!$usuario) {
            throw new ApiException('Token inválido ou expirado.', 401);
        }
        // A permissão precisa existir no JWT e continuar permitida no banco.
        $usuario['permissoes'] = array_values(array_intersect(
            $payload['permissoes'], permissoesDoPerfil($usuario['perfil'])
        ));
        return $usuario;
    }
    if (empty($_SESSION['usuario_id'])) {
        responder(401, ['success' => false, 'error' => 'Faça login para continuar.']);
    }
    $perfil = $_SESSION['perfil'] ?? 'usuario';
    return $usuario = [
        'id' => $_SESSION['usuario_id'], 'perfil' => $perfil,
        'permissoes' => permissoesDoPerfil($perfil),
    ];
}

function exigirPermissaoApi(string $permissao): void
{
    $usuario = exigirLoginApi();
    if (!in_array($permissao, $usuario['permissoes'], true)) {
        responder(403, ['success' => false, 'error' => 'Você não tem permissão para esta ação.']);
    }
}

function exigirPerfilApi(string $perfil): void
{
    $usuario = exigirLoginApi();
    if (($usuario['perfil'] ?? '') !== $perfil) {
        responder(403, ['success' => false, 'error' => 'Você não tem permissão para esta ação.']);
    }
}
