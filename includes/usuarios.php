<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/bootstrap.php';

function buscarUsuarioPorEmail(PDO $db, string $email): ?array
{
    $statement = $db->prepare('SELECT id, nome, email, senha_hash, perfil FROM usuarios WHERE email = ?');
    $statement->execute([$email]);
    return $statement->fetch() ?: null;
}

function inserirUsuario(PDO $db, string $nome, string $email, string $senha): int
{
    $statement = $db->prepare('INSERT INTO usuarios (nome, email, senha_hash, perfil) VALUES (?, ?, ?, ?)');
    $statement->execute([$nome, $email, password_hash($senha, PASSWORD_DEFAULT), 'usuario']);
    return (int) $db->lastInsertId();
}
