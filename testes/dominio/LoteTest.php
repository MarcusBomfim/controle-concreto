<?php

declare(strict_types=1);

use ControleConcreto\Dominio\Concretagem\Concretagem;
use ControleConcreto\Dominio\Concreto\Abatimento;
use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\Ensaio\DiametroDoCorpoDeProva;
use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;
use ControleConcreto\Dominio\Ensaio\ResultadoDeEnsaio;
use ControleConcreto\Dominio\Estrutura\ElementoEstrutural;
use ControleConcreto\Dominio\Estrutura\GrupoDeSolicitacao;
use ControleConcreto\Dominio\Estrutura\TipoDeElemento;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Lote\CondicaoDePreparo;
use ControleConcreto\Dominio\Lote\Lote;
use ControleConcreto\Dominio\Lote\SituacaoDoLote;
use ControleConcreto\Dominio\Lote\TipoDeAmostragem;

grupo('Lote: composição');

teste('aceita concretagem concluída do mesmo fck e grupo', function (): void {
    $lote = loteC30();

    $lote->adicionarConcretagem(concretagemComResultados(1, [32.0, 31.0]));

    igual(1, count($lote->concretagens()));
    igualAproximado(16.0, $lote->volumeEmM3());
    igual(2, count($lote->exemplaresDeAceitacao()));
});

teste('recusa concretagem em andamento', function (): void {
    $lote = loteC30();
    $emAndamento = concretagemDeTeste();
    $emAndamento->definirNumero(9);
    chegaCarga($emAndamento, 100);

    lanca(ExcecaoDeDominio::class, static fn () => $lote->adicionarConcretagem($emAndamento), 'Só concretagem concluída');
});

teste('recusa fck diferente', function (): void {
    $lote = loteC30();
    $pilaresC35 = concretagemComResultados(2, [40.0], pilaresDeTeste());

    lanca(ExcecaoDeDominio::class, static fn () => $lote->adicionarConcretagem($pilaresC35), 'Um lote tem um fck só');
});

teste('recusa grupo diferente', function (): void {
    // Pilares C30 são verticais; o lote é horizontal.
    $pilarC30 = new ElementoEstrutural('P-9', TipoDeElemento::Pilar, 'Pilar', null, ClasseDeResistencia::C30, new Abatimento(100), 5.0);
    $lote = loteC30();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $lote->adicionarConcretagem(concretagemComResultados(3, [35.0], $pilarC30)),
        'Os grupos não se misturam',
    );
});

teste('recusa a mesma concretagem duas vezes', function (): void {
    $lote = loteC30();
    $c = concretagemComResultados(1, [32.0]);
    $lote->adicionarConcretagem($c);

    lanca(ExcecaoDeDominio::class, static fn () => $lote->adicionarConcretagem($c), 'já está neste lote');
});

teste('recusa passar do volume máximo do grupo', function (): void {
    // Horizontal: 100 m³. Duas concretagens de 6 cargas × 8 m³ = 48 cada; a terceira estoura.
    $lote = loteC30();
    $lote->adicionarConcretagem(concretagemComResultados(1, [30.0, 30.0, 30.0, 30.0, 30.0, 30.0]));
    $lote->adicionarConcretagem(concretagemComResultados(2, [30.0, 30.0, 30.0, 30.0, 30.0, 30.0]));

    igualAproximado(96.0, $lote->volumeEmM3());

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $lote->adicionarConcretagem(concretagemComResultados(3, [30.0])),
        'acima do limite de 100 m³',
    );
});

teste('recusa o quarto dia de concretagem', function (): void {
    $lote = loteC30();
    $lote->adicionarConcretagem(concretagemComResultados(1, [30.0], data: '2026-03-10'));
    $lote->adicionarConcretagem(concretagemComResultados(2, [30.0], data: '2026-03-11'));
    $lote->adicionarConcretagem(concretagemComResultados(3, [30.0], data: '2026-03-12'));

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $lote->adicionarConcretagem(concretagemComResultados(4, [30.0], data: '2026-03-13')),
        'máximo da norma',
    );
});

teste('duas concretagens no mesmo dia contam um dia só', function (): void {
    $lote = loteC30();
    $lote->adicionarConcretagem(concretagemComResultados(1, [30.0], data: '2026-03-10'));
    $lote->adicionarConcretagem(concretagemComResultados(2, [30.0], data: '2026-03-10'));
    $lote->adicionarConcretagem(concretagemComResultados(3, [30.0], data: '2026-03-11'));
    $lote->adicionarConcretagem(concretagemComResultados(4, [30.0], data: '2026-03-12'));

    igual(4, count($lote->concretagens()));
});

grupo('Lote: julgamento');

teste('aceita quando o fck estimado atende o projeto', function (): void {
    // Seis exemplares: 32,4 29,8 31,1 33,6 30,5 35,0 → fck,est = 29,2. Lote é C25 aqui.
    $lote = new Lote('OBR-2026-007', ClasseDeResistencia::C25, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, TipoDeAmostragem::Parcial);
    $sapata = new ElementoEstrutural('SAP', TipoDeElemento::Fundacao, 'Sapata', null, ClasseDeResistencia::C25, new Abatimento(80), 60.0);

    $lote->adicionarConcretagem(concretagemComResultados(1, [32.4, 29.8, 31.1], $sapata));
    $lote->adicionarConcretagem(concretagemComResultados(2, [33.6, 30.5, 35.0], $sapata, '2026-03-11'));

    verdadeiro($lote->podeSerJulgado(), 'pode julgar');

    $estimativa = $lote->julgar(momento('2026-04-10 10:00'));

    igualAproximado(29.2, $estimativa->fckEstimadoEmMPa);
    igual(SituacaoDoLote::Aceito, $lote->situacao());
    verdadeiro($lote->foiAceito(), 'aceito');
});

teste('reprova quando não atende', function (): void {
    // Os mesmos 29,2 num lote C30: não conforme.
    $lote = loteC30();
    $lote->adicionarConcretagem(concretagemComResultados(1, [32.4, 29.8, 31.1]));
    $lote->adicionarConcretagem(concretagemComResultados(2, [33.6, 30.5, 35.0], data: '2026-03-11'));

    $lote->julgar(momento('2026-04-10 10:00'));

    igual(SituacaoDoLote::NaoConforme, $lote->situacao());
    falso($lote->foiAceito(), 'não conforme');
});

teste('não julga com exemplar pendente', function (): void {
    $lote = loteC30();
    $lote->adicionarConcretagem(concretagemComResultados(1, [32.0, 31.0, null, 33.0, 30.0, 34.0]));

    falso($lote->podeSerJulgado(), 'tem pendente');
    igual(1, count($lote->exemplaresPendentes()));

    lanca(ExcecaoDeDominio::class, static fn () => $lote->julgar(momento('2026-04-10 10:00')), 'aguardando rompimento');
});

teste('exemplar descartado nos dois corpos de prova conta como perdido, não como pendente', function (): void {
    $lote = loteC30();
    $concretagem = concretagemComResultados(1, [32.0, 31.0, null, 33.0, 30.0, 34.0, 32.5]);

    $exemplar = $concretagem->exemplar(3, IdadeDeEnsaio::VinteEOitoDias);
    $exemplar?->primeiro->descartar('Quebrou');
    $exemplar?->segundo->descartar('Quebrou também');

    $lote->adicionarConcretagem($concretagem);

    igual(0, count($lote->exemplaresPendentes()));
    igual(1, count($lote->exemplaresPerdidos()));
    igual(6, count($lote->exemplaresComResultado()), 'seis para a conta');
    verdadeiro($lote->podeSerJulgado(), 'pode julgar com os seis');
});

teste('lote vazio não julga', function (): void {
    lanca(ExcecaoDeDominio::class, static fn () => loteC30()->julgar(momento('2026-04-10 10:00')), 'está vazio');
});

teste('lote julgado não muda mais', function (): void {
    $lote = loteC30();
    $lote->adicionarConcretagem(concretagemComResultados(1, [32.4, 29.8, 31.1, 33.6, 30.5, 35.0]));
    $lote->julgar(momento('2026-04-10 10:00'));

    lanca(ExcecaoDeDominio::class, static fn () => $lote->julgar(momento('2026-04-11 10:00')), 'já foi julgado');
    lanca(ExcecaoDeDominio::class, static fn () => $lote->adicionarConcretagem(concretagemComResultados(2, [30.0])), 'já foi julgado');
});

teste('amostragem total com poucos exemplares vale o menor', function (): void {
    $lote = loteC30(TipoDeAmostragem::Total);
    $lote->adicionarConcretagem(concretagemComResultados(1, [33.0, 31.5, 34.0]));

    $estimativa = $lote->julgar(momento('2026-04-10 10:00'));

    igualAproximado(31.5, $estimativa->fckEstimadoEmMPa);
    verdadeiro($lote->foiAceito(), '31,5 ≥ 30');
});
