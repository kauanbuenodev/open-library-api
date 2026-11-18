<?php
require_once __DIR__ . '/includes/auth.php';
exigirLogin();
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Aplicação acadêmica de busca de livros usando a Open Library.">
    <title>Minha Lista de Leitura</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <header class="hero">
        <div class="hero__content">
            <div class="session-bar">
                <p class="eyebrow">Open Library · Trabalho de LP VIII</p>
                <form method="post" action="logout.php"><button type="submit" class="button-secondary">Sair</button></form>
            </div>
            <h1>Encontre sua próxima leitura.</h1>
            <p class="hero__description">
                Pesquise uma obra e guarde o primeiro resultado na sua lista de leitura.
            </p>

            <form id="search-form" class="search" novalidate>
                <label for="query">Título, autor ou assunto</label>
                <div class="search__controls">
                    <input
                        id="query"
                        name="query"
                        type="search"
                        minlength="2"
                        maxlength="120"
                        placeholder="Ex.: Dom Casmurro"
                        autocomplete="off"
                        required
                    >
                    <button id="search-button" type="submit">Buscar e salvar</button>
                </div>
            </form>

            <div id="message" class="message" role="status" aria-live="polite" hidden></div>
        </div>
    </header>

    <main class="container">
        <section class="list-heading" aria-labelledby="list-title">
            <div>
                <p class="eyebrow">Banco de dados</p>
                <h2 id="list-title">Lista de leitura</h2>
            </div>
            <button id="refresh-button" class="button-secondary" type="button">Atualizar lista</button>
        </section>

        <section class="manual-entry" aria-labelledby="manual-title">
            <h3 id="manual-title">Adicionar livro manualmente</h3>
            <form id="manual-form" class="manual-form" novalidate>
                <label>Título <input name="title" maxlength="255" required></label>
                <label>Autor <input name="authors" maxlength="500" required></label>
                <label>Ano <input name="first_publish_year" type="number" min="1" max="2100"></label>
                <label>ISBN <input name="isbn" maxlength="20"></label>
                <button type="submit">Adicionar</button>
            </form>
            <p id="manual-message" role="status" aria-live="polite"></p>
        </section>

        <div id="loading" class="loading" role="status">Carregando livros...</div>
        <div id="empty-state" class="empty-state" hidden>
            <span aria-hidden="true">⌕</span>
            <h3>Sua lista ainda está vazia</h3>
            <p>Faça uma busca acima para adicionar o primeiro livro.</p>
        </div>
        <div id="book-list" class="book-grid" aria-live="polite"></div>
    </main>

    <template id="book-template">
        <article class="book-card">
            <div class="book-card__cover-wrap">
                <img class="book-card__cover" alt="" loading="lazy">
                <div class="book-card__placeholder" aria-hidden="true">Sem capa</div>
            </div>
            <div class="book-card__body">
                <p class="book-card__year"></p>
                <h3 class="book-card__title"></h3>
                <p class="book-card__authors"></p>
                <dl class="book-card__details">
                    <div>
                        <dt>ISBN</dt>
                        <dd class="book-card__isbn"></dd>
                    </div>
                    <div>
                        <dt>Adicionado</dt>
                        <dd class="book-card__date"></dd>
                    </div>
                </dl>
                <div class="book-card__actions">
                    <a class="book-card__link" target="_blank" rel="noopener noreferrer">Ver na Open Library</a>
                    <button class="book-card__edit" type="button">Editar</button>
                    <?php if (($_SESSION['perfil'] ?? '') === 'admin'): ?>
                    <button class="book-card__delete" type="button">Remover</button>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    </template>

    <script src="app.js" defer></script>
</body>
</html>
