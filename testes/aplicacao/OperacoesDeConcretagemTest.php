<?php

declare(strict_types=1);

use ControleConcreto\Aplicacao\OperacoesDeConcretagem;
use ControleConcreto\Dominio\Concretagem\SituacaoDaConcretagem;
use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;
use ControleConcreto\Dominio\ExcecaoDeDominio;

/*
 * O serviço que a interface web chama para o dia de concretagem. O que se
 * prova aqui é o ciclo carregar → agir → gravar: cada chamada deixa o banco
 * no estado que o domínio decidiu, e o que o domínio recusa não é gravado.
 */

/** @return array{0: OperacoesDeConcretagem, 1: array} */
function operacoesDeTeste(): array
{
    $ambiente = ambienteComObra();

    return [
        new OperacoesDeConcretagem($ambiente['obras'], $ambiente['elementos'], $ambiente['concretagens']),
        $ambiente,
    ];
}

grupo('Operações de concretagem: abrir');

teste('abre a concretagem, numera e grava', function (): void {
    [$operacoes, $ambiente] = operacoesDeTeste();

    $concretagem = $operacoes->abrir('OBR-2026-007', 'L3-P4', momento('2026-03-10'), 'Usina', 'Marcus');

    igual(1, $concretagem->numero());

    $gravada = $ambiente['concretagens']->porNumero('OBR-2026-007', 1);
    igual('L3-P4', $gravada?->elemento->codigo);
    igual(SituacaoDaConcretagem::EmAndamento, $gravada?->situacao());
});

teste('recusa obra inexistente', function (): void {
    [$operacoes] = operacoesDeTeste();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $operacoes->abrir('OBR-NAO-EXISTE', 'L3-P4', momento('2026-03-10'), 'Usina', 'Marcus'),
        'não foi encontrada',
    );
});

teste('recusa elemento que não é da obra', function (): void {
    [$operacoes] = operacoesDeTeste();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $operacoes->abrir('OBR-2026-007', 'X-99', momento('2026-03-10'), 'Usina', 'Marcus'),
        'não existe na obra',
    );
});

grupo('Operações de concretagem: cargas e moldagem');

teste('recebe a carga e ela aparece ao recarregar', function (): void {
    [$operacoes, $ambiente] = operacoesDeTeste();
    $operacoes->abrir('OBR-2026-007', 'L3-P4', momento('2026-03-10'), 'Usina', 'Marcus');

    $carga = $operacoes->receberCarga('OBR-2026-007', 1, 'NF-1', 'ABC-1D23', 8.0, hora('08:00'), hora('08:45'), 100, null);

    verdadeiro($carga->foiAceita(), 'carga dentro da faixa é aceita');
    igual(1, count($ambiente['concretagens']->porNumero('OBR-2026-007', 1)?->cargas() ?? []));
});

teste('a devolução também é gravada, porque é registro', function (): void {
    [$operacoes, $ambiente] = operacoesDeTeste();
    $operacoes->abrir('OBR-2026-007', 'L3-P4', momento('2026-03-10'), 'Usina', 'Marcus');

    // Abatimento 160 contra faixa 100 ± 20: devolvida.
    $carga = $operacoes->receberCarga('OBR-2026-007', 1, 'NF-2', null, 8.0, hora('08:00'), hora('08:45'), 160, null);

    verdadeiro($carga->foiDevolvida(), 'abatimento fora da faixa devolve');

    $gravada = $ambiente['concretagens']->porNumero('OBR-2026-007', 1);
    igual(1, count($gravada?->cargasDevolvidas() ?? []));
    igualAproximado(0.0, $gravada?->volumeAceitoEmM3() ?? -1.0);
});

teste('molda e os corpos de prova entram na agenda', function (): void {
    [$operacoes, $ambiente] = operacoesDeTeste();
    $operacoes->abrir('OBR-2026-007', 'L3-P4', momento('2026-03-10'), 'Usina', 'Marcus');
    $operacoes->receberCarga('OBR-2026-007', 1, 'NF-1', null, 8.0, hora('08:00'), hora('08:45'), 100, null);

    $exemplares = $operacoes->moldar('OBR-2026-007', 1, 1, hora('09:00'), [IdadeDeEnsaio::SeteDias, IdadeDeEnsaio::VinteEOitoDias]);

    igual(2, count($exemplares));
    igual(4, $ambiente['agenda']->totalEmCura());
});

teste('não grava o que o domínio recusou', function (): void {
    [$operacoes, $ambiente] = operacoesDeTeste();
    $operacoes->abrir('OBR-2026-007', 'L3-P4', momento('2026-03-10'), 'Usina', 'Marcus');

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $operacoes->moldar('OBR-2026-007', 1, 1, hora('09:00'), [IdadeDeEnsaio::VinteEOitoDias]),
        'Não existe carga',
    );

    igual(0, $ambiente['agenda']->totalEmCura());
});

teste('concretagem inexistente dá erro de domínio, não de banco', function (): void {
    [$operacoes] = operacoesDeTeste();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $operacoes->receberCarga('OBR-2026-007', 42, 'NF-1', null, 8.0, hora('08:00'), hora('08:45'), 100, null),
        'Não existe concretagem',
    );
});

grupo('Operações de concretagem: concluir e cancelar');

teste('conclui e a situação persiste', function (): void {
    [$operacoes, $ambiente] = operacoesDeTeste();
    $operacoes->abrir('OBR-2026-007', 'L3-P4', momento('2026-03-10'), 'Usina', 'Marcus');
    $operacoes->receberCarga('OBR-2026-007', 1, 'NF-1', null, 8.0, hora('08:00'), hora('08:45'), 100, null);
    $operacoes->moldar('OBR-2026-007', 1, 1, hora('09:00'), [IdadeDeEnsaio::VinteEOitoDias]);

    $operacoes->concluir('OBR-2026-007', 1);

    igual(SituacaoDaConcretagem::Concluida, $ambiente['concretagens']->porNumero('OBR-2026-007', 1)?->situacao());
});

teste('cancela a concretagem sem carga aceita', function (): void {
    [$operacoes, $ambiente] = operacoesDeTeste();
    $operacoes->abrir('OBR-2026-007', 'L3-P4', momento('2026-03-10'), 'Usina', 'Marcus');

    $operacoes->cancelar('OBR-2026-007', 1);

    igual(SituacaoDaConcretagem::Cancelada, $ambiente['concretagens']->porNumero('OBR-2026-007', 1)?->situacao());
});

teste('recusa cancelar com concreto na forma, e nada muda no banco', function (): void {
    [$operacoes, $ambiente] = operacoesDeTeste();
    $operacoes->abrir('OBR-2026-007', 'L3-P4', momento('2026-03-10'), 'Usina', 'Marcus');
    $operacoes->receberCarga('OBR-2026-007', 1, 'NF-1', null, 8.0, hora('08:00'), hora('08:45'), 100, null);

    lanca(ExcecaoDeDominio::class, static fn () => $operacoes->cancelar('OBR-2026-007', 1), 'não se cancela');

    igual(SituacaoDaConcretagem::EmAndamento, $ambiente['concretagens']->porNumero('OBR-2026-007', 1)?->situacao());
});
