<?php

declare(strict_types=1);

namespace App\Dominio\NaoConformidade;

interface RepositorioDeNaoConformidades
{
    /** Grava a não conformidade e as providências; a chave é o lote. */
    public function salvar(NaoConformidade $naoConformidade): void;

    public function doLote(string $obraCodigo, int $loteNumero): ?NaoConformidade;

    /** @return NaoConformidade[] da mais recente para a mais antiga */
    public function daObra(string $obraCodigo): array;

    /** @return NaoConformidade[] de todas as obras, as que ainda esperam decisão */
    public function abertas(): array;
}
