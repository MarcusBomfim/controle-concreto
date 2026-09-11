<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Concreto;

use ControleConcreto\Dominio\ExcecaoDeDominio;

/**
 * Abatimento do tronco de cone — o "slump" — especificado para a peça.
 *
 * É a medida da consistência do concreto fresco: quanto o cone abate ao ser
 * desmoldado, em milímetros. Concreto muito seco não preenche a forma; muito
 * fluido segrega. O projeto especifica um valor, e cada caminhão que chega
 * é medido contra ele.
 *
 * A tolerância vem da NBR 7212 e cresce com o abatimento: um concreto de
 * 100 mm pode variar 20; um de 220 mm, 30.
 */
final class Abatimento
{
    private const MINIMO = 10;
    private const MAXIMO = 250;

    public readonly int $especificadoEmMm;

    public function __construct(int $especificadoEmMm)
    {
        if ($especificadoEmMm < self::MINIMO || $especificadoEmMm > self::MAXIMO) {
            throw new ExcecaoDeDominio(sprintf(
                'Abatimento especificado de %d mm fora da faixa usual (%d a %d mm).',
                $especificadoEmMm,
                self::MINIMO,
                self::MAXIMO,
            ));
        }

        $this->especificadoEmMm = $especificadoEmMm;
    }

    /**
     * Tolerância em mm conforme a NBR 7212. Os limites de faixa foram
     * transcritos da norma; confira com o texto vigente antes de uso real.
     */
    public function toleranciaEmMm(): int
    {
        return match (true) {
            $this->especificadoEmMm <= 90 => 10,
            $this->especificadoEmMm <= 150 => 20,
            default => 30,
        };
    }

    public function minimoAceito(): int
    {
        return $this->especificadoEmMm - $this->toleranciaEmMm();
    }

    public function maximoAceito(): int
    {
        return $this->especificadoEmMm + $this->toleranciaEmMm();
    }

    public function aceita(int $medidoEmMm): bool
    {
        return $medidoEmMm >= $this->minimoAceito() && $medidoEmMm <= $this->maximoAceito();
    }

    public function faixa(): string
    {
        return sprintf('%d ± %d mm', $this->especificadoEmMm, $this->toleranciaEmMm());
    }

    public function ehIgualA(self $outro): bool
    {
        return $this->especificadoEmMm === $outro->especificadoEmMm;
    }
}
