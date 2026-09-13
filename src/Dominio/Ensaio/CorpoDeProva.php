<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Ensaio;

use DateInterval;
use DateTimeImmutable;
use ControleConcreto\Dominio\Regras;

/**
 * Um cilindro de concreto moldado de uma carga, esperando a hora de ir para
 * a prensa.
 *
 * Nesta etapa o corpo de prova sabe de tempo: quando foi moldado, com que
 * idade deve romper, e a janela de horário em que o rompimento vale. O
 * resultado do ensaio entra na Etapa 5 — aqui a pergunta é "quando", não
 * "quanto".
 */
final class CorpoDeProva
{
    public readonly string $identificacao;
    public readonly DateTimeImmutable $moldadoEm;
    public readonly IdadeDeEnsaio $idade;

    private SituacaoDoCorpoDeProva $situacao = SituacaoDoCorpoDeProva::Curando;

    public function __construct(
        string $identificacao,
        DateTimeImmutable $moldadoEm,
        IdadeDeEnsaio $idade,
    ) {
        $this->identificacao = Regras::textoObrigatorio($identificacao, 'Identificação do corpo de prova', 40);
        $this->moldadoEm = $moldadoEm;
        $this->idade = $idade;
    }

    public function situacao(): SituacaoDoCorpoDeProva
    {
        return $this->situacao;
    }

    /** O instante exato em que o corpo de prova completa a idade. */
    public function rompimentoPrevisto(): DateTimeImmutable
    {
        return $this->moldadoEm->add(new DateInterval("P{$this->idade->dias()}D"));
    }

    public function inicioDaJanela(): DateTimeImmutable
    {
        return $this->deslocar($this->rompimentoPrevisto(), -$this->idade->toleranciaEmHoras());
    }

    public function fimDaJanela(): DateTimeImmutable
    {
        return $this->deslocar($this->rompimentoPrevisto(), $this->idade->toleranciaEmHoras());
    }

    /** Diz se romper neste instante ainda representa a idade nominal. */
    public function dentroDaJanela(DateTimeImmutable $momento): bool
    {
        return $momento >= $this->inicioDaJanela() && $momento <= $this->fimDaJanela();
    }

    /** Ainda não chegou a hora: romper agora seria antes da idade. */
    public function aindaNaoPodeRomper(DateTimeImmutable $agora): bool
    {
        return $agora < $this->inicioDaJanela();
    }

    /**
     * A janela passou e ninguém rompeu. O corpo de prova perdeu a idade e o
     * ensaio dele já não vale — é o que a agenda do laboratório existe para
     * evitar.
     */
    public function estaVencido(DateTimeImmutable $agora): bool
    {
        return $this->situacao->aguardaRompimento() && $agora > $this->fimDaJanela();
    }

    /** Horas até o início da janela; negativo se já abriu. */
    public function horasAteAJanela(DateTimeImmutable $agora): float
    {
        return round(($this->inicioDaJanela()->getTimestamp() - $agora->getTimestamp()) / 3600, 1);
    }

    public function descricaoDaJanela(): string
    {
        return sprintf(
            '%s ± %sh — de %s a %s',
            $this->rompimentoPrevisto()->format('d/m/Y H:i'),
            rtrim(rtrim(number_format($this->idade->toleranciaEmHoras(), 1, ',', ''), '0'), ','),
            $this->inicioDaJanela()->format('d/m H:i'),
            $this->fimDaJanela()->format('d/m H:i'),
        );
    }

    /**
     * DateInterval não aceita fração de hora, e a tolerância de 24h é 0,5h.
     * Somar segundos ao timestamp resolve sem depender de formato.
     */
    private function deslocar(DateTimeImmutable $base, float $horas): DateTimeImmutable
    {
        $segundos = (int) round($horas * 3600);
        $sinal = $segundos < 0 ? '-' : '+';

        return $base->modify(sprintf('%s%d seconds', $sinal, abs($segundos)));
    }
}
