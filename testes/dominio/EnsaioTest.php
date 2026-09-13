<?php

declare(strict_types=1);

use ControleConcreto\Dominio\Ensaio\CorpoDeProva;
use ControleConcreto\Dominio\Ensaio\Exemplar;
use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;
use ControleConcreto\Dominio\Ensaio\SituacaoDoCorpoDeProva;
use ControleConcreto\Dominio\ExcecaoDeDominio;

grupo('Idade de ensaio');

teste('a tolerância de rompimento cresce com a idade', function (): void {
    igualAproximado(0.5, IdadeDeEnsaio::UmDia->toleranciaEmHoras());
    igualAproximado(6.0, IdadeDeEnsaio::SeteDias->toleranciaEmHoras());
    igualAproximado(20.0, IdadeDeEnsaio::VinteEOitoDias->toleranciaEmHoras());
    igualAproximado(48.0, IdadeDeEnsaio::NoventaEUmDias->toleranciaEmHoras());
});

teste('só 28 dias é idade de aceitação', function (): void {
    verdadeiro(IdadeDeEnsaio::VinteEOitoDias->ehDeAceitacao(), '28 dias');
    falso(IdadeDeEnsaio::SeteDias->ehDeAceitacao(), '7 dias é informação');
    falso(IdadeDeEnsaio::SessentaETresDias->ehDeAceitacao(), '63 dias é complementar');
});

teste('recusa idade fora das previstas', function (): void {
    lanca(ExcecaoDeDominio::class, static fn () => IdadeDeEnsaio::deDias(14), 'Não há ensaio previsto');
});

grupo('Corpo de prova: a janela de rompimento');

teste('o rompimento previsto é a moldagem mais a idade', function (): void {
    $cp = new CorpoDeProva('C1-28d-A', momento('2026-03-10 09:00'), IdadeDeEnsaio::VinteEOitoDias);

    igual('2026-04-07 09:00', $cp->rompimentoPrevisto()->format('Y-m-d H:i'));
});

teste('a janela de 28 dias é de 20 horas para cada lado', function (): void {
    $cp = new CorpoDeProva('C1-28d-A', momento('2026-03-10 09:00'), IdadeDeEnsaio::VinteEOitoDias);

    igual('2026-04-06 13:00', $cp->inicioDaJanela()->format('Y-m-d H:i'));
    igual('2026-04-08 05:00', $cp->fimDaJanela()->format('Y-m-d H:i'));
});

teste('a janela de 24 horas lida com meia hora', function (): void {
    // DateInterval não aceita 0,5 h. Se o deslocamento estiver errado, é aqui que aparece.
    $cp = new CorpoDeProva('C1-1d-A', momento('2026-03-10 09:00'), IdadeDeEnsaio::UmDia);

    igual('2026-03-11 08:30', $cp->inicioDaJanela()->format('Y-m-d H:i'));
    igual('2026-03-11 09:30', $cp->fimDaJanela()->format('Y-m-d H:i'));
});

teste('reconhece o momento dentro e fora da janela', function (): void {
    $cp = new CorpoDeProva('C1-28d-A', momento('2026-03-10 09:00'), IdadeDeEnsaio::VinteEOitoDias);

    verdadeiro($cp->dentroDaJanela(momento('2026-04-07 09:00')), 'no instante exato');
    verdadeiro($cp->dentroDaJanela(momento('2026-04-06 13:00')), 'limite inferior');
    verdadeiro($cp->dentroDaJanela(momento('2026-04-08 05:00')), 'limite superior');
    falso($cp->dentroDaJanela(momento('2026-04-06 12:59')), 'um minuto antes');
    falso($cp->dentroDaJanela(momento('2026-04-08 05:01')), 'um minuto depois');
});

teste('sabe quando ainda é cedo', function (): void {
    $cp = new CorpoDeProva('C1-7d-A', momento('2026-03-10 09:00'), IdadeDeEnsaio::SeteDias);

    verdadeiro($cp->aindaNaoPodeRomper(momento('2026-03-15 09:00')), 'cinco dias');
    falso($cp->aindaNaoPodeRomper(momento('2026-03-17 04:00')), 'já dentro da janela de ±6h');
});

teste('vence quando a janela passa sem rompimento', function (): void {
    $cp = new CorpoDeProva('C1-7d-A', momento('2026-03-10 09:00'), IdadeDeEnsaio::SeteDias);

    falso($cp->estaVencido(momento('2026-03-17 15:00')), 'ainda na janela');
    verdadeiro($cp->estaVencido(momento('2026-03-17 15:01')), 'passou');
    igual(SituacaoDoCorpoDeProva::Curando, $cp->situacao(), 'continua curando, mas perdeu a idade');
});

teste('conta as horas até a janela abrir', function (): void {
    $cp = new CorpoDeProva('C1-28d-A', momento('2026-03-10 09:00'), IdadeDeEnsaio::VinteEOitoDias);

    igualAproximado(24.0, $cp->horasAteAJanela(momento('2026-04-05 13:00')));
    igualAproximado(-2.0, $cp->horasAteAJanela(momento('2026-04-06 15:00')), 'negativo quando já abriu');
});

grupo('Exemplar');

teste('molda dois corpos de prova com identificação derivada', function (): void {
    $exemplar = Exemplar::moldar(2, IdadeDeEnsaio::VinteEOitoDias, momento('2026-03-10 09:00'));

    igual('C2-28d-A', $exemplar->primeiro->identificacao);
    igual('C2-28d-B', $exemplar->segundo->identificacao);
    igual('Carga 2 · 28 dias', $exemplar->identificacao());
    igual(2, count($exemplar->corposDeProva()));
});

teste('os dois corpos de prova compartilham a janela', function (): void {
    $exemplar = Exemplar::moldar(1, IdadeDeEnsaio::SeteDias, momento('2026-03-10 09:00'));

    igual(
        $exemplar->primeiro->fimDaJanela()->format('c'),
        $exemplar->segundo->fimDaJanela()->format('c'),
    );
    igual('2026-03-17 09:00', $exemplar->rompimentoPrevisto()->format('Y-m-d H:i'));
});

teste('exemplar de 28 dias é de aceitação', function (): void {
    verdadeiro(Exemplar::moldar(1, IdadeDeEnsaio::VinteEOitoDias, momento('2026-03-10 09:00'))->ehDeAceitacao(), '28');
    falso(Exemplar::moldar(1, IdadeDeEnsaio::SeteDias, momento('2026-03-10 09:00'))->ehDeAceitacao(), '7');
});

teste('acusa corpo de prova vencido', function (): void {
    $exemplar = Exemplar::moldar(1, IdadeDeEnsaio::SeteDias, momento('2026-03-10 09:00'));

    falso($exemplar->temCorpoDeProvaVencido(momento('2026-03-17 12:00')), 'na janela');
    verdadeiro($exemplar->temCorpoDeProvaVencido(momento('2026-03-18 12:00')), 'um dia depois');
});

grupo('Moldagem a partir da concretagem');

teste('molda um exemplar por idade a partir de uma carga aceita', function (): void {
    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100);

    $exemplares = moldaPadrao($concretagem);

    igual(2, count($exemplares), 'um de 7 e um de 28');
    igual(4, count($concretagem->corposDeProva()), 'dois corpos de prova por exemplar');
    igual(1, count($concretagem->exemplaresDeAceitacao()), 'só o de 28 conta');
});

teste('carga devolvida não gera corpo de prova', function (): void {
    // A regra que amarra a Etapa 2 a esta: o concreto devolvido não está na peça.
    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 140);

    lanca(
        ExcecaoDeDominio::class,
        static fn () => moldaPadrao($concretagem),
        'não gera corpo de prova',
    );
    igual([], $concretagem->exemplares());
});

teste('recusa carga inexistente', function (): void {
    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100);

    lanca(ExcecaoDeDominio::class, static fn () => moldaPadrao($concretagem, 7), 'Não existe carga');
});

teste('recusa moldagem antes da chegada da carga', function (): void {
    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100, '08:00', '08:50');

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $concretagem->moldar(1, hora('08:30'), [IdadeDeEnsaio::VinteEOitoDias]),
        'anterior à chegada',
    );
});

teste('recusa moldagem em outro dia', function (): void {
    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100);

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $concretagem->moldar(1, momento('2026-03-11 09:00'), [IdadeDeEnsaio::VinteEOitoDias]),
        'no dia da concretagem',
    );
});

teste('recusa segundo exemplar da mesma idade para a mesma carga', function (): void {
    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100);
    moldaPadrao($concretagem);

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $concretagem->moldar(1, hora('09:30'), [IdadeDeEnsaio::VinteEOitoDias]),
        'já tem exemplar de 28 dias',
    );
});

teste('recusa lista de idades vazia', function (): void {
    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100);

    lanca(ExcecaoDeDominio::class, static fn () => $concretagem->moldar(1, hora('09:00'), []), 'ao menos uma idade');
});

teste('encontra o exemplar por carga e idade', function (): void {
    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100, notaFiscal: 'NF-1');
    chegaCarga($concretagem, 100, notaFiscal: 'NF-2');
    moldaPadrao($concretagem, 1);
    moldaPadrao($concretagem, 2);

    igual('Carga 2 · 28 dias', $concretagem->exemplar(2, IdadeDeEnsaio::VinteEOitoDias)?->identificacao());
    igual(null, $concretagem->exemplar(3, IdadeDeEnsaio::VinteEOitoDias));
    igual(2, count($concretagem->exemplaresDaCarga(1)));
    igual(2, count($concretagem->exemplaresDeAceitacao()));
});

teste('concretagem concluída não molda mais', function (): void {
    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100, notaFiscal: 'NF-1');
    chegaCarga($concretagem, 100, notaFiscal: 'NF-2');
    moldaPadrao($concretagem, 1);
    $concretagem->concluir();

    lanca(ExcecaoDeDominio::class, static fn () => moldaPadrao($concretagem, 2), 'não é possível moldar');
});
