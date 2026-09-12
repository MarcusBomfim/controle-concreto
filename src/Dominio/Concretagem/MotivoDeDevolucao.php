<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Concretagem;

/**
 * Por que um caminhão foi mandado de volta.
 *
 * Devolução é evento documentado, não apagado: é o registro que sustenta a
 * discussão com a usina sobre quem paga o concreto recusado.
 */
enum MotivoDeDevolucao: string
{
    case AbatimentoForaDaFaixa = 'abatimento_fora_da_faixa';
    case TempoDeTransporteExcedido = 'tempo_de_transporte_excedido';

    public function rotulo(): string
    {
        return match ($this) {
            self::AbatimentoForaDaFaixa => 'Abatimento fora da faixa',
            self::TempoDeTransporteExcedido => 'Tempo de transporte excedido',
        };
    }
}
