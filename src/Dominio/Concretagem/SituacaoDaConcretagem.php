<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Concretagem;

enum SituacaoDaConcretagem: string
{
    case EmAndamento = 'em_andamento';
    case Concluida = 'concluida';
    case Cancelada = 'cancelada';

    public function rotulo(): string
    {
        return match ($this) {
            self::EmAndamento => 'Em andamento',
            self::Concluida => 'Concluída',
            self::Cancelada => 'Cancelada',
        };
    }

    /** Só concretagem em andamento recebe caminhão. */
    public function aceitaCarga(): bool
    {
        return $this === self::EmAndamento;
    }
}
