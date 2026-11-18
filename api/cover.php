<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/api.php';

try {
    exigirLoginApi();
    exigirPermissaoApi('livros:ler');
    $coverId = filter_input(
        INPUT_GET,
        'id',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($coverId === false || $coverId === null) {
        throw new ApiException('Informe um identificador de capa válido.', 400);
    }

    if (!function_exists('curl_init')) {
        throw new ApiException('A extensão cURL não está habilitada no PHP.', 500);
    }

    $api = config()['open_library'];
    $url = 'https://covers.openlibrary.org/b/id/' . $coverId . '-M.jpg?default=false';
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => $api['timeout_seconds'],
        CURLOPT_HTTPHEADER => [
            'Accept: image/jpeg',
            'User-Agent: ' . $api['user_agent'],
        ],
    ]);

    $image = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    curl_close($curl);

    if ($image === false || $status === 404) {
        http_response_code(404);
        exit;
    }

    if ($status < 200 || $status >= 300 || strpos($contentType, 'image/') !== 0) {
        http_response_code(502);
        exit;
    }

    header('Content-Type: ' . $contentType);
    header('Cache-Control: public, max-age=86400');
    echo $image;
} catch (ApiException $exception) {
    responder($exception->getHttpStatus(), [
        'success' => false,
        'error' => $exception->getMessage(),
    ]);
} catch (Throwable $exception) {
    error_log($exception->getMessage());
    http_response_code(500);
}
