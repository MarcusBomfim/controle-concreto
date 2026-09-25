<?php

declare(strict_types=1);

namespace App\Dominio\NaoConformidade;

/**
 * O que a providência concluiu sobre a peça.
 *
 * Favorável sustenta o encerramento — a revisão fechou, o testemunho atingiu
 * o fck, o reforço foi executado. Desfavorável manda para o passo seguinte.
 * Informativo é o ensaio que só localiza, sem dizer sim nem não.
 */
enum ResultadoDaProvidencia: string
{
    case Favoravel = 'favoravel';
    case Desfavoravel = 'desfavoravel';
    case Informativo = 'informativo';

    public function rotulo(): string
    {
        return match ($this) {
            self::Favoravel => 'Favorável',
            self::Desfavoravel => 'Desfavorável',
            self::Informativo => 'Informativo',
        };
    }
}
