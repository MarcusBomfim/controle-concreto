<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Lote;

/**
 * Como o concreto foi dosado — a "condição de preparo" da NBR 12655.
 *
 * Quanto mais preciso o preparo, menos variação entre uma betonada e outra,
 * e mais confiança a norma deposita nos poucos exemplares que rompeu. É por
 * isso que o coeficiente ψ6 muda com a condição: concreto de usina (A) tem
 * um piso mais alto para o fck estimado do que concreto virado na obra (C).
 */
enum CondicaoDePreparo: string
{
    case A = 'a';
    case B = 'b';
    case C = 'c';

    public function rotulo(): string
    {
        return 'Condição ' . strtoupper($this->value);
    }

    public function descricao(): string
    {
        return match ($this) {
            self::A => 'Cimento e agregados medidos em massa, água corrigida pela umidade — o padrão de usina.',
            self::B => 'Cimento em massa, agregados em volume com correção de umidade.',
            self::C => 'Cimento em massa, agregados em volume sem correção — o concreto virado na obra.',
        };
    }

    /** Na tabela de ψ6 da norma, B e C compartilham a mesma linha. */
    public function linhaDaTabela(): string
    {
        return $this === self::A ? 'A' : 'B ou C';
    }
}
