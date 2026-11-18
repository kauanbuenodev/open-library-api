<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/jwt.php';

$checks = 0;
function check(bool $condition, string $name): void
{
    global $checks;
    if (!$condition) throw new RuntimeException('Falhou: ' . $name);
    $checks++;
}
function rejected(string $token, string $name): void
{
    try {
        validarJwt($token);
    } catch (ApiException $exception) {
        check($exception->getHttpStatus() === 401, $name);
        return;
    }
    throw new RuntimeException('Token aceito indevidamente: ' . $name);
}
function signed(array $claims, array $header = ['alg' => 'HS256', 'typ' => 'JWT']): string
{
    $body = jwtBase64Encode(json_encode($header)) . '.' . jwtBase64Encode(json_encode($claims));
    return $body . '.' . jwtBase64Encode(hash_hmac('sha256', $body, jwtSecret(), true));
}

$token = criarJwt(['id' => 123, 'nome' => 'Teste JWT', 'email' => 'jwt@example.invalid', 'perfil' => 'usuario']);
$claims = validarJwt($token);
check($claims['sub'] === '123', 'identidade');
check($claims['exp'] - $claims['iat'] === 3600, 'validade de uma hora');
check(!isset($claims['senha']), 'sem senha no payload');
check(!isset($claims['senha_hash']), 'sem hash no payload');
check($claims['nome'] === 'Teste JWT' && $claims['email'] === 'jwt@example.invalid', 'dados públicos no JWT');
check($claims['perfil'] === 'usuario', 'perfil no JWT');
check($claims['permissoes'] === ['livros:ler', 'livros:criar', 'livros:editar'], 'permissões do usuário');
$adminClaims = validarJwt(criarJwt(['id' => 124, 'nome' => 'Admin', 'email' => 'admin@example.invalid', 'perfil' => 'admin']));
check(in_array('livros:excluir', $adminClaims['permissoes'], true), 'admin pode excluir');
rejected($token . '.extra', 'segmento extra');
$parts = explode('.', $token);
$altered = $claims; $altered['sub'] = '999';
rejected($parts[0] . '.' . jwtBase64Encode(json_encode($altered)) . '.' . $parts[2], 'payload adulterado');
$altered = $claims; $altered['perfil'] = 'admin'; $altered['permissoes'][] = 'livros:excluir';
rejected($parts[0] . '.' . jwtBase64Encode(json_encode($altered)) . '.' . $parts[2], 'permissões adulteradas sem nova assinatura');
foreach ([
    'expirado' => ['exp' => time() - 1],
    'expiração como texto' => ['exp' => (string) (time() + 60)],
    'emissor incorreto' => ['iss' => 'outro'],
    'audiência incorreta' => ['aud' => 'outra'],
    'ainda não válido' => ['nbf' => time() + 100],
    'emissão futura' => ['iat' => time() + 100],
    'sub inválido' => ['sub' => '-1'],
    'perfil desconhecido' => ['perfil' => 'superadmin'],
    'permissões como texto' => ['permissoes' => 'livros:excluir'],
    'permissões sem lista' => ['permissoes' => ['excluir' => 'livros:excluir']],
    'permissão incompatível com perfil' => ['permissoes' => ['livros:excluir']],
    'permissão inexistente' => ['permissoes' => ['usuarios:excluir']],
] as $name => $changes) rejected(signed(array_replace($claims, $changes)), $name);
$legacy = $claims; unset($legacy['permissoes']);
rejected(signed($legacy), 'token antigo exige novo login');
rejected(signed($claims, ['alg' => 'none', 'typ' => 'JWT']), 'alg none');
rejected(signed($claims, ['alg' => 'HS512', 'typ' => 'JWT']), 'algoritmo diferente');
rejected('abc.@@.xyz', 'base64 inválido');
echo "JWT: {$checks} verificações passaram.\n";

// Integração opcional: php tests/jwt.php http://127.0.0.1:8099
if (empty($argv[1])) exit;
$base = rtrim($argv[1], '/');
function callApi(string $method, string $path, ?string $body = null, ?string $token = null, string $type = 'application/json'): array
{
    global $base;
    $curl = curl_init($base . $path);
    $headers = ['Content-Type: ' . $type];
    if ($token !== null) $headers[] = 'Authorization: Bearer ' . $token;
    curl_setopt_array($curl, [CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $headers, CURLOPT_TIMEOUT => 10]);
    if ($body !== null) curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($curl);
    if ($raw === false) throw new RuntimeException(curl_error($curl));
    $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);
    return [$status, json_decode($raw, true)];
}

$db = database();
$ids = []; $bookId = null;
$password = bin2hex(random_bytes(16));
try {
    $emails = [];
    foreach (['usuario', 'admin'] as $role) {
        $email = 'jwt-test-' . bin2hex(random_bytes(8)) . '@example.invalid';
        $stmt = $db->prepare('INSERT INTO usuarios (nome,email,senha_hash,perfil) VALUES (?,?,?,?)');
        $stmt->execute(['Teste JWT', $email, password_hash($password, PASSWORD_DEFAULT), $role]);
        $ids[] = (int) $db->lastInsertId(); $emails[$role] = $email;
    }
    check(callApi('GET', '/api/login.php')[0] === 405, 'login método');
    check(callApi('POST', '/api/login.php', '{}', null, 'text/plain')[0] === 415, 'Content-Type');
    check(callApi('POST', '/api/login.php', '{')[0] === 400, 'JSON malformado');
    check(callApi('POST', '/api/login.php', '[]')[0] === 400, 'JSON deve ser objeto');
    check(callApi('POST', '/api/login.php', '{}')[0] === 422, 'campos obrigatórios');
    check(callApi('POST', '/api/login.php', json_encode(['email' => $emails['usuario'], 'senha' => 'errada']))[0] === 401, 'senha errada');
    $tokens = [];
    foreach ($emails as $role => $email) {
        [$status, $data] = callApi('POST', '/api/login.php', json_encode([
            'email' => $email, 'senha' => $password,
            'perfil' => 'admin', 'permissoes' => ['livros:excluir'],
        ]));
        check($status === 200 && $data['token_type'] === 'Bearer' && $data['expires_in'] === 3600, 'login ' . $role);
        check(!isset($data['usuario']['senha_hash']), 'resposta sem hash');
        $decoded = json_decode(jwtBase64Decode(explode('.', $data['access_token'])[1]), true);
        check($decoded['email'] === $email && $decoded['nome'] === 'Teste JWT', 'identidade no JWT de login');
        check($decoded['perfil'] === $role, 'perfil correto no JWT de login');
        check($decoded['permissoes'] === permissoesDoPerfil($role), 'permissões corretas no JWT de login');
        check($data['usuario']['permissoes'] === $decoded['permissoes'], 'permissões da resposta e JWT iguais');
        $tokens[$role] = $data['access_token'];
    }
    $parts = explode('.', $tokens['usuario']);
    $fakeClaims = json_decode(jwtBase64Decode($parts[1]), true);
    $fakeClaims['perfil'] = 'admin'; $fakeClaims['permissoes'][] = 'livros:excluir';
    $forged = $parts[0] . '.' . jwtBase64Encode(json_encode($fakeClaims)) . '.' . $parts[2];
    check(callApi('GET', '/api/books.php', null, $forged)[0] === 401, 'API rejeita alteração de perfil e permissões no JWT');
    check(callApi('GET', '/api/books.php')[0] === 401, 'sem autenticação');
    check(callApi('GET', '/api/books.php', null, 'invalido')[0] === 401, 'Bearer inválido');
    check(callApi('GET', '/api/books.php', null, $tokens['usuario'])[0] === 200, 'Bearer válido');
    check(callApi('GET', '/api/cover.php', null, $tokens['usuario'])[0] === 400, 'capa aceita autenticação JWT');
    [$status, $data] = callApi('POST', '/api/books.php', json_encode(['title' => 'Teste JWT', 'authors' => 'Teste']), $tokens['usuario']);
    $bookId = $data['data']['id'] ?? null;
    check($status === 201 && $bookId !== null, 'cadastro raw');
    check(callApi('PUT', '/api/books.php?id=' . $bookId, json_encode(['title' => 'Editado JWT', 'authors' => 'Teste']), $tokens['usuario'])[0] === 200, 'edição raw');
    check(callApi('DELETE', '/api/books.php?id=' . $bookId, null, $tokens['usuario'])[0] === 403, 'usuário não exclui');
    check(callApi('DELETE', '/api/books.php?id=' . $bookId, null, $tokens['admin'])[0] === 204, 'admin exclui');
    $stmt = $db->prepare('UPDATE usuarios SET perfil = ? WHERE id = ?');
    $stmt->execute(['usuario', $ids[1]]);
    check(callApi('DELETE', '/api/books.php?id=1', null, $tokens['admin'])[0] === 403, 'perfil consultado no banco');
    check(callApi('GET', '/api/books.php', null, $tokens['admin'])[0] === 200, 'admin rebaixado mantém leitura');
    $stmt->execute(['admin', $ids[0]]);
    check(callApi('DELETE', '/api/books.php?id=1', null, $tokens['usuario'])[0] === 403, 'promoção não amplia permissões do token antigo');
    [$status, $data] = callApi('POST', '/api/login.php', json_encode(['email' => $emails['usuario'], 'senha' => $password]));
    check($status === 200 && in_array('livros:excluir', $data['usuario']['permissoes'], true), 'novo login recebe permissões da promoção');
    $stmt = $db->prepare('DELETE FROM usuarios WHERE id = ?');
    $stmt->execute([$ids[0]]);
    check(callApi('GET', '/api/books.php', null, $tokens['usuario'])[0] === 401, 'usuário excluído perde acesso');
    echo "JWT + HTTP: {$checks} verificações passaram.\n";
} finally {
    if ($bookId !== null) $db->prepare('DELETE FROM lista_leitura WHERE id = ?')->execute([$bookId]);
    foreach ($ids as $id) $db->prepare('DELETE FROM usuarios WHERE id = ?')->execute([$id]);
}
