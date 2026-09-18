<?php

declare(strict_types=1);

use ControleConcreto\Aplicacao\TratarNaoConformidade;
use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\Estrutura\GrupoDeSolicitacao;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Lote\CondicaoDePreparo;
use ControleConcreto\Dominio\Lote\SituacaoDoLote;
use ControleConcreto\Dominio\Lote\TipoDeAmostragem;
use ControleConcreto\Dominio\NaoConformidade\Desfecho;
use ControleConcreto\Dominio\NaoConformidade\Providencia;
use ControleConcreto\Dominio\NaoConformidade\ResultadoDaProvidencia;
use ControleConcreto\Dominio\NaoConformidade\SituacaoDaNaoConformidade;
use ControleConcreto\Dominio\NaoConformidade\TipoDeProvidencia;

/*
 * Lote C30 com fck,est = 29,2 (mesmas concretagens dos testes de lote):
 * reprovado por 0,8 MPa. É o cenário em que a não conformidade nasce.
 */
function ambienteComLoteReprovado(): array
{
    $app = ambienteComConcretagensJulgaveis();
    $app['formarLote']->executar('OBR-2026-007', ClasseDeResistencia::C30, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, TipoDeAmostragem::Parcial, [$app['n1'], $app['n2']]);
    $app['julgarLote']->executar('OBR-2026-007', 1, momento('2026-04-10 10:00'));
    $app['tratar'] = new TratarNaoConformidade($app['naoConformidades']);

    return $app;
}

grupo('Não conformidade: nasce com o julgamento');

teste('lote reprovado abre a não conformidade na mesma transação', function (): void {
    $app = ambienteComLoteReprovado();

    $nc = $app['naoConformidades']->doLote('OBR-2026-007', 1);

    igual(SituacaoDaNaoConformidade::Aberta, $nc?->situacao());
    igualAproximado(30.0, $nc?->fckDeProjetoEmMPa ?? 0.0);
    igualAproximado(29.2, $nc?->fckEstimadoEmMPa ?? 0.0);
    igual('2026-04-10 10:00', $nc?->abertaEm->format('Y-m-d H:i'));
    igual(1, count($app['naoConformidades']->abertas()));
});

teste('lote aceito não abre nada', function (): void {
    $app = ambienteComObra();

    // Seis exemplares folgados: C30 com fck,est bem acima de 30.
    $n = $app['concretagens']->salvar(concretagemComResultados(0, [36.0, 37.5, 38.2, 36.8, 39.0, 40.1]));
    $app['formarLote']->executar('OBR-2026-007', ClasseDeResistencia::C30, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, TipoDeAmostragem::Parcial, [$n]);

    $lote = $app['julgarLote']->executar('OBR-2026-007', 1, momento('2026-04-10 10:00'));

    igual(SituacaoDoLote::Aceito, $lote->situacao());
    igual(null, $app['naoConformidades']->doLote('OBR-2026-007', 1));
});

teste('o banco também recusa não conformidade de lote que atendeu', function (): void {
    $app = ambienteComLoteReprovado();

    lanca(
        PDOException::class,
        static fn () => $app['conexao']->exec(
            "INSERT INTO nao_conformidades (obra_codigo, lote_numero, aberta_em, fck_projeto_mpa, fck_estimado_mpa, situacao)
             VALUES ('OBR-2026-007', 1, '2026-04-10 10:00:00', 30, 31, 'aberta')"
        ),
    );
});

grupo('Não conformidade: tratamento persistido');

teste('providências e encerramento sobrevivem à releitura', function (): void {
    $app = ambienteComLoteReprovado();

    $app['tratar']->registrarProvidencia('OBR-2026-007', 1, new Providencia(
        TipoDeProvidencia::EnsaioNaoDestrutivo,
        momento('2026-04-12'),
        'Esclerometria em 12 pontos da laje; menor índice no eixo 3.',
        ResultadoDaProvidencia::Informativo,
        'Laboratório Litoral',
    ));

    $app['tratar']->registrarProvidencia('OBR-2026-007', 1, new Providencia(
        TipoDeProvidencia::ExtracaoDeTestemunhos,
        momento('2026-04-18'),
        'Três testemunhos extraídos no eixo 3 e rompidos conforme a NBR 7680.',
        ResultadoDaProvidencia::Favoravel,
        'Laboratório Litoral',
        31.4,
    ));

    $lida = $app['naoConformidades']->doLote('OBR-2026-007', 1);

    igual(2, count($lida?->providencias() ?? []));
    igual(TipoDeProvidencia::ExtracaoDeTestemunhos, $lida?->providencias()[1]->tipo);
    igualAproximado(31.4, $lida?->providencias()[1]->fckObtidoEmMPa ?? 0.0);
    igual([Desfecho::EstruturaAceita], $lida?->desfechosPossiveis());

    $app['tratar']->encerrar('OBR-2026-007', 1, Desfecho::EstruturaAceita, 'Testemunhos a 31,4 MPa: a peça atende.', momento('2026-04-20 09:00'));

    $encerrada = $app['naoConformidades']->doLote('OBR-2026-007', 1);

    igual(SituacaoDaNaoConformidade::Encerrada, $encerrada?->situacao());
    igual(Desfecho::EstruturaAceita, $encerrada?->desfecho());
    igual('Testemunhos a 31,4 MPa: a peça atende.', $encerrada?->parecer());
    igual([], $app['naoConformidades']->abertas(), 'nada mais aberto');
});

teste('a recusa do domínio não deixa nada no banco', function (): void {
    $app = ambienteComLoteReprovado();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['tratar']->encerrar('OBR-2026-007', 1, Desfecho::EstruturaAceita, 'Sem base', momento('2026-04-20')),
        'sem tratamento',
    );

    igual(SituacaoDaNaoConformidade::Aberta, $app['naoConformidades']->doLote('OBR-2026-007', 1)?->situacao());
});

teste('lote sem não conformidade dá erro de domínio', function (): void {
    $app = ambienteComLoteReprovado();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['tratar']->encerrar('OBR-2026-007', 99, Desfecho::Reforcada, 'Parecer', momento('2026-04-20')),
        'não tem não conformidade',
    );
});

teste('a lista da obra traz abertas e encerradas', function (): void {
    $app = ambienteComLoteReprovado();

    igual(1, count($app['naoConformidades']->daObra('OBR-2026-007')));
    igual([], $app['naoConformidades']->daObra('OUTRA'));
});

teste('apagar a obra leva a não conformidade e as providências junto', function (): void {
    $app = ambienteComLoteReprovado();

    $app['tratar']->registrarProvidencia('OBR-2026-007', 1, new Providencia(
        TipoDeProvidencia::RevisaoDeProjeto,
        momento('2026-04-12'),
        'Projetista reverificou a laje com fck de 29,2 MPa.',
        ResultadoDaProvidencia::Favoravel,
        'Projetista',
    ));

    $app['conexao']->exec("DELETE FROM obras WHERE codigo = 'OBR-2026-007'");

    igual(0, (int) $app['conexao']->query('SELECT COUNT(*) FROM nao_conformidades')->fetchColumn());
    igual(0, (int) $app['conexao']->query('SELECT COUNT(*) FROM providencias')->fetchColumn());
});
