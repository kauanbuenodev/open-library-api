<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/usuarios.php';

$erro = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = filter_var(trim((string) ($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $senha = (string) ($_POST['senha'] ?? '');
    if (mb_strlen($nome) < 2 || mb_strlen($nome) > 100 || !$email || strlen($email) > 150 || strlen($senha) < 8) {
        $erro = 'Informe nome (2 a 100 caracteres), e-mail válido e senha com pelo menos 8 caracteres.';
    } else {
        try {
            inserirUsuario(database(), $nome, $email, $senha);
            header('Location: login.php?cadastro=ok');
            exit;
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $erro = 'Não foi possível cadastrar este e-mail.';
            } else {
                error_log($exception->getMessage());
                $erro = 'Não foi possível acessar o banco de dados.';
            }
        }
    }
}
header('Cache-Control: no-store');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastro · Lista de leitura</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="auth-page">
    <main class="auth-card">
        <p class="eyebrow">Lista de leitura</p>
        <h1>Criar conta</h1>
        <?php if ($erro): ?><p class="auth-error" role="alert"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <form method="post">
            <label for="nome">Nome</label>
            <input id="nome" name="nome" maxlength="100" required>
            <label for="email">E-mail</label>
            <input id="email" name="email" type="email" maxlength="150" autocomplete="username" required>
            <label for="senha">Senha (mínimo 8 caracteres)</label>
            <input id="senha" name="senha" type="password" minlength="8" autocomplete="new-password" required>
            <button type="submit">Cadastrar</button>
        </form>
        <p>Já tem conta? <a href="login.php">Entre</a>.</p>
    </main>
</body>
</html>
