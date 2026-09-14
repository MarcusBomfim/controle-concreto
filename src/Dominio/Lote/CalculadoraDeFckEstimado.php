<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Lote;

use ControleConcreto\Dominio\ExcecaoDeDominio;

/**
 * A conta do item 6.2.3 da NBR 12655: do conjunto de exemplares ao fck
 * estimado do lote.
 *
 * ATENÇÃO — as fórmulas e os limiares abaixo foram transcritos de memória.
 * É a conta que aprova ou reprova uma laje. Antes de uso real, cada linha
 * precisa ser conferida contra o texto vigente da norma, e o teste que
 * acompanha esta classe precisa ser recalculado à mão a partir dele.
 *
 * O que está implementado, como eu me lembro:
 *
 *  Amostragem TOTAL
 *    n ≤ 20  →  fck,est = f1                    (o menor exemplar)
 *    n > 20  →  fck,est = f(i), i = ⌈0,05 n⌉    (o percentil de 5 %)
 *
 *  Amostragem PARCIAL
 *    n < 6   →  amostra insuficiente; o lote não pode ser julgado
 *    6 ≤ n < 20:
 *       m = ⌊n/2⌋ (n ímpar: despreza-se o maior valor)
 *       fórmula = 2·(f1 + … + f(m−1))/(m−1) − f(m)
 *       fck,est = máx(fórmula, ψ6 × f1)
 *    n ≥ 20  →  fck,est = f(i), i = ⌈0,05 n⌉
 *
 * Os f são as resistências dos exemplares ordenadas da menor para a maior.
 */
final class CalculadoraDeFckEstimado
{
    public const MINIMO_PARA_AMOSTRAGEM_PARCIAL = 6;
    public const LIMIAR_DA_AMOSTRAGEM_GRANDE = 20;

    private function __construct()
    {
    }

    /**
     * @param float[] $resistencias resistências dos exemplares, em qualquer ordem
     */
    public static function calcular(
        array $resistencias,
        TipoDeAmostragem $amostragem,
        CondicaoDePreparo $condicao,
    ): EstimativaDeFck {
        $valores = array_values(array_map(static fn (float $v): float => $v, $resistencias));
        sort($valores);

        $n = count($valores);

        if ($n === 0) {
            throw new ExcecaoDeDominio('Não há exemplar com resultado para estimar o fck.');
        }

        return match ($amostragem) {
            TipoDeAmostragem::Total => self::amostragemTotal($valores, $condicao),
            TipoDeAmostragem::Parcial => self::amostragemParcial($valores, $condicao),
        };
    }

    /** @param float[] $f ordenados */
    private static function amostragemTotal(array $f, CondicaoDePreparo $condicao): EstimativaDeFck
    {
        $n = count($f);

        if ($n <= self::LIMIAR_DA_AMOSTRAGEM_GRANDE) {
            return new EstimativaDeFck(
                $f[0],
                $n,
                $f,
                TipoDeAmostragem::Total,
                $condicao,
                'Amostragem total com n ≤ 20: fck,est é o menor exemplar (f1).',
                null,
                null,
                null,
            );
        }

        return self::percentilDeCincoPorCento($f, TipoDeAmostragem::Total, $condicao);
    }

    /** @param float[] $f ordenados */
    private static function amostragemParcial(array $f, CondicaoDePreparo $condicao): EstimativaDeFck
    {
        $n = count($f);

        if ($n < self::MINIMO_PARA_AMOSTRAGEM_PARCIAL) {
            throw new ExcecaoDeDominio(sprintf(
                'Amostragem parcial exige ao menos %d exemplares com resultado; há %d. '
                . 'Junte mais concretagens ao lote ou aguarde os rompimentos pendentes.',
                self::MINIMO_PARA_AMOSTRAGEM_PARCIAL,
                $n,
            ));
        }

        if ($n >= self::LIMIAR_DA_AMOSTRAGEM_GRANDE) {
            return self::percentilDeCincoPorCento($f, TipoDeAmostragem::Parcial, $condicao);
        }

        // m = n/2, desprezando o maior valor quando n é ímpar.
        $m = intdiv($n, 2);
        $somaDosMenores = array_sum(array_slice($f, 0, $m - 1));
        $formula = 2 * $somaDosMenores / ($m - 1) - $f[$m - 1];

        $psi6 = Psi6::para($n, $condicao);
        $piso = $psi6 * $f[0];

        $fckEstimado = max($formula, $piso);

        return new EstimativaDeFck(
            round($fckEstimado, 1),
            $n,
            $f,
            TipoDeAmostragem::Parcial,
            $condicao,
            sprintf(
                'Amostragem parcial com 6 ≤ n < 20: m = %d; fórmula 2·(f1…f%d)/%d − f%d = %s; '
                . 'piso ψ6 × f1 = %s × %s = %s; vale o maior.',
                $m,
                $m - 1,
                $m - 1,
                $m,
                number_format($formula, 1, ',', '.'),
                number_format($psi6, 2, ',', '.'),
                number_format($f[0], 1, ',', '.'),
                number_format($piso, 1, ',', '.'),
            ),
            round($formula, 1),
            $psi6,
            round($piso, 1),
        );
    }

    /** @param float[] $f ordenados */
    private static function percentilDeCincoPorCento(
        array $f,
        TipoDeAmostragem $amostragem,
        CondicaoDePreparo $condicao,
    ): EstimativaDeFck {
        $n = count($f);

        // i = 0,05·n; fracionário arredonda para cima. Índice 1-based na norma.
        $i = (int) ceil(0.05 * $n);

        return new EstimativaDeFck(
            $f[$i - 1],
            $n,
            $f,
            $amostragem,
            $condicao,
            sprintf('n ≥ 20: fck,est = f(i) com i = ⌈0,05 × %d⌉ = %d.', $n, $i),
            null,
            null,
            null,
        );
    }
}
