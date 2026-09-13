<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Ensaio;

use ControleConcreto\Dominio\ExcecaoDeDominio;

/**
 * Idades em que um corpo de prova é rompido, e a folga que a prensa tem.
 *
 * O concreto ganha resistência com o tempo, e o fck é definido aos 28 dias.
 * Romper aos 7 dias serve para antecipar problema; romper aos 28 é o que
 * vale para aceitar o lote.
 *
 * A NBR 5739 dá uma tolerância de horário para cada idade: um corpo de prova
 * de 28 dias pode ser rompido até 20 horas antes ou depois do momento exato.
 * Fora dessa janela o ensaio não representa a idade, e o resultado não vale.
 */
enum IdadeDeEnsaio: int
{
    case UmDia = 1;
    case TresDias = 3;
    case SeteDias = 7;
    case VinteEOitoDias = 28;
    case SessentaETresDias = 63;
    case NoventaEUmDias = 91;

    public function dias(): int
    {
        return $this->value;
    }

    public function rotulo(): string
    {
        return $this->value === 1 ? '24 horas' : "{$this->value} dias";
    }

    /**
     * Tolerância de rompimento em horas, para mais ou para menos.
     * Valores transcritos da NBR 5739; confira com o texto vigente.
     */
    public function toleranciaEmHoras(): float
    {
        return match ($this) {
            self::UmDia => 0.5,
            self::TresDias => 2.0,
            self::SeteDias => 6.0,
            self::VinteEOitoDias => 20.0,
            self::SessentaETresDias => 36.0,
            self::NoventaEUmDias => 48.0,
        };
    }

    /** Só o resultado de 28 dias entra na aceitação do lote. O resto é informação. */
    public function ehDeAceitacao(): bool
    {
        return $this === self::VinteEOitoDias;
    }

    public static function deDias(int $dias): self
    {
        return self::tryFrom($dias) ?? throw new ExcecaoDeDominio(
            "Não há ensaio previsto aos {$dias} dias. Use 1, 3, 7, 28, 63 ou 91."
        );
    }
}
