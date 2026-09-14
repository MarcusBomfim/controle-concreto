<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Lote;

use ControleConcreto\Dominio\ExcecaoDeDominio;

/**
 * O coeficiente ψ6 da NBR 12655, que dá o piso do fck estimado na amostragem
 * parcial: fck,est nunca fica abaixo de ψ6 × f1, o menor exemplar.
 *
 * ATENÇÃO — valores transcritos de memória da Tabela 7 da NBR 12655:2015.
 * É a tabela que decide se um lote passa ou não. Antes de qualquer uso real,
 * confira cada número contra o texto vigente da norma. A quirk de A cair de
 * 0,82 (n=2) para 0,75 (n=3) é como eu me lembro da tabela; se estiver
 * errada, é aqui que se corrige.
 *
 * Para n intermediário (9, 11, 13, 15) usa-se o valor do maior n tabulado
 * abaixo dele — o lado conservador, que rebaixa o piso. Se a norma vigente
 * pedir interpolação, este é o único lugar a mudar.
 */
final class Psi6
{
    /** @var array<int, array{a: float, bc: float}> n => coeficientes */
    private const TABELA = [
        2 => ['a' => 0.82, 'bc' => 0.75],
        3 => ['a' => 0.75, 'bc' => 0.75],
        4 => ['a' => 0.82, 'bc' => 0.75],
        5 => ['a' => 0.85, 'bc' => 0.80],
        6 => ['a' => 0.86, 'bc' => 0.81],
        7 => ['a' => 0.87, 'bc' => 0.83],
        8 => ['a' => 0.87, 'bc' => 0.84],
        10 => ['a' => 0.88, 'bc' => 0.86],
        12 => ['a' => 0.89, 'bc' => 0.87],
        14 => ['a' => 0.90, 'bc' => 0.88],
        16 => ['a' => 0.91, 'bc' => 0.89],
    ];

    private function __construct()
    {
    }

    public static function para(int $numeroDeExemplares, CondicaoDePreparo $condicao): float
    {
        if ($numeroDeExemplares < 2) {
            throw new ExcecaoDeDominio('ψ6 só é definido a partir de 2 exemplares.');
        }

        $n = min($numeroDeExemplares, 16);

        // Desce até o maior n tabulado que não passa do pedido.
        while (!isset(self::TABELA[$n])) {
            $n--;
        }

        return self::TABELA[$n][$condicao === CondicaoDePreparo::A ? 'a' : 'bc'];
    }
}
