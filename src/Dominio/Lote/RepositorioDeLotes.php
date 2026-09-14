<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Lote;

interface RepositorioDeLotes
{
    /** Grava o lote e os vínculos com as concretagens; devolve o número. */
    public function salvar(Lote $lote): int;

    public function porNumero(string $obraCodigo, int $numero): ?Lote;

    /** @return Lote[] do mais recente para o mais antigo */
    public function daObra(string $obraCodigo): array;

    /** O número do lote em que a concretagem está, ou nulo se em nenhum. */
    public function loteDaConcretagem(string $obraCodigo, int $concretagemNumero): ?int;
}
