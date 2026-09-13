<?php

declare(strict_types=1);

use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;

/** Grava a concretagem de teste com uma carga e o par 7/28 moldado às 9h de 10/03/2026. */
function concretagemGravada(array $app): int
{
    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100);
    moldaPadrao($concretagem);

    return $app['concretagens']->salvar($concretagem);
}

grupo('Agenda do laboratório');

teste('lista o que rompe no intervalo, do mais urgente ao menos', function (): void {
    $app = ambienteComObra();
    concretagemGravada($app);

    // 7 dias: rompe 17/03 às 9h. 28 dias: 07/04 às 9h.
    $itens = $app['agenda']->comRompimentoEntre(momento('2026-03-17 00:00'), momento('2026-03-17 23:59'));

    igual(2, count($itens), 'os dois de 7 dias');
    igual('C1-7d-A', $itens[0]->identificacao);
    igual(IdadeDeEnsaio::SeteDias, $itens[0]->idade);
    igual('L3-P4 — Laje L3 (4º pavimento)', $itens[0]->elementoIdentificacao);
    igual(30, $itens[0]->fckDeProjeto);
    igual('NF-1001', $itens[0]->notaFiscal);
});

teste('nada no intervalo devolve lista vazia', function (): void {
    $app = ambienteComObra();
    concretagemGravada($app);

    igual([], $app['agenda']->comRompimentoEntre(momento('2026-03-20 00:00'), momento('2026-03-25 23:59')));
});

teste('acusa os vencidos: janela fechada e ainda curando', function (): void {
    $app = ambienteComObra();
    concretagemGravada($app);

    // Fim da janela de 7 dias: 17/03 15:00. Em 18/03 os dois venceram; os de 28 não.
    $vencidos = $app['agenda']->vencidos(momento('2026-03-18 08:00'));

    igual(2, count($vencidos));
    igual(IdadeDeEnsaio::SeteDias, $vencidos[0]->idade);
    verdadeiro($vencidos[0]->estaVencido(momento('2026-03-18 08:00')), 'o item sabe que venceu');
});

teste('dentro da janela ainda não é vencido', function (): void {
    $app = ambienteComObra();
    concretagemGravada($app);

    igual([], $app['agenda']->vencidos(momento('2026-03-17 14:00')));
});

teste('conta o total em cura', function (): void {
    $app = ambienteComObra();
    concretagemGravada($app);

    igual(4, $app['agenda']->totalEmCura());
});

teste('o item descreve o próprio estado para a tela', function (): void {
    $app = ambienteComObra();
    concretagemGravada($app);

    $item = $app['agenda']->comRompimentoEntre(momento('2026-04-07 00:00'), momento('2026-04-07 23:59'))[0];

    igual('em 27 dias', $item->estado(momento('2026-03-10 13:00')));
    igual('amanhã', $item->estado(momento('2026-04-05 14:00')));
    igual('na janela', $item->estado(momento('2026-04-07 09:00')));
    igual('vencido', $item->estado(momento('2026-04-09 09:00')));
});
