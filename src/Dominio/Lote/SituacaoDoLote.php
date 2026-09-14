<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Lote;

enum SituacaoDoLote: string
{
    case Aberto = 'aberto';
    case Aceito = 'aceito';
    case NaoConforme = 'nao_conforme';

    public function rotulo(): string
    {
        return match ($this) {
            self::Aberto => 'Aberto',
            self::Aceito => 'Aceito',
            self::NaoConforme => 'Não conforme',
        };
    }

    public function foiJulgado(): bool
    {
        return $this !== self::Aberto;
    }
}
