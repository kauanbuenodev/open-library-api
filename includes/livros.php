<?php

declare(strict_types=1);

require_once __DIR__ . '/../api/bootstrap.php';

function listarLivros(PDO $db): array
{
    return $db->query('SELECT * FROM lista_leitura ORDER BY created_at DESC, id DESC')->fetchAll();
}

function validarLivro(array $input): array
{
    $errors = [];
    $title = is_string($input['title'] ?? null) ? trim($input['title']) : '';
    $authors = is_string($input['authors'] ?? null) ? trim($input['authors']) : '';
    $year = $input['first_publish_year'] ?? null;
    $isbn = is_string($input['isbn'] ?? null) ? trim($input['isbn']) : '';

    if ($title === '' || mb_strlen($title) > 255) $errors[] = 'Título deve ter de 1 a 255 caracteres.';
    if ($authors === '' || mb_strlen($authors) > 500) $errors[] = 'Autor deve ter de 1 a 500 caracteres.';
    if ($year !== null && $year !== '' && (!is_int($year) && (!is_string($year) || !ctype_digit($year)) || (int) $year < 1 || (int) $year > 2100)) {
        $errors[] = 'Ano de publicação inválido.';
    }
    if (isset($input['isbn']) && !is_string($input['isbn'])) $errors[] = 'ISBN deve ser um texto.';
    if (strlen($isbn) > 20) $errors[] = 'ISBN deve ter no máximo 20 caracteres.';
    return $errors;
}

function criarLivroManual(PDO $db, array $input): array
{
    $statement = $db->prepare('INSERT INTO lista_leitura
        (open_library_key, title, authors, first_publish_year, isbn, search_term)
        VALUES (?, ?, ?, ?, ?, ?)');
    $statement->execute([
        'local:' . bin2hex(random_bytes(12)), trim($input['title']), trim($input['authors']),
        ($input['first_publish_year'] ?? null) === '' ? null : ($input['first_publish_year'] ?? null),
        trim((string) ($input['isbn'] ?? '')) ?: null, 'cadastro manual',
    ]);
    return findBookById($db, (int) $db->lastInsertId());
}

function atualizarLivro(PDO $db, int $id, array $input): ?array
{
    if (!findBookById($db, $id)) return null;
    $statement = $db->prepare('UPDATE lista_leitura
        SET title = ?, authors = ?, first_publish_year = ?, isbn = ? WHERE id = ?');
    $statement->execute([
        trim($input['title']), trim($input['authors']),
        ($input['first_publish_year'] ?? null) === '' ? null : ($input['first_publish_year'] ?? null),
        trim((string) ($input['isbn'] ?? '')) ?: null, $id,
    ]);
    return findBookById($db, $id);
}

function excluirLivro(PDO $db, int $id): bool
{
    $statement = $db->prepare('DELETE FROM lista_leitura WHERE id = ?');
    $statement->execute([$id]);
    return $statement->rowCount() > 0;
}
