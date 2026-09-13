<?php

declare(strict_types=1);

namespace ControleConcreto\Aplicacao;

use DateTimeImmutable;

/**
 * As perguntas que o laboratório faz todo dia de manhã.
 *
 * Interface de leitura: quem implementa consulta o banco direto, com os
 * índices da tabela corpos_de_prova, sem passar pelo agregado.
 */
interface AgendaDoLaboratorio
{
    /**
     * Corpos de prova em cura cujo rompimento previsto cai no intervalo.
     *
     * @return ItemDaAgenda[] do mais urgente para o menos
     */
    public function comRompimentoEntre(DateTimeImmutable $inicio, DateTimeImmutable $fim): array;

    /**
     * Corpos de prova em cura cuja janela já fechou: perderam a idade.
     *
     * @return ItemDaAgenda[] do mais antigo para o mais recente
     */
    public function vencidos(DateTimeImmutable $agora): array;

    /** Quantos estão em cura, no total. */
    public function totalEmCura(): int;
}
