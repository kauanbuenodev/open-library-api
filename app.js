const API_URL = 'api/books.php';

const form = document.querySelector('#search-form');
const queryInput = document.querySelector('#query');
const searchButton = document.querySelector('#search-button');
const refreshButton = document.querySelector('#refresh-button');
const message = document.querySelector('#message');
const loading = document.querySelector('#loading');
const emptyState = document.querySelector('#empty-state');
const bookList = document.querySelector('#book-list');
const bookTemplate = document.querySelector('#book-template');
const manualForm = document.querySelector('#manual-form');
const manualMessage = document.querySelector('#manual-message');

async function apiRequest(url, options = {}) {
    let response;

    try {
        response = await fetch(url, options);
    } catch {
        throw new Error('Não foi possível conectar ao servidor PHP. Verifique se o Apache está iniciado.');
    }

    if (response.status === 401) {
        window.location.href = 'login.php';
        throw new Error('Sua sessão expirou. Faça login novamente.');
    }
    if (response.status === 204) return { success: true };

    let payload;
    try {
        payload = await response.json();
    } catch {
        throw new Error(`O servidor respondeu em um formato inesperado (HTTP ${response.status}).`);
    }

    if (!response.ok) {
        throw new Error(payload.error || (payload.errors || []).join(' ') || `A operação falhou com o status HTTP ${response.status}.`);
    }

    return payload;
}

function showMessage(text, type = 'success') {
    message.textContent = text;
    message.classList.toggle('message--error', type === 'error');
    message.hidden = false;
}

function hideMessage() {
    message.hidden = true;
    message.textContent = '';
}

function formatDate(value) {
    if (!value) return 'Data indisponível';
    const date = new Date(value.replace(' ', 'T'));
    return Number.isNaN(date.getTime())
        ? value
        : new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(date);
}

function createBookCard(book) {
    const fragment = bookTemplate.content.cloneNode(true);
    const card = fragment.querySelector('.book-card');
    const cover = fragment.querySelector('.book-card__cover');

    fragment.querySelector('.book-card__year').textContent = book.first_publish_year
        ? `Primeira publicação · ${book.first_publish_year}`
        : 'Ano não informado';
    fragment.querySelector('.book-card__title').textContent = book.title;
    fragment.querySelector('.book-card__authors').textContent = book.authors;
    fragment.querySelector('.book-card__isbn').textContent = book.isbn || 'Não informado';
    fragment.querySelector('.book-card__date').textContent = formatDate(book.created_at);

    if (book.cover_id) {
        cover.src = `api/cover.php?id=${encodeURIComponent(book.cover_id)}`;
        cover.alt = `Capa de ${book.title}`;
        cover.addEventListener('error', () => {
            cover.hidden = true;
        });
    } else {
        cover.hidden = true;
    }

    const openLibraryLink = fragment.querySelector('.book-card__link');
    if (book.open_library_key.startsWith('/works/')) {
        openLibraryLink.href = `https://openlibrary.org${book.open_library_key}`;
    } else {
        openLibraryLink.hidden = true;
    }

    const editButton = fragment.querySelector('.book-card__edit');
    editButton.addEventListener('click', async () => {
        const title = window.prompt('Título:', book.title);
        if (title === null) return;
        const authors = window.prompt('Autor:', book.authors);
        if (authors === null) return;
        const year = window.prompt('Ano de publicação (opcional):', book.first_publish_year || '');
        if (year === null) return;
        const isbn = window.prompt('ISBN (opcional):', book.isbn || '');
        if (isbn === null) return;
        editButton.disabled = true;
        try {
            const payload = await apiRequest(`${API_URL}?id=${encodeURIComponent(book.id)}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ title, authors, first_publish_year: year, isbn }),
            });
            showMessage(payload.message);
            await loadBooks();
        } catch (error) {
            showMessage(error.message, 'error');
        } finally {
            editButton.disabled = false;
        }
    });

    const deleteButton = fragment.querySelector('.book-card__delete');
    deleteButton?.addEventListener('click', async () => {
        const confirmed = window.confirm(`Remover “${book.title}” da lista de leitura?`);
        if (!confirmed) return;

        deleteButton.disabled = true;
        deleteButton.textContent = 'Removendo...';

        try {
            await apiRequest(`${API_URL}?id=${encodeURIComponent(book.id)}`, {
                method: 'DELETE',
            });
            card.remove();
            showMessage('Livro removido da lista.');
            if (!bookList.children.length) emptyState.hidden = false;
        } catch (error) {
            showMessage(error.message, 'error');
            deleteButton.disabled = false;
            deleteButton.textContent = 'Remover';
        }
    });

    return fragment;
}

async function loadBooks() {
    loading.hidden = false;
    emptyState.hidden = true;
    emptyState.querySelector('h3').textContent = 'Sua lista ainda está vazia';
    emptyState.querySelector('p').textContent = 'Faça uma busca acima para adicionar o primeiro livro.';
    bookList.replaceChildren();
    refreshButton.disabled = true;

    try {
        const payload = await apiRequest(API_URL);
        const books = payload.data || [];
        for (const book of books) bookList.append(createBookCard(book));
        emptyState.hidden = books.length !== 0;
    } catch (error) {
        showMessage(error.message, 'error');
        emptyState.hidden = false;
        emptyState.querySelector('h3').textContent = 'Não foi possível carregar a lista';
        emptyState.querySelector('p').textContent = 'Confira o Apache, o MySQL e as configurações do projeto.';
    } finally {
        loading.hidden = true;
        refreshButton.disabled = false;
    }
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    hideMessage();

    const query = queryInput.value.trim();
    if (query.length < 2 || query.length > 120) {
        showMessage('Digite uma busca com 2 a 120 caracteres.', 'error');
        queryInput.focus();
        return;
    }

    searchButton.disabled = true;
    searchButton.textContent = 'Buscando...';

    try {
        const payload = await apiRequest(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ query }),
        });
        showMessage(payload.message);
        form.reset();
        await loadBooks();
    } catch (error) {
        showMessage(error.message, 'error');
    } finally {
        searchButton.disabled = false;
        searchButton.textContent = 'Buscar e salvar';
    }
});

manualForm.addEventListener('submit', async (event) => {
    event.preventDefault();
    manualMessage.textContent = '';
    const fields = Object.fromEntries(new FormData(manualForm).entries());
    const button = manualForm.querySelector('button');
    button.disabled = true;
    try {
        const payload = await apiRequest(API_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(fields),
        });
        manualMessage.textContent = payload.message;
        manualForm.reset();
        await loadBooks();
    } catch (error) {
        manualMessage.textContent = error.message;
    } finally {
        button.disabled = false;
    }
});

refreshButton.addEventListener('click', () => {
    hideMessage();
    loadBooks();
});

loadBooks();
