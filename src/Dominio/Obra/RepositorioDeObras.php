<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Obra;

interface RepositorioDeObras
{
    public function salvar(Obra $obra): void;

    public function porCodigo(string $codigo): ?Obra;

    /** @return Obra[] ordenadas por código */
    public function todas(): array;

    public function existe(string $codigo): bool;
}
