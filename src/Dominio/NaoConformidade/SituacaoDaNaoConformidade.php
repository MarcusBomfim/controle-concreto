<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\NaoConformidade;

enum SituacaoDaNaoConformidade: string
{
    case Aberta = 'aberta';
    case Encerrada = 'encerrada';

    public function rotulo(): string
    {
        return match ($this) {
            self::Aberta => 'Aberta',
            self::Encerrada => 'Encerrada',
        };
    }
}
