<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Ensaio;

use ControleConcreto\Dominio\ExcecaoDeDominio;

/**
 * Os dois tamanhos de cilindro da NBR 5738: 10 × 20 cm e 15 × 30 cm.
 *
 * O diâmetro define a área, e a área é o que converte a força da prensa em
 * resistência. Errar o diâmetro erra a resistência em 2,25 vezes — por isso
 * é enum, não número livre.
 */
enum DiametroDoCorpoDeProva: int
{
    case DezCentimetros = 100;
    case QuinzeCentimetros = 150;

    public function emMm(): int
    {
        return $this->value;
    }

    public function rotulo(): string
    {
        return match ($this) {
            self::DezCentimetros => '10 × 20 cm',
            self::QuinzeCentimetros => '15 × 30 cm',
        };
    }

    /** Área da seção circular em mm². */
    public function areaEmMm2(): float
    {
        return M_PI * ($this->value / 2) ** 2;
    }

    public static function deMm(int $diametro): self
    {
        return self::tryFrom($diametro) ?? throw new ExcecaoDeDominio(
            "Não há corpo de prova de {$diametro} mm. A NBR 5738 prevê 100 ou 150 mm."
        );
    }
}
