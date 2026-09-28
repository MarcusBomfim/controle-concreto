<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Aplicacao\OperacoesDeConcretagem;
use App\Dominio\Concreto\Abatimento;
use App\Dominio\Concreto\ClasseDeResistencia;
use App\Dominio\Concretagem\Concretagem;
use App\Dominio\Concretagem\RepositorioDeConcretagens;
use App\Dominio\Ensaio\DiametroDoCorpoDeProva;
use App\Dominio\Ensaio\IdadeDeEnsaio;
use App\Dominio\Ensaio\ResultadoDeEnsaio;
use App\Dominio\Estrutura\ElementoEstrutural;
use App\Dominio\Estrutura\RepositorioDeElementos;
use App\Dominio\Estrutura\TipoDeElemento;
use App\Dominio\Obra\Obra;
use App\Dominio\Obra\RepositorioDeObras;
use DateTimeImmutable;
use Illuminate\Database\Seeder;

/**
 * Dados de demonstração, carregados pelo domínio.
 *
 * Nada de INSERT direto: a obra, os elementos e cada caminhão passam pelas
 * mesmas regras que a tela aplica. Um seeder que grava por SQL consegue criar
 * estado que o sistema jamais produziria — carga devolvida com corpo de prova,
 * concretagem concluída sem exemplar de 28 dias — e aí a tela mostra algo
 * impossível.
 *
 * As datas são relativas a hoje, não fixas. A agenda do laboratório só faz
 * sentido contra o relógio: um seeder com datas de 2026 mostraria a tela
 * vazia amanhã.
 */
final class DatabaseSeeder extends Seeder
{
    private const OBRA = 'OBR-2026-007';

    /** Minutos desde a meia-noite usados como "agora" das concretagens recentes. */
    private int $minutoBase = 0;

    public function run(): void
    {
        $obras = app(RepositorioDeObras::class);

        if ($obras->existe(self::OBRA)) {
            $this->command?->info('A obra de demonstração já existe. Nada a fazer.');

            return;
        }

        $obras->salvar(new Obra(
            self::OBRA,
            'Edifício residencial Vista Serra',
            'Construtora Vale Verde Ltda.',
            'Marcus Bomfim',
            'CREA-SP 5069874521/D',
        ));

        $this->carregarElementos();
        $this->carregarConcretagens();
    }

    private function carregarElementos(): void
    {
        $elementos = app(RepositorioDeElementos::class);

        $pecas = [
            new ElementoEstrutural('SAP-B1', TipoDeElemento::Fundacao, 'Sapatas do bloco 1', null, ClasseDeResistencia::C25, new Abatimento(80), 18.0),
            new ElementoEstrutural('BLC-B1', TipoDeElemento::Fundacao, 'Blocos de coroamento do bloco 1', null, ClasseDeResistencia::C25, new Abatimento(80), 10.0),
            new ElementoEstrutural('P-TER', TipoDeElemento::Pilar, 'Pilares do térreo', 'Térreo', ClasseDeResistencia::C35, new Abatimento(120), 30.0),
            new ElementoEstrutural('VIG-B1', TipoDeElemento::Viga, 'Vigas baldrame do bloco 1', null, ClasseDeResistencia::C25, new Abatimento(80), 12.0),
            new ElementoEstrutural('L3-P4', TipoDeElemento::Laje, 'Laje L3', '4º pavimento', ClasseDeResistencia::C30, new Abatimento(100), 42.0),
        ];

        foreach ($pecas as $peca) {
            $elementos->salvar(self::OBRA, $peca);
        }

        $this->command?->info(sprintf('Carregado: 1 obra e %d elementos.', count($pecas)));
    }

    /**
     * Quatro concretagens, escolhidas para que a agenda mostre cada estado:
     * exemplar vencido, exemplar na janela agora, rompimento previsto para os
     * próximos dias, e uma concretagem aberta esperando caminhão.
     */
    private function carregarConcretagens(): void
    {
        $agora = new DateTimeImmutable('now');

        /*
         * A hora de referência é a de agora, presa entre 06:00 e 23:00. O
         * motivo é o domínio: a carga e a moldagem têm que cair no mesmo dia
         * da concretagem, e a moldagem tem que vir depois da chegada. Rodar
         * o seeder às 00:10 empurraria a chegada para o dia anterior.
         *
         * A folga que a prisão introduz — no máximo 6 h — cabe dentro da
         * tolerância de 6 h da idade de 7 dias, que é o que sustenta o item
         * "na janela agora".
         */
        $minutos = ((int) $agora->format('G')) * 60 + (int) $agora->format('i');
        $this->minutoBase = max(6 * 60, min(23 * 60, $minutos));

        $this->lajeComExemplaresA28Dias();
        $this->pilaresComExemplarVencido();
        $this->vigaComExemplarNaJanela();
        $this->sapataEmAndamento();

        $this->command?->info('Carregado: 4 concretagens, com cargas, corpos de prova e resultados.');
    }

    /** Laje de 25 dias atrás: 7 dias já rompidos, 28 dias chegando em 3 dias. */
    private function lajeComExemplaresA28Dias(): void
    {
        $concretagem = $this->abrir('L3-P4', 25, 'Concreteira Litoral');

        $concretagem->receberCarga('NF-88410', 'BRA-2E19', 8.0, $this->as(25, '07:10'), $this->as(25, '07:50'), 100, 'Bomba lança');

        // Abatimento de 150 mm numa peça de 100 ± 20: o cone reprova, e o
        // caminhão volta para a usina. O registro fica.
        $concretagem->receberCarga('NF-88411', 'BRA-2E20', 8.0, $this->as(25, '09:00'), $this->as(25, '09:45'), 150, 'Água na obra — recusado');

        $concretagem->receberCarga('NF-88412', 'BRA-2E21', 8.0, $this->as(25, '10:00'), $this->as(25, '10:40'), 95, null);

        $concretagem->moldar(1, $this->as(25, '08:00'), [IdadeDeEnsaio::SeteDias, IdadeDeEnsaio::VinteEOitoDias]);
        $concretagem->moldar(3, $this->as(25, '11:00'), [IdadeDeEnsaio::VinteEOitoDias]);

        // Os de 7 dias já foram rompidos na época, dentro da janela.
        $this->romper($concretagem, IdadeDeEnsaio::SeteDias, [24.8, 25.6]);

        $concretagem->concluir();

        $this->gravar($concretagem);
    }

    /** Pilares de 10 dias atrás: os de 7 dias passaram da janela sem romper. */
    private function pilaresComExemplarVencido(): void
    {
        $concretagem = $this->abrir('P-TER', 10, 'Usina Baixada');

        $concretagem->receberCarga('NF-90233', 'BRA-7J55', 7.0, $this->as(10, '07:30'), $this->as(10, '08:05'), 125, null);
        $concretagem->moldar(1, $this->as(10, '08:30'), [IdadeDeEnsaio::SeteDias, IdadeDeEnsaio::VinteEOitoDias]);
        $concretagem->concluir();

        $this->gravar($concretagem);
    }

    /** Viga de 7 dias atrás, moldada na hora de agora: o exemplar rompe neste instante. */
    private function vigaComExemplarNaJanela(): void
    {
        $concretagem = $this->abrir('VIG-B1', 7, 'Concreteira Litoral');

        $concretagem->receberCarga('NF-91007', 'BRA-4K02', 6.0, $this->emMinutos(7, -70), $this->emMinutos(7, -30), 80, null);
        $concretagem->moldar(1, $this->emMinutos(7, 0), [IdadeDeEnsaio::SeteDias, IdadeDeEnsaio::VinteEOitoDias]);
        $concretagem->concluir();

        $this->gravar($concretagem);
    }

    /** Concretagem de hoje, ainda em andamento: a tela mostra os formulários. */
    private function sapataEmAndamento(): void
    {
        $concretagem = $this->abrir('SAP-B1', 0, 'Usina Baixada');

        $concretagem->receberCarga('NF-91120', 'BRA-9L71', 9.0, $this->emMinutos(0, -70), $this->emMinutos(0, -30), 85, null);

        $this->gravar($concretagem);
    }

    private function abrir(string $elemento, int $diasAtras, string $fornecedor): Concretagem
    {
        return app(OperacoesDeConcretagem::class)->abrir(
            self::OBRA,
            $elemento,
            $this->dia($diasAtras),
            $fornecedor,
            'Marcus Bomfim',
        );
    }

    /**
     * Rompe os dois cilindros de cada exemplar da idade dada, no momento
     * exato do rompimento previsto — dentro da janela, portanto.
     *
     * @param list<float> $resistenciasEmMPa uma por exemplar
     */
    private function romper(Concretagem $concretagem, IdadeDeEnsaio $idade, array $resistenciasEmMPa): void
    {
        $exemplares = array_values(array_filter(
            $concretagem->exemplares(),
            static fn ($exemplar): bool => $exemplar->idade === $idade,
        ));

        foreach ($exemplares as $indice => $exemplar) {
            $mpa = $resistenciasEmMPa[$indice] ?? null;

            if ($mpa === null) {
                continue;
            }

            $rompidoEm = $exemplar->rompimentoPrevisto();

            foreach ($exemplar->corposDeProva() as $posicao => $corpoDeProva) {
                // Meio MPa de diferença entre os dois cilindros do exemplar:
                // é o que a prensa dá na vida real, e o exemplar fica com o maior.
                $resistencia = $mpa + ($posicao === 0 ? 0.0 : 0.5);

                $corpoDeProva->romper(
                    new ResultadoDeEnsaio(
                        $this->cargaEmKN($resistencia),
                        DiametroDoCorpoDeProva::DezCentimetros,
                        $rompidoEm,
                    ),
                    $rompidoEm,
                );
            }
        }
    }

    /** A força em kN que a prensa marcaria para dar essa resistência num cilindro de 10 cm. */
    private function cargaEmKN(float $mpa): float
    {
        return round($mpa * DiametroDoCorpoDeProva::DezCentimetros->areaEmMm2() / 1000, 1);
    }

    private function gravar(Concretagem $concretagem): void
    {
        app(RepositorioDeConcretagens::class)->salvar($concretagem);
    }

    private function dia(int $diasAtras): DateTimeImmutable
    {
        return (new DateTimeImmutable('today'))->modify(sprintf('-%d days', $diasAtras));
    }

    /** Um horário fixo num dia passado: "há 25 dias, às 07:10". */
    private function as(int $diasAtras, string $hora): DateTimeImmutable
    {
        return new DateTimeImmutable($this->dia($diasAtras)->format('Y-m-d') . ' ' . $hora);
    }

    /** Um momento relativo à hora de referência: "há 7 dias, 30 min antes de agora". */
    private function emMinutos(int $diasAtras, int $ajuste): DateTimeImmutable
    {
        $minutos = $this->minutoBase + $ajuste;

        return $this->dia($diasAtras)->setTime(intdiv($minutos, 60), $minutos % 60);
    }
}
