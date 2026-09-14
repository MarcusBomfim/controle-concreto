<?php

declare(strict_types=1);

use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\Estrutura\GrupoDeSolicitacao;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Lote\CondicaoDePreparo;
use ControleConcreto\Dominio\Lote\SituacaoDoLote;
use ControleConcreto\Dominio\Lote\TipoDeAmostragem;

/** Grava duas concretagens C30 concluídas com seis exemplares no total (fck,est = 29,2). */
function ambienteComConcretagensJulgaveis(): array
{
    $app = ambienteComObra();

    $primeira = concretagemComResultados(0, [32.4, 29.8, 31.1]);
    $segunda = concretagemComResultados(0, [33.6, 30.5, 35.0], data: '2026-03-11');

    // O helper numera com 0 para o repositório atribuir; corrige antes de gravar.
    $app['n1'] = $app['concretagens']->salvar($primeira);
    $app['n2'] = $app['concretagens']->salvar($segunda);

    return $app;
}

grupo('Formar lote');

teste('forma, grava e lê de volta com as concretagens', function (): void {
    $app = ambienteComConcretagensJulgaveis();

    $lote = $app['formarLote']->executar(
        'OBR-2026-007',
        ClasseDeResistencia::C30,
        GrupoDeSolicitacao::Horizontal,
        CondicaoDePreparo::A,
        TipoDeAmostragem::Parcial,
        [$app['n1'], $app['n2']],
    );

    igual(1, $lote->numero());

    $lido = $app['lotes']->porNumero('OBR-2026-007', 1);

    igual(2, count($lido?->concretagens() ?? []));
    igual(6, count($lido?->exemplaresDeAceitacao() ?? []));
    igualAproximado(48.0, $lido?->volumeEmM3() ?? 0.0);
    igual(SituacaoDoLote::Aberto, $lido?->situacao());
});

teste('uma concretagem entra em um lote só', function (): void {
    $app = ambienteComConcretagensJulgaveis();

    $app['formarLote']->executar('OBR-2026-007', ClasseDeResistencia::C30, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, TipoDeAmostragem::Parcial, [$app['n1']]);

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $app['formarLote']->executar('OBR-2026-007', ClasseDeResistencia::C30, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, TipoDeAmostragem::Parcial, [$app['n1']]),
        'já está no lote 1',
    );

    igual(1, $app['lotes']->loteDaConcretagem('OBR-2026-007', $app['n1']));
    igual(null, $app['lotes']->loteDaConcretagem('OBR-2026-007', $app['n2']));
});

teste('a chave primária do banco também impede a concretagem em dois lotes', function (): void {
    // Por fora do caso de uso: insere o vínculo duplicado direto no SQL.
    $app = ambienteComConcretagensJulgaveis();
    $app['formarLote']->executar('OBR-2026-007', ClasseDeResistencia::C30, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, TipoDeAmostragem::Parcial, [$app['n1']]);
    $app['formarLote']->executar('OBR-2026-007', ClasseDeResistencia::C30, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, TipoDeAmostragem::Parcial, [$app['n2']]);

    lanca(PDOException::class, static function () use ($app): void {
        $app['conexao']->exec(
            "INSERT INTO lote_concretagens (obra_codigo, lote_numero, concretagem_numero)
             VALUES ('OBR-2026-007', 2, {$app['n1']})"
        );
    });
});

teste('recusa lista vazia e concretagem inexistente', function (): void {
    $app = ambienteComConcretagensJulgaveis();

    lanca(ExcecaoDeDominio::class, static fn () => $app['formarLote']->executar('OBR-2026-007', ClasseDeResistencia::C30, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, TipoDeAmostragem::Parcial, []), 'ao menos uma');
    lanca(ExcecaoDeDominio::class, static fn () => $app['formarLote']->executar('OBR-2026-007', ClasseDeResistencia::C30, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, TipoDeAmostragem::Parcial, [99]), 'Não existe concretagem');
});

grupo('Julgar lote');

teste('julga, grava o veredito e a memória de cálculo sobrevive à releitura', function (): void {
    $app = ambienteComConcretagensJulgaveis();
    $app['formarLote']->executar('OBR-2026-007', ClasseDeResistencia::C30, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, TipoDeAmostragem::Parcial, [$app['n1'], $app['n2']]);

    $lote = $app['julgarLote']->executar('OBR-2026-007', 1, momento('2026-04-10 10:00'));

    igual(SituacaoDoLote::NaoConforme, $lote->situacao(), '29,2 não atende C30');

    $lido = $app['lotes']->porNumero('OBR-2026-007', 1);
    $estimativa = $lido?->estimativa();

    igual(SituacaoDoLote::NaoConforme, $lido?->situacao());
    igualAproximado(29.2, $estimativa?->fckEstimadoEmMPa ?? 0.0);
    igualAproximado(0.86, $estimativa?->psi6 ?? 0.0);
    igual([29.8, 30.5, 31.1, 32.4, 33.6, 35.0], $estimativa?->valoresOrdenados);
    verdadeiro(str_contains($estimativa?->metodo ?? '', 'm = 3'), 'o método foi guardado');
    igual('2026-04-10 10:00', $lido?->julgadoEm()?->format('Y-m-d H:i'));
});

teste('o banco recusa lote julgado sem fck estimado', function (): void {
    $app = ambienteComConcretagensJulgaveis();
    $app['formarLote']->executar('OBR-2026-007', ClasseDeResistencia::C30, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, TipoDeAmostragem::Parcial, [$app['n1']]);

    lanca(PDOException::class, static function () use ($app): void {
        $app['conexao']->exec("UPDATE lotes SET situacao = 'aceito' WHERE numero = 1");
    });
});

teste('não julga duas vezes', function (): void {
    $app = ambienteComConcretagensJulgaveis();
    $app['formarLote']->executar('OBR-2026-007', ClasseDeResistencia::C30, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, TipoDeAmostragem::Parcial, [$app['n1'], $app['n2']]);
    $app['julgarLote']->executar('OBR-2026-007', 1, momento('2026-04-10 10:00'));

    lanca(ExcecaoDeDominio::class, static fn () => $app['julgarLote']->executar('OBR-2026-007', 1), 'já foi julgado');
});

teste('lista os lotes da obra', function (): void {
    $app = ambienteComConcretagensJulgaveis();
    $app['formarLote']->executar('OBR-2026-007', ClasseDeResistencia::C30, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, TipoDeAmostragem::Parcial, [$app['n1']]);
    $app['formarLote']->executar('OBR-2026-007', ClasseDeResistencia::C30, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, TipoDeAmostragem::Parcial, [$app['n2']]);

    $numeros = array_map(static fn ($l) => $l->numero(), $app['lotes']->daObra('OBR-2026-007'));

    igual([2, 1], $numeros, 'do mais recente para o mais antigo');
});
