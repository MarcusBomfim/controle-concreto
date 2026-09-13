<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Ensaio;

use DateTimeImmutable;
use ControleConcreto\Dominio\Regras;

/**
 * Dois corpos de prova da mesma carga, moldados no mesmo ato, para a mesma
 * idade. É a unidade que a norma conta.
 *
 * A NBR 12655 não olha corpo de prova isolado: olha o exemplar, e a
 * resistência do exemplar é a MAIOR entre as duas. A lógica é que os dois
 * vieram do mesmo concreto — se um deu menos, foi defeito de moldagem, cura
 * ou ensaio, e não do concreto. O menor é descartado como ruído.
 */
final class Exemplar
{
    public readonly int $cargaNumero;
    public readonly IdadeDeEnsaio $idade;
    public readonly DateTimeImmutable $moldadoEm;
    public readonly CorpoDeProva $primeiro;
    public readonly CorpoDeProva $segundo;

    private function __construct(
        int $cargaNumero,
        IdadeDeEnsaio $idade,
        DateTimeImmutable $moldadoEm,
        CorpoDeProva $primeiro,
        CorpoDeProva $segundo,
    ) {
        $this->cargaNumero = Regras::inteiroPositivo($cargaNumero, 'Número da carga');
        $this->idade = $idade;
        $this->moldadoEm = $moldadoEm;
        $this->primeiro = $primeiro;
        $this->segundo = $segundo;
    }

    /** Molda os dois corpos de prova de uma vez, com identificação derivada. */
    public static function moldar(int $cargaNumero, IdadeDeEnsaio $idade, DateTimeImmutable $momento): self
    {
        $prefixo = sprintf('C%d-%dd', $cargaNumero, $idade->dias());

        return new self(
            $cargaNumero,
            $idade,
            $momento,
            new CorpoDeProva("{$prefixo}-A", $momento, $idade),
            new CorpoDeProva("{$prefixo}-B", $momento, $idade),
        );
    }

    /** Recompõe um exemplar vindo do banco, com os corpos de prova já montados. */
    public static function reconstituir(
        int $cargaNumero,
        IdadeDeEnsaio $idade,
        DateTimeImmutable $moldadoEm,
        CorpoDeProva $primeiro,
        CorpoDeProva $segundo,
    ): self {
        return new self($cargaNumero, $idade, $moldadoEm, $primeiro, $segundo);
    }

    public function identificacao(): string
    {
        return sprintf('Carga %d · %s', $this->cargaNumero, $this->idade->rotulo());
    }

    /** @return CorpoDeProva[] */
    public function corposDeProva(): array
    {
        return [$this->primeiro, $this->segundo];
    }

    public function ehDeAceitacao(): bool
    {
        return $this->idade->ehDeAceitacao();
    }

    public function rompimentoPrevisto(): DateTimeImmutable
    {
        return $this->primeiro->rompimentoPrevisto();
    }

    public function inicioDaJanela(): DateTimeImmutable
    {
        return $this->primeiro->inicioDaJanela();
    }

    public function fimDaJanela(): DateTimeImmutable
    {
        return $this->primeiro->fimDaJanela();
    }

    /** Algum dos dois passou da janela sem ser rompido. */
    public function temCorpoDeProvaVencido(DateTimeImmutable $agora): bool
    {
        return $this->primeiro->estaVencido($agora) || $this->segundo->estaVencido($agora);
    }

    /** Os dois ainda esperam a prensa. */
    public function aguardaRompimento(): bool
    {
        return $this->primeiro->situacao()->aguardaRompimento()
            || $this->segundo->situacao()->aguardaRompimento();
    }
}
