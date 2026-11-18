<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/usuarios.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !empty($_SESSION['usuario_id'])) {
    header('Location: painel.php');
    exit;
}

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $senha = (string) ($_POST['senha'] ?? '');
    try {
        $usuario = $email ? buscarUsuarioPorEmail(database(), $email) : null;
        if ($usuario && password_verify($senha, $usuario['senha_hash'])) {
            session_regenerate_id(true);
            $_SESSION['usuario_id'] = (int) $usuario['id'];
            $_SESSION['nome'] = $usuario['nome'];
            $_SESSION['perfil'] = $usuario['perfil'];
            header('Location: painel.php');
            exit;
        }
        $erro = 'E-mail ou senha inválidos.';
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        $erro = 'Não foi possível acessar o banco de dados.';
    }
}
header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar · Lista de leitura</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <p class="eyebrow">Lista de leitura</p>
        <h1>Entrar</h1>
        <?php if ($erro): ?><p class="auth-error" role="alert"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <form method="post">
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" autocomplete="username" required>
            <label for="senha">Senha</label>
            <input id="senha" name="senha" type="password" autocomplete="current-password" required>
            <button type="submit">Entrar</button>
        </form>
        <p>Sem conta? <a href="cadastro.php">Cadastre-se</a>.</p>
    </main>
</body>
</html>
