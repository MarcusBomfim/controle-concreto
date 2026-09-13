<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Estrutura;

interface RepositorioDeElementos
{
    public function salvar(string $obraCodigo, ElementoEstrutural $elemento): void;

    public function porCodigo(string $obraCodigo, string $codigo): ?ElementoEstrutural;

    /** @return ElementoEstrutural[] ordenados por código */
    public function daObra(string $obraCodigo): array;
}
