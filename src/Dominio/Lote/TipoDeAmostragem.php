<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Lote;

/**
 * Se todas as betonadas do lote foram ensaiadas ou só parte delas.
 *
 * Muda a conta do fck estimado. Na amostragem total, cada caminhão virou
 * exemplar e a norma confia no menor valor diretamente. Na parcial, só
 * alguns foram ensaiados e a norma extrapola — com uma fórmula mais
 * conservadora e um piso.
 */
enum TipoDeAmostragem: string
{
    case Parcial = 'parcial';
    case Total = 'total';

    public function rotulo(): string
    {
        return match ($this) {
            self::Parcial => 'Amostragem parcial',
            self::Total => 'Amostragem total',
        };
    }
}
