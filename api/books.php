<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/livros.php';

try {
    exigirLoginApi();
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($method, ['GET', 'POST', 'PUT', 'DELETE'], true)) {
        header('Allow: GET, POST, PUT, DELETE');
        responder(405, ['success' => false, 'error' => 'Método não permitido.']);
    }

    exigirPermissaoApi([
        'GET' => 'livros:ler', 'POST' => 'livros:criar',
        'PUT' => 'livros:editar', 'DELETE' => 'livros:excluir',
    ][$method]);
    $db = database();
    if ($method === 'GET') {
        if (!isset($_GET['id'])) responder(200, ['success' => true, 'data' => listarLivros($db)]);
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!$id) throw new ApiException('Informe um id inteiro positivo.', 400);
        $book = findBookById($db, $id);
        if (!$book) throw new ApiException('Livro não encontrado.', 404);
        responder(200, ['success' => true, 'data' => $book]);
    }

    if ($method === 'POST') {
        $input = lerJson();
        if (array_key_exists('query', $input)) {
            try {
                $query = validateQuery($input['query']);
            } catch (ApiException $exception) {
                throw new ApiException($exception->getMessage(), 422);
            }
            $saved = saveBook($db, searchOpenLibrary($query));
            responder($saved['created'] ? 201 : 200, [
                'success' => true,
                'message' => $saved['created'] ? 'Livro adicionado.' : 'Livro atualizado.',
                'data' => $saved['book'],
            ]);
        }
        $errors = validarLivro($input);
        if ($errors) responder(422, ['success' => false, 'errors' => $errors, 'error' => implode(' ', $errors)]);
        responder(201, ['success' => true, 'message' => 'Livro cadastrado.', 'data' => criarLivroManual($db, $input)]);
    }

    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) throw new ApiException('Informe um id inteiro positivo.', 400);

    if ($method === 'PUT') {
        $input = lerJson();
        $errors = validarLivro($input);
        if ($errors) responder(422, ['success' => false, 'errors' => $errors, 'error' => implode(' ', $errors)]);
        $book = atualizarLivro($db, $id, $input);
        if (!$book) throw new ApiException('Livro não encontrado.', 404);
        responder(200, ['success' => true, 'message' => 'Livro atualizado.', 'data' => $book]);
    }

    exigirPerfilApi('admin');
    if (!excluirLivro($db, $id)) throw new ApiException('Livro não encontrado.', 404);
    responder(204);
} catch (ApiException $exception) {
    responder($exception->getHttpStatus(), ['success' => false, 'error' => $exception->getMessage()]);
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    responder(500, ['success' => false, 'error' => 'Falha ao acessar o banco. Confira database.sql.']);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    responder(500, ['success' => false, 'error' => 'Erro inesperado no servidor.']);
}
