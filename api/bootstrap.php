<?php

declare(strict_types=1);

final class ApiException extends RuntimeException
{
    private int $httpStatus;

    public function __construct(string $message, int $httpStatus)
    {
        parent::__construct($message);
        $this->httpStatus = $httpStatus;
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }
}

function config(): array
{
    static $config;

    if ($config === null) {
        $config = require __DIR__ . '/config.php';
    }

    return $config;
}

function database(): PDO
{
    static $connection;

    if ($connection instanceof PDO) {
        return $connection;
    }

    $db = config()['database'];
    $connection = new PDO(
        sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $db['host'], $db['port'], $db['name']),
        $db['user'],
        $db['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );

    return $connection;
}

function validateQuery($value): string
{
    if (!is_string($value)) {
        throw new ApiException('O campo "query" é obrigatório e deve ser um texto.', 400);
    }

    $query = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
    $length = function_exists('mb_strlen') ? mb_strlen($query) : strlen($query);

    if ($length < 2 || $length > 120) {
        throw new ApiException('A busca deve ter entre 2 e 120 caracteres.', 400);
    }

    return $query;
}

function searchOpenLibrary(string $query): array
{
    if (!function_exists('curl_init')) {
        throw new ApiException('A extensão cURL não está habilitada no PHP.', 500);
    }

    $api = config()['open_library'];
    $parameters = http_build_query([
        'q' => $query,
        'fields' => 'key,title,author_name,first_publish_year,isbn,cover_i',
        'limit' => 1,
    ]);

    $url = $api['search_url'] . '?' . $parameters;
    for ($attempt = 0; $attempt < 2; $attempt++) {
        $curl = curl_init($url);
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => $api['connect_timeout_seconds'],
            CURLOPT_TIMEOUT => $api['timeout_seconds'],
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'User-Agent: ' . $api['user_agent'],
            ],
        ]);

        $body = curl_exec($curl);
        $curlError = curl_error($curl);
        $curlErrno = curl_errno($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($body !== false || $status !== 0 || !in_array($curlErrno, [CURLE_COULDNT_CONNECT, CURLE_OPERATION_TIMEDOUT], true)) {
            break;
        }
    }

    if ($body === false) {
        error_log('Falha na Open Library: ' . $curlError);
        throw new ApiException('Não foi possível acessar a Open Library agora. Confira a conexão ou cadastre o livro manualmente.', 502);
    }

    if ($status < 200 || $status >= 300) {
        throw new ApiException('A Open Library respondeu com o status HTTP ' . $status . '.', 502);
    }

    try {
        $data = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new ApiException('A Open Library devolveu uma resposta inválida.', 502);
    }

    $document = $data['docs'][0] ?? null;
    if (!is_array($document)) {
        throw new ApiException('Nenhum livro foi encontrado para essa busca.', 404);
    }

    $key = (string) ($document['key'] ?? '');
    if ($key === '') {
        throw new ApiException('O resultado não possui um identificador de obra válido.', 502);
    }
    if (strpos($key, '/') !== 0) {
        $key = '/works/' . $key;
    }

    $authors = array_values(array_filter(
        array_slice((array) ($document['author_name'] ?? []), 0, 5),
        'is_string'
    ));
    $isbnValues = array_values(array_filter((array) ($document['isbn'] ?? []), 'is_string'));
    $coverId = isset($document['cover_i']) ? (int) $document['cover_i'] : null;

    return [
        'open_library_key' => $key,
        'title' => trim((string) ($document['title'] ?? 'Título desconhecido')),
        'authors' => $authors !== [] ? implode(', ', $authors) : 'Autor desconhecido',
        'first_publish_year' => isset($document['first_publish_year'])
            ? (int) $document['first_publish_year']
            : null,
        'isbn' => $isbnValues[0] ?? null,
        'cover_id' => $coverId,
        'cover_url' => $coverId !== null
            ? 'https://covers.openlibrary.org/b/id/' . $coverId . '-M.jpg?default=false'
            : null,
        'search_term' => $query,
    ];
}

function findBookById(PDO $db, int $id): ?array
{
    $statement = $db->prepare(
        'SELECT id, open_library_key, title, authors, first_publish_year, isbn, cover_id, cover_url, search_term, created_at, updated_at
         FROM lista_leitura
         WHERE id = ?'
    );
    $statement->execute([$id]);
    $book = $statement->fetch();

    return $book ?: null;
}

function saveBook(PDO $db, array $book): array
{
    $openLibraryKey = $book['open_library_key'];
    $check = $db->prepare('SELECT id FROM lista_leitura WHERE open_library_key = ?');
    $check->execute([$openLibraryKey]);
    $existing = $check->fetch();

    $statement = $db->prepare(
        'INSERT INTO lista_leitura
            (open_library_key, title, authors, first_publish_year, isbn, cover_id, cover_url, search_term)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            authors = VALUES(authors),
            first_publish_year = VALUES(first_publish_year),
            isbn = VALUES(isbn),
            cover_id = VALUES(cover_id),
            cover_url = VALUES(cover_url),
            search_term = VALUES(search_term),
            updated_at = CURRENT_TIMESTAMP'
    );
    $title = $book['title'];
    $authors = $book['authors'];
    $firstPublishYear = $book['first_publish_year'];
    $isbn = $book['isbn'];
    $coverId = $book['cover_id'];
    $coverUrl = $book['cover_url'];
    $searchTerm = $book['search_term'];
    $statement->execute([
        $openLibraryKey, $title, $authors, $firstPublishYear,
        $isbn, $coverId, $coverUrl, $searchTerm,
    ]);
    $id = $existing ? (int) $existing['id'] : (int) $db->lastInsertId();

    return [
        'created' => $existing === null,
        'book' => findBookById($db, $id),
    ];
}
