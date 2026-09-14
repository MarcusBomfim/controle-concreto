<?php

declare(strict_types=1);

use ControleConcreto\Aplicacao\DescartarCorpoDeProva;
use ControleConcreto\Aplicacao\RegistrarRompimento;
use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;
use ControleConcreto\Dominio\Ensaio\SituacaoDoCorpoDeProva;
use ControleConcreto\Dominio\ExcecaoDeDominio;

/** Ambiente com uma concretagem gravada (carga 1, exemplares de 7 e 28 dias) e os casos de uso. */
function ambienteComRompimento(): array
{
    $app = ambienteComObra();

    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100);
    moldaPadrao($concretagem);
    $app['numero'] = $app['concretagens']->salvar($concretagem);

    $app['registrar'] = new RegistrarRompimento($app['concretagens']);
    $app['descartar'] = new DescartarCorpoDeProva($app['concretagens']);

    return $app;
}

grupo('Registrar rompimento: caminho completo');

teste('grava o resultado e ele volta do banco', function (): void {
    $app = ambienteComRompimento();

    $cp = $app['registrar']->executar(
        'OBR-2026-007',
        $app['numero'],
        'c1-28d-a',
        260.0,
        100,
        momento('2026-04-07 10:30'),
        momento('2026-04-07 11:00'),
    );

    igualAproximado(33.1, $cp->resistenciaEmMPa() ?? 0.0, 'na hora');

    $lido = $app['concretagens']->porNumero('OBR-2026-007', $app['numero'])?->corpoDeProva('C1-28d-A');

    igual(SituacaoDoCorpoDeProva::Rompido, $lido?->situacao());
    igualAproximado(33.1, $lido?->resistenciaEmMPa() ?? 0.0, 'depois de recarregar');
    igualAproximado(260.0, $lido?->resultado()?->cargaDeRupturaEmKN ?? 0.0);
    igual('2026-04-07 10:30', $lido?->resultado()?->rompidoEm->format('Y-m-d H:i'));
});

teste('a resistência fica gravada no banco, pronta para a conta do lote', function (): void {
    $app = ambienteComRompimento();

    $app['registrar']->executar('OBR-2026-007', $app['numero'], 'C1-28d-A', 260.0, 100, momento('2026-04-07 10:30'), momento('2026-04-07 11:00'));

    $gravada = $app['conexao']->query(
        "SELECT resistencia_mpa FROM corpos_de_prova WHERE identificacao = 'C1-28d-A'"
    )->fetchColumn();

    igualAproximado(33.1, (float) $gravada);
});

teste('a agenda deixa de listar o que foi rompido', function (): void {
    $app = ambienteComRompimento();

    igual(4, $app['agenda']->totalEmCura(), 'antes');

    $app['registrar']->executar('OBR-2026-007', $app['numero'], 'C1-28d-A', 260.0, 100, momento('2026-04-07 10:30'), momento('2026-04-07 11:00'));

    igual(3, $app['agenda']->totalEmCura(), 'depois');
});

teste('recusa fora da janela sem tocar no banco', function (): void {
    $app = ambienteComRompimento();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['registrar']->executar(
            'OBR-2026-007',
            $app['numero'],
            'C1-28d-A',
            260.0,
            100,
            momento('2026-04-10 10:30'),
            momento('2026-04-11 00:00'),
        ),
        'fora da janela',
    );

    igual(
        SituacaoDoCorpoDeProva::Curando,
        $app['concretagens']->porNumero('OBR-2026-007', $app['numero'])?->corpoDeProva('C1-28d-A')?->situacao(),
    );
});

teste('recusa corpo de prova inexistente', function (): void {
    $app = ambienteComRompimento();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['registrar']->executar('OBR-2026-007', $app['numero'], 'C9-28d-Z', 260.0, 100, momento('2026-04-07 10:30')),
        'Confira a etiqueta',
    );
});

teste('recusa diâmetro fora da norma', function (): void {
    $app = ambienteComRompimento();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['registrar']->executar('OBR-2026-007', $app['numero'], 'C1-28d-A', 260.0, 120, momento('2026-04-07 10:30')),
        'prevê 100 ou 150',
    );
});

grupo('Descartar corpo de prova');

teste('descarta com motivo e o exemplar segue com um só', function (): void {
    $app = ambienteComRompimento();

    $app['descartar']->executar('OBR-2026-007', $app['numero'], 'C1-28d-B', 'Quebrou na desforma');
    $app['registrar']->executar('OBR-2026-007', $app['numero'], 'C1-28d-A', 260.0, 100, momento('2026-04-07 10:30'), momento('2026-04-07 11:00'));

    $exemplar = $app['concretagens']->porNumero('OBR-2026-007', $app['numero'])?->exemplar(1, IdadeDeEnsaio::VinteEOitoDias);

    igual('Quebrou na desforma', $exemplar?->segundo->motivoDoDescarte());
    igualAproximado(33.1, $exemplar?->resistenciaEmMPa() ?? 0.0);
    verdadeiro($exemplar?->estaIncompleto() ?? false, 'marcado como incompleto');
});

grupo('Gatilhos do banco');

teste('o banco recusa "rompido" sem resultado', function (): void {
    // O mesmo que o domínio garante, repetido no banco: nenhum caminho escapa.
    $app = ambienteComRompimento();

    lanca(PDOException::class, static function () use ($app): void {
        $app['conexao']->exec(
            "UPDATE corpos_de_prova SET situacao = 'rompido' WHERE identificacao = 'C1-28d-A'"
        );
    });
});

teste('o banco recusa "descartado" sem motivo', function (): void {
    $app = ambienteComRompimento();

    lanca(PDOException::class, static function () use ($app): void {
        $app['conexao']->exec(
            "UPDATE corpos_de_prova SET situacao = 'descartado' WHERE identificacao = 'C1-28d-A'"
        );
    });
});

teste('o banco não deixa rompido voltar a curando', function (): void {
    $app = ambienteComRompimento();
    $app['registrar']->executar('OBR-2026-007', $app['numero'], 'C1-28d-A', 260.0, 100, momento('2026-04-07 10:30'), momento('2026-04-07 11:00'));

    lanca(PDOException::class, static function () use ($app): void {
        $app['conexao']->exec(
            "UPDATE corpos_de_prova SET situacao = 'curando' WHERE identificacao = 'C1-28d-A'"
        );
    });
});
