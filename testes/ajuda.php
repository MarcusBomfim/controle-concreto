<?php

declare(strict_types=1);

use ControleConcreto\Dominio\Concreto\Abatimento;
use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\Estrutura\ElementoEstrutural;
use ControleConcreto\Dominio\Estrutura\TipoDeElemento;

/*
 * Fábricas compartilhadas entre os arquivos de teste. Ficam aqui, carregadas
 * antes de tudo, porque o executor inclui os testes em ordem alfabética — e
 * uma função definida em EstruturaTest.php não existe ainda quando
 * ConcretagemTest.php roda.
 */

/** Laje C30, abatimento 100 ± 20 mm, 42 m³. */
function lajeDeTeste(float $volume = 42.0): ElementoEstrutural
{
    return new ElementoEstrutural(
        'l3-p4',
        TipoDeElemento::Laje,
        'Laje L3',
        '4º pavimento',
        ClasseDeResistencia::C30,
        new Abatimento(100),
        $volume,
    );
}

/** Pilares C35, abatimento 120 ± 20 mm — lote de 50 m³. */
function pilaresDeTeste(float $volume = 30.0): ElementoEstrutural
{
    return new ElementoEstrutural(
        'P-T',
        TipoDeElemento::Pilar,
        'Pilares do térreo',
        'Térreo',
        ClasseDeResistencia::C35,
        new Abatimento(120),
        $volume,
    );
}
