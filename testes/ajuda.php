<?php

declare(strict_types=1);

use ControleConcreto\Dominio\Concretagem\Carga;
use ControleConcreto\Dominio\Concretagem\Concretagem;
use ControleConcreto\Dominio\Concreto\Abatimento;
use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;
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

/** Concretagem da laje de teste em 10/03/2026, com "hoje" fixado no mesmo dia. */
function concretagemDeTeste(): Concretagem
{
    return new Concretagem(
        'obr-2026-007',
        lajeDeTeste(),
        new DateTimeImmutable('2026-03-10'),
        'Concreteira Litoral',
        'Marcus Bomfim',
        new DateTimeImmutable('2026-03-10'),
    );
}

/** Um horário no dia da concretagem de teste. */
function hora(string $horario): DateTimeImmutable
{
    return new DateTimeImmutable("2026-03-10 {$horario}");
}

/** Carga típica: saiu 8h, chegou 8h50, abatimento dentro da faixa de 100 ± 20. */
function chegaCarga(
    Concretagem $concretagem,
    int $abatimento = 100,
    string $saida = '08:00',
    string $chegada = '08:50',
    float $volume = 8.0,
    string $notaFiscal = 'NF-1001',
): Carga {
    return $concretagem->receberCarga($notaFiscal, 'ABC-1D23', $volume, hora($saida), hora($chegada), $abatimento);
}

/** Molda o par padrão de exemplares — 7 e 28 dias — da carga, às 9h. */
function moldaPadrao(Concretagem $concretagem, int $cargaNumero = 1): array
{
    return $concretagem->moldar(
        $cargaNumero,
        hora('09:00'),
        [IdadeDeEnsaio::SeteDias, IdadeDeEnsaio::VinteEOitoDias],
    );
}
