<?php

declare(strict_types=1);

namespace Tests\Apoio;

use App\Dominio\Concretagem\Carga;
use App\Dominio\Concretagem\Concretagem;
use App\Dominio\Concreto\Abatimento;
use App\Dominio\Concreto\ClasseDeResistencia;
use App\Dominio\Ensaio\DiametroDoCorpoDeProva;
use App\Dominio\Ensaio\IdadeDeEnsaio;
use App\Dominio\Ensaio\ResultadoDeEnsaio;
use App\Dominio\Estrutura\ElementoEstrutural;
use App\Dominio\Estrutura\GrupoDeSolicitacao;
use App\Dominio\Estrutura\TipoDeElemento;
use App\Dominio\Lote\CondicaoDePreparo;
use App\Dominio\Lote\Lote;
use App\Dominio\Lote\TipoDeAmostragem;
use App\Dominio\Obra\Obra;
use DateTimeImmutable;

/**
 * As peças que os testes montam o tempo todo.
 *
 * Trait, e não funções globais: no PHPUnit cada classe de teste é autônoma,
 * e o que ela precisa chega por composição. No executor caseiro isso era um
 * arquivo carregado antes de tudo, e a ordem alfabética dos arquivos já
 * causou problema mais de uma vez.
 */
trait ObjetosDeExemplo
{
    /** Um instante qualquer, para os testes de janela de rompimento. */
    protected function momento(string $dataHora): DateTimeImmutable
    {
        return new DateTimeImmutable($dataHora);
    }

    /** Um horário no dia da concretagem de teste (10/03/2026). */
    protected function hora(string $horario): DateTimeImmutable
    {
        return new DateTimeImmutable("2026-03-10 {$horario}");
    }

    /** Laje C30, abatimento 100 ± 20 mm, 42 m³. */
    protected function lajeDeTeste(float $volume = 42.0): ElementoEstrutural
    {
        return new ElementoEstrutural(
            'l3-p4',
            TipoDeElemento::Laje,
            'Laje L3',
            '4º pavimento',
            ClasseDeResistencia::C30,
            new Abatimento(100),
            $volume,
        );
    }

    /** Pilares C35, abatimento 120 ± 20 mm — lote de 50 m³. */
    protected function pilaresDeTeste(float $volume = 30.0): ElementoEstrutural
    {
        return new ElementoEstrutural(
            'P-T',
            TipoDeElemento::Pilar,
            'Pilares do térreo',
            'Térreo',
            ClasseDeResistencia::C35,
            new Abatimento(120),
            $volume,
        );
    }

    protected function obraDeTeste(): Obra
    {
        return new Obra(
            'OBR-2026-007',
            'Edifício Vista Serra',
            'Construtora Vale Verde',
            'Marcus Bomfim',
            'CREA-SP 123456/D',
        );
    }

    /** Concretagem da laje em 10/03/2026, com "hoje" fixado no mesmo dia. */
    protected function concretagemDeTeste(): Concretagem
    {
        return new Concretagem(
            'obr-2026-007',
            $this->lajeDeTeste(),
            new DateTimeImmutable('2026-03-10'),
            'Concreteira Litoral',
            'Marcus Bomfim',
            new DateTimeImmutable('2026-03-10'),
        );
    }

    /** Carga típica: saiu 8h, chegou 8h50, abatimento dentro da faixa de 100 ± 20. */
    protected function chegaCarga(
        Concretagem $concretagem,
        int $abatimento = 100,
        string $saida = '08:00',
        string $chegada = '08:50',
        float $volume = 8.0,
        string $notaFiscal = 'NF-1001',
    ): Carga {
        return $concretagem->receberCarga(
            $notaFiscal,
            'ABC-1D23',
            $volume,
            $this->hora($saida),
            $this->hora($chegada),
            $abatimento,
        );
    }

    /**
     * Molda o par padrão de exemplares — 7 e 28 dias — da carga, às 9h.
     *
     * @return \App\Dominio\Ensaio\Exemplar[]
     */
    protected function moldaPadrao(Concretagem $concretagem, int $cargaNumero = 1): array
    {
        return $concretagem->moldar(
            $cargaNumero,
            $this->hora('09:00'),
            [IdadeDeEnsaio::SeteDias, IdadeDeEnsaio::VinteEOitoDias],
        );
    }

    protected function loteC30(TipoDeAmostragem $amostragem = TipoDeAmostragem::Parcial): Lote
    {
        return new Lote(
            'OBR-2026-007',
            ClasseDeResistencia::C30,
            GrupoDeSolicitacao::Horizontal,
            CondicaoDePreparo::A,
            $amostragem,
        );
    }

    /**
     * Concretagem concluída, com uma carga de 8 m³ por resistência pedida.
     * Cada carga tem exemplar de 28 dias rompido nos dois corpos de prova no
     * valor dado; nulo deixa o exemplar aguardando a prensa.
     *
     * @param array<int, ?float> $resistenciasEmMPa
     */
    protected function concretagemComResultados(
        int $numero,
        array $resistenciasEmMPa,
        ?ElementoEstrutural $elemento = null,
        string $data = '2026-03-10',
    ): Concretagem {
        $concretagem = new Concretagem(
            'OBR-2026-007',
            $elemento ?? $this->lajeDeTeste(),
            new DateTimeImmutable($data),
            'Usina',
            'Marcus',
            new DateTimeImmutable($data),
        );

        // Zero significa "deixe o repositório numerar": definirNumero recusaria.
        if ($numero > 0) {
            $concretagem->definirNumero($numero);
        }

        foreach ($resistenciasEmMPa as $indice => $mpa) {
            $carga = $concretagem->receberCarga(
                "NF-{$numero}-{$indice}",
                null,
                8.0,
                new DateTimeImmutable("{$data} 08:00"),
                new DateTimeImmutable("{$data} 08:40"),
                $elemento?->abatimento->especificadoEmMm ?? 100,
            );

            [$exemplar] = $concretagem->moldar(
                $carga->numero,
                new DateTimeImmutable("{$data} 09:00"),
                [IdadeDeEnsaio::VinteEOitoDias],
            );

            if ($mpa === null) {
                continue;
            }

            // A carga em kN que dá exatamente esse MPa num cilindro de 10 cm.
            $kN = $mpa * DiametroDoCorpoDeProva::DezCentimetros->areaEmMm2() / 1000;
            $rompidoEm = (new DateTimeImmutable("{$data} 09:00"))->modify('+28 days');

            foreach ($exemplar->corposDeProva() as $corpoDeProva) {
                $corpoDeProva->romper(
                    new ResultadoDeEnsaio($kN, DiametroDoCorpoDeProva::DezCentimetros, $rompidoEm),
                    $rompidoEm,
                );
            }
        }

        $concretagem->concluir();

        return $concretagem;
    }
}
