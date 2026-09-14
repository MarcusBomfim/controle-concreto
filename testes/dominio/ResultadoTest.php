<?php

declare(strict_types=1);

use ControleConcreto\Dominio\Ensaio\CorpoDeProva;
use ControleConcreto\Dominio\Ensaio\DiametroDoCorpoDeProva;
use ControleConcreto\Dominio\Ensaio\Exemplar;
use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;
use ControleConcreto\Dominio\Ensaio\ResultadoDeEnsaio;
use ControleConcreto\Dominio\Ensaio\SituacaoDoCorpoDeProva;
use ControleConcreto\Dominio\ExcecaoDeDominio;

/** Corpo de prova de 28 dias moldado em 10/03 às 9h: janela de 06/04 13h a 08/04 05h. */
function cpDe28(): CorpoDeProva
{
    return new CorpoDeProva('C1-28d-A', momento('2026-03-10 09:00'), IdadeDeEnsaio::VinteEOitoDias);
}

grupo('Diâmetro do corpo de prova');

teste('calcula a área da seção', function (): void {
    igualAproximado(7853.98, round(DiametroDoCorpoDeProva::DezCentimetros->areaEmMm2(), 2));
    igualAproximado(17671.46, round(DiametroDoCorpoDeProva::QuinzeCentimetros->areaEmMm2(), 2));
});

teste('recusa diâmetro fora da norma', function (): void {
    lanca(ExcecaoDeDominio::class, static fn () => DiametroDoCorpoDeProva::deMm(120), 'prevê 100 ou 150');
});

grupo('Resultado de ensaio: a conta da prensa');

teste('converte força em resistência: kN / mm² → MPa', function (): void {
    // 250 kN num cilindro de 10 cm: 250 000 N / 7 853,98 mm² = 31,8 MPa.
    $resultado = new ResultadoDeEnsaio(250.0, DiametroDoCorpoDeProva::DezCentimetros, momento('2026-04-07 09:00'));

    igualAproximado(31.8, $resultado->resistenciaEmMPa());
});

teste('o cilindro de 15 cm precisa de mais força para a mesma resistência', function (): void {
    // Área 2,25× maior: 562,5 kN dão os mesmos 31,8 MPa.
    $resultado = new ResultadoDeEnsaio(562.5, DiametroDoCorpoDeProva::QuinzeCentimetros, momento('2026-04-07 09:00'));

    igualAproximado(31.8, $resultado->resistenciaEmMPa());
});

teste('arredonda a uma casa decimal', function (): void {
    $resultado = new ResultadoDeEnsaio(237.3, DiametroDoCorpoDeProva::DezCentimetros, momento('2026-04-07 09:00'));

    igualAproximado(30.2, $resultado->resistenciaEmMPa());
});

teste('recusa carga zerada ou implausível', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => new ResultadoDeEnsaio(0.0, DiametroDoCorpoDeProva::DezCentimetros, momento('2026-04-07 09:00')),
        'maior que zero',
    );
    // 250 000 é 250 kN digitados em N: erro de unidade clássico.
    lanca(
        ExcecaoDeDominio::class,
        static fn () => new ResultadoDeEnsaio(250000.0, DiametroDoCorpoDeProva::DezCentimetros, momento('2026-04-07 09:00')),
        'Confira a unidade',
    );
});

grupo('Corpo de prova: romper');

teste('rompe dentro da janela e guarda o resultado', function (): void {
    $cp = cpDe28();

    $cp->romper(
        new ResultadoDeEnsaio(260.0, DiametroDoCorpoDeProva::DezCentimetros, momento('2026-04-07 10:30')),
        momento('2026-04-07 11:00'),
    );

    igual(SituacaoDoCorpoDeProva::Rompido, $cp->situacao());
    verdadeiro($cp->foiRompido(), 'rompido');
    igualAproximado(33.1, $cp->resistenciaEmMPa() ?? 0.0);
});

teste('aceita rompimento nos limites da janela', function (): void {
    $cedo = cpDe28();
    $cedo->romper(
        new ResultadoDeEnsaio(260.0, DiametroDoCorpoDeProva::DezCentimetros, momento('2026-04-06 13:00')),
        momento('2026-04-09 00:00'),
    );
    verdadeiro($cedo->foiRompido(), 'limite inferior');

    $tarde = cpDe28();
    $tarde->romper(
        new ResultadoDeEnsaio(260.0, DiametroDoCorpoDeProva::DezCentimetros, momento('2026-04-08 05:00')),
        momento('2026-04-09 00:00'),
    );
    verdadeiro($tarde->foiRompido(), 'limite superior');
});

teste('recusa rompimento antes da janela', function (): void {
    $cp = cpDe28();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $cp->romper(
            new ResultadoDeEnsaio(260.0, DiametroDoCorpoDeProva::DezCentimetros, momento('2026-04-05 09:00')),
            momento('2026-04-09 00:00'),
        ),
        'fora da janela',
    );
    igual(SituacaoDoCorpoDeProva::Curando, $cp->situacao(), 'continua curando');
    igual(null, $cp->resistenciaEmMPa(), 'sem resultado');
});

teste('recusa rompimento depois da janela', function (): void {
    // É a regra central: o número existe, mas não representa 28 dias.
    $cp = cpDe28();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $cp->romper(
            new ResultadoDeEnsaio(260.0, DiametroDoCorpoDeProva::DezCentimetros, momento('2026-04-10 09:00')),
            momento('2026-04-11 00:00'),
        ),
        'descarte-o e registre o motivo',
    );
});

teste('recusa rompimento com data no futuro', function (): void {
    $cp = cpDe28();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $cp->romper(
            new ResultadoDeEnsaio(260.0, DiametroDoCorpoDeProva::DezCentimetros, momento('2026-04-07 10:00')),
            momento('2026-04-07 09:00'),
        ),
        'ainda não chegou',
    );
});

teste('não rompe duas vezes', function (): void {
    $cp = cpDe28();
    $cp->romper(
        new ResultadoDeEnsaio(260.0, DiametroDoCorpoDeProva::DezCentimetros, momento('2026-04-07 10:00')),
        momento('2026-04-07 11:00'),
    );

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $cp->romper(
            new ResultadoDeEnsaio(280.0, DiametroDoCorpoDeProva::DezCentimetros, momento('2026-04-07 10:30')),
            momento('2026-04-07 11:00'),
        ),
        'já está rompido',
    );
});

grupo('Corpo de prova: descartar');

teste('descarta com motivo e fica sem resultado', function (): void {
    $cp = cpDe28();

    $cp->descartar('Quebrou na desforma');

    igual(SituacaoDoCorpoDeProva::Descartado, $cp->situacao());
    igual('Quebrou na desforma', $cp->motivoDoDescarte());
    igual(null, $cp->resistenciaEmMPa());
});

teste('exige motivo', function (): void {
    $cp = cpDe28();

    lanca(ExcecaoDeDominio::class, static fn () => $cp->descartar('   '), 'Motivo do descarte é obrigatório');
});

teste('não descarta o que já foi rompido', function (): void {
    $cp = cpDe28();
    $cp->romper(
        new ResultadoDeEnsaio(260.0, DiametroDoCorpoDeProva::DezCentimetros, momento('2026-04-07 10:00')),
        momento('2026-04-07 11:00'),
    );

    lanca(ExcecaoDeDominio::class, static fn () => $cp->descartar('tarde demais'), 'não pode ser descartado');
});

grupo('Exemplar: a resistência é a maior');

function exemplarRompido(?float $primeiraCargaKN, ?float $segundaCargaKN): Exemplar
{
    $exemplar = Exemplar::moldar(1, IdadeDeEnsaio::VinteEOitoDias, momento('2026-03-10 09:00'));
    $agora = momento('2026-04-07 12:00');

    if ($primeiraCargaKN !== null) {
        $exemplar->primeiro->romper(
            new ResultadoDeEnsaio($primeiraCargaKN, DiametroDoCorpoDeProva::DezCentimetros, momento('2026-04-07 10:00')),
            $agora,
        );
    }

    if ($segundaCargaKN !== null) {
        $exemplar->segundo->romper(
            new ResultadoDeEnsaio($segundaCargaKN, DiametroDoCorpoDeProva::DezCentimetros, momento('2026-04-07 10:05')),
            $agora,
        );
    }

    return $exemplar;
}

teste('vale o maior dos dois corpos de prova', function (): void {
    // 260 kN → 33,1 MPa; 240 kN → 30,6 MPa. O menor é ruído.
    $exemplar = exemplarRompido(260.0, 240.0);

    igualAproximado(33.1, $exemplar->resistenciaEmMPa() ?? 0.0);
    verdadeiro($exemplar->estaCompleto(), 'os dois rompidos');
    falso($exemplar->estaIncompleto(), 'não está incompleto');
});

teste('sem nenhum rompido, não tem resistência', function (): void {
    $exemplar = exemplarRompido(null, null);

    igual(null, $exemplar->resistenciaEmMPa());
    falso($exemplar->temResultado(), 'sem resultado');
    verdadeiro($exemplar->aguardaRompimento(), 'aguardando');
});

teste('com um rompido e o outro descartado, vale o que rompeu e fica marcado', function (): void {
    $exemplar = exemplarRompido(260.0, null);
    $exemplar->segundo->descartar('Perdido na câmara de cura');

    igualAproximado(33.1, $exemplar->resistenciaEmMPa() ?? 0.0);
    verdadeiro($exemplar->temResultado(), 'tem resultado');
    falso($exemplar->estaCompleto(), 'não está completo');
    verdadeiro($exemplar->estaIncompleto(), 'incompleto: metade da redundância');
});

teste('com um rompido e o outro ainda curando, tem resultado parcial', function (): void {
    $exemplar = exemplarRompido(260.0, null);

    igualAproximado(33.1, $exemplar->resistenciaEmMPa() ?? 0.0);
    verdadeiro($exemplar->aguardaRompimento(), 'o segundo ainda espera');
    falso($exemplar->estaIncompleto(), 'não é incompleto: ainda pode completar');
});
