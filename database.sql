CREATE DATABASE IF NOT EXISTS open_library_app
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE open_library_app;

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    senha_hash VARCHAR(255) NOT NULL,
    perfil VARCHAR(20) NOT NULL DEFAULT 'usuario'
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS lista_leitura (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    open_library_key VARCHAR(40) NOT NULL,
    title VARCHAR(255) NOT NULL,
    authors VARCHAR(500) NOT NULL,
    first_publish_year SMALLINT UNSIGNED NULL,
    isbn VARCHAR(20) NULL,
    cover_id BIGINT UNSIGNED NULL,
    cover_url VARCHAR(512) NULL,
    search_term VARCHAR(120) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_lista_leitura_open_library_key UNIQUE (open_library_key)
) ENGINE=InnoDB;

INSERT IGNORE INTO lista_leitura
    (open_library_key, title, authors, first_publish_year, search_term)
VALUES
    ('local:dom-casmurro', 'Dom Casmurro', 'Machado de Assis', 1899, 'exemplo inicial'),
    ('local:o-cortico', 'O Cortiço', 'Aluísio Azevedo', 1890, 'exemplo inicial');
