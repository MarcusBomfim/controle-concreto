<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Ensaio;

enum SituacaoDoCorpoDeProva: string
{
    case Curando = 'curando';
    case Rompido = 'rompido';
    case Descartado = 'descartado';

    public function rotulo(): string
    {
        return match ($this) {
            self::Curando => 'Curando',
            self::Rompido => 'Rompido',
            self::Descartado => 'Descartado',
        };
    }

    /** Só corpo de prova em cura pode ir para a prensa. */
    public function aguardaRompimento(): bool
    {
        return $this === self::Curando;
    }
}
