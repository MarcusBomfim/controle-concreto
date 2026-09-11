<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Concreto;

use ControleConcreto\Dominio\ExcecaoDeDominio;

/**
 * Classes de resistência do concreto, conforme a NBR 8953.
 *
 * O valor de cada caso é o fck em MPa: a resistência característica à
 * compressão aos 28 dias, que o projeto estrutural especifica e que o
 * controle tecnológico existe para comprovar.
 *
 * A lista é a da norma, com os saltos que ela tem: acima de C60 as classes
 * pulam de 10 em 10. Um enum garante que ninguém cadastre "C27".
 */
enum ClasseDeResistencia: int
{
    // Grupo I
    case C15 = 15;
    case C20 = 20;
    case C25 = 25;
    case C30 = 30;
    case C35 = 35;
    case C40 = 40;
    case C45 = 45;
    case C50 = 50;

    // Grupo II
    case C55 = 55;
    case C60 = 60;
    case C70 = 70;
    case C80 = 80;
    case C90 = 90;
    case C100 = 100;

    public function fck(): float
    {
        return (float) $this->value;
    }

    public function rotulo(): string
    {
        return $this->name;
    }

    /**
     * A NBR 6118 exige no mínimo C20 para concreto armado. C15 só entra em
     * fundação e obra provisória, e por isso continua na lista — mas o
     * elemento estrutural recusa.
     */
    public function ehEstrutural(): bool
    {
        return $this->value >= 20;
    }

    /** Grupo II pede controle mais rigoroso e nem toda usina produz. */
    public function ehDeAltoDesempenho(): bool
    {
        return $this->value >= 55;
    }

    public static function deFck(int $fck): self
    {
        return self::tryFrom($fck) ?? throw new ExcecaoDeDominio(
            "Não existe classe de resistência C{$fck}. Use uma classe da NBR 8953, como C25 ou C30."
        );
    }
}
