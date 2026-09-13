<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Concretagem;

interface RepositorioDeConcretagens
{
    /**
     * Grava a concretagem com cargas, exemplares e corpos de prova, e devolve
     * o número sequencial dentro da obra. Concretagem já numerada é
     * atualizada — a situação muda, e cargas e exemplares novos entram.
     */
    public function salvar(Concretagem $concretagem): int;

    public function porNumero(string $obraCodigo, int $numero): ?Concretagem;

    /** @return Concretagem[] da mais recente para a mais antiga */
    public function daObra(string $obraCodigo): array;

    /** @return Concretagem[] da mais antiga para a mais recente */
    public function doElemento(string $obraCodigo, string $elementoCodigo): array;
}
