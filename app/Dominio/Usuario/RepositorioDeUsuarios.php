<?php

declare(strict_types=1);

namespace App\Dominio\Usuario;

interface RepositorioDeUsuarios
{
    public function salvar(Usuario $usuario): void;

    public function porEmail(string $email): ?Usuario;

    /** @return Usuario[] ordenados por nome */
    public function todos(): array;

    public function existe(string $email): bool;
}
