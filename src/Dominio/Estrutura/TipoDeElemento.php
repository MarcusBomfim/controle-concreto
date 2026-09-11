<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Estrutura;

/**
 * O que está sendo concretado.
 *
 * O tipo importa para o controle: a NBR 12655 forma lotes de aceitação por
 * volume, e o volume máximo do lote muda conforme a peça — pilar e viga têm
 * lote menor que laje, porque uma falha neles é mais grave.
 */
enum TipoDeElemento: string
{
    case Fundacao = 'fundacao';
    case Pilar = 'pilar';
    case Viga = 'viga';
    case Laje = 'laje';
    case Parede = 'parede';
    case Reservatorio = 'reservatorio';
    case Piso = 'piso';
    case Outro = 'outro';

    public function rotulo(): string
    {
        return match ($this) {
            self::Fundacao => 'Fundação',
            self::Pilar => 'Pilar',
            self::Viga => 'Viga',
            self::Laje => 'Laje',
            self::Parede => 'Parede',
            self::Reservatorio => 'Reservatório',
            self::Piso => 'Piso',
            self::Outro => 'Outro',
        };
    }

    /**
     * Volume máximo do lote de aceitação em m³, conforme a NBR 12655.
     *
     * A norma distingue elementos "verticais" de "horizontais" pelo risco:
     * pilar e parede carregam tudo o que está acima. Valores transcritos da
     * tabela da norma; confira com o texto vigente antes de uso real.
     */
    public function volumeMaximoDoLoteEmM3(): int
    {
        return match ($this) {
            self::Pilar, self::Parede => 50,
            default => 100,
        };
    }

    /** Piso e "outro" podem ser concreto simples; o resto é estrutural. */
    public function exigeConcretoEstrutural(): bool
    {
        return match ($this) {
            self::Piso, self::Outro => false,
            default => true,
        };
    }
}
