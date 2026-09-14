<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Estrutura;

/**
 * Como a peça trabalha: comprimida (pilar, parede) ou fletida (laje, viga).
 *
 * A NBR 12655 forma lotes separados para cada grupo e dá lote menor às peças
 * comprimidas — uma falha em pilar derruba o que está em cima. Um lote nunca
 * mistura os dois grupos.
 */
enum GrupoDeSolicitacao: string
{
    case Vertical = 'vertical';
    case Horizontal = 'horizontal';

    public function rotulo(): string
    {
        return match ($this) {
            self::Vertical => 'Compressão (pilares e paredes)',
            self::Horizontal => 'Flexão (lajes, vigas e fundações)',
        };
    }

    /**
     * Volume máximo do lote, em m³. Transcrito da Tabela 6 da NBR 12655;
     * confira com o texto vigente antes de uso real.
     */
    public function volumeMaximoDoLoteEmM3(): int
    {
        return match ($this) {
            self::Vertical => 50,
            self::Horizontal => 100,
        };
    }
}
