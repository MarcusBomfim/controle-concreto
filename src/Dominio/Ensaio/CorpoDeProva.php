<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Ensaio;

use DateInterval;
use DateTimeImmutable;
use ControleConcreto\Dominio\ExcecaoDeDominio;
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
    private ?ResultadoDeEnsaio $resultado = null;
    private ?string $motivoDoDescarte = null;

    public function __construct(
        string $identificacao,
        DateTimeImmutable $moldadoEm,
        IdadeDeEnsaio $idade,
    ) {
        $this->identificacao = Regras::textoObrigatorio($identificacao, 'Identificação do corpo de prova', 40);
        $this->moldadoEm = $moldadoEm;
        $this->idade = $idade;
    }

    /** Recria um corpo de prova vindo do banco, na situação em que estava. */
    public static function reconstituir(
        string $identificacao,
        DateTimeImmutable $moldadoEm,
        IdadeDeEnsaio $idade,
        SituacaoDoCorpoDeProva $situacao,
        ?ResultadoDeEnsaio $resultado = null,
        ?string $motivoDoDescarte = null,
    ): self {
        $corpoDeProva = new self($identificacao, $moldadoEm, $idade);
        $corpoDeProva->situacao = $situacao;
        $corpoDeProva->resultado = $resultado;
        $corpoDeProva->motivoDoDescarte = $motivoDoDescarte;

        return $corpoDeProva;
    }

    public function situacao(): SituacaoDoCorpoDeProva
    {
        return $this->situacao;
    }

    /**
     * Registra o rompimento na prensa.
     *
     * A regra com mais consequência do módulo: o resultado só entra se o
     * rompimento aconteceu dentro da janela da idade. Um cilindro de 28 dias
     * rompido no 30º dia é mais forte do que era aos 28 — o número existe,
     * mas não representa a idade nominal. Não é dado; é ruído com cara de
     * dado. Quem rompeu fora da hora descarta e anota o motivo.
     */
    public function romper(ResultadoDeEnsaio $resultado, ?DateTimeImmutable $agora = null): void
    {
        if (!$this->situacao->aguardaRompimento()) {
            throw new ExcecaoDeDominio(sprintf(
                'O corpo de prova %s já está %s.',
                $this->identificacao,
                mb_strtolower($this->situacao->rotulo()),
            ));
        }

        $limite = $agora ?? new DateTimeImmutable('now');

        if ($resultado->rompidoEm > $limite) {
            throw new ExcecaoDeDominio('A hora do rompimento ainda não chegou. Confira o relógio.');
        }

        if (!$this->dentroDaJanela($resultado->rompidoEm)) {
            throw new ExcecaoDeDominio(sprintf(
                'O corpo de prova %s foi rompido às %s, fora da janela de %s. '
                . 'O resultado não representa a idade de %s: descarte-o e registre o motivo.',
                $this->identificacao,
                $resultado->rompidoEm->format('d/m H:i'),
                $this->descricaoDaJanela(),
                $this->idade->rotulo(),
            ));
        }

        $this->resultado = $resultado;
        $this->situacao = SituacaoDoCorpoDeProva::Rompido;
    }

    /**
     * Tira o corpo de prova do controle sem resultado: quebrou na desforma,
     * foi perdido, ou passou da janela. O motivo é obrigatório — um cilindro
     * que some sem explicação é o que auditoria procura.
     */
    public function descartar(string $motivo): void
    {
        if (!$this->situacao->aguardaRompimento()) {
            throw new ExcecaoDeDominio(sprintf(
                'O corpo de prova %s já está %s e não pode ser descartado.',
                $this->identificacao,
                mb_strtolower($this->situacao->rotulo()),
            ));
        }

        $this->motivoDoDescarte = Regras::textoObrigatorio($motivo, 'Motivo do descarte', 300);
        $this->situacao = SituacaoDoCorpoDeProva::Descartado;
    }

    public function resultado(): ?ResultadoDeEnsaio
    {
        return $this->resultado;
    }

    public function resistenciaEmMPa(): ?float
    {
        return $this->resultado?->resistenciaEmMPa();
    }

    public function motivoDoDescarte(): ?string
    {
        return $this->motivoDoDescarte;
    }

    public function foiRompido(): bool
    {
        return $this->situacao === SituacaoDoCorpoDeProva::Rompido;
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
