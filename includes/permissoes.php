<?php

declare(strict_types=1);

function permissoesDoPerfil(string $perfil): array
{
    $permissoes = ['livros:ler', 'livros:criar', 'livros:editar'];
    if ($perfil === 'admin') return [...$permissoes, 'livros:excluir'];
    return $perfil === 'usuario' ? $permissoes : [];
}
