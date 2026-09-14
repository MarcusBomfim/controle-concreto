<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Lote;

/**
 * O resultado da conta da norma, com a memória de cálculo.
 *
 * Não guarda só o número: guarda como se chegou nele. Quando um lote é
 * reprovado, a primeira coisa que o engenheiro faz é conferir a conta — e
 * ele precisa ver os valores ordenados, qual fórmula entrou e se o piso do
 * ψ6 prevaleceu.
 */
final class EstimativaDeFck
{
    /**
     * @param float[] $valoresOrdenados resistências dos exemplares, da menor para a maior
     */
    public function __construct(
        public readonly float $fckEstimadoEmMPa,
        public readonly int $numeroDeExemplares,
        public readonly array $valoresOrdenados,
        public readonly TipoDeAmostragem $amostragem,
        public readonly CondicaoDePreparo $condicao,
        public readonly string $metodo,
        public readonly ?float $valorDaFormula,
        public readonly ?float $psi6,
        public readonly ?float $pisoDoPsi6,
    ) {
    }

    /** O piso de ψ6 × f1 ficou acima da fórmula e foi ele que valeu. */
    public function pisoPrevaleceu(): bool
    {
        return $this->pisoDoPsi6 !== null
            && $this->valorDaFormula !== null
            && $this->pisoDoPsi6 > $this->valorDaFormula;
    }

    public function menorExemplar(): float
    {
        return $this->valoresOrdenados[0];
    }

    public function maiorExemplar(): float
    {
        return $this->valoresOrdenados[count($this->valoresOrdenados) - 1];
    }

    public function media(): float
    {
        return round(array_sum($this->valoresOrdenados) / count($this->valoresOrdenados), 1);
    }

    public function atende(float $fckDeProjeto): bool
    {
        return $this->fckEstimadoEmMPa >= $fckDeProjeto - 0.01;
    }
}
