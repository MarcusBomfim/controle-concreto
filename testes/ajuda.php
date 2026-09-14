<?php

declare(strict_types=1);

use ControleConcreto\Aplicacao\FormarLote;
use ControleConcreto\Aplicacao\JulgarLote;
use ControleConcreto\Dominio\Concretagem\Carga;
use ControleConcreto\Dominio\Concretagem\Concretagem;
use ControleConcreto\Dominio\Concreto\Abatimento;
use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\Ensaio\DiametroDoCorpoDeProva;
use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;
use ControleConcreto\Dominio\Ensaio\ResultadoDeEnsaio;
use ControleConcreto\Dominio\Estrutura\ElementoEstrutural;
use ControleConcreto\Dominio\Estrutura\GrupoDeSolicitacao;
use ControleConcreto\Dominio\Estrutura\TipoDeElemento;
use ControleConcreto\Dominio\Lote\CondicaoDePreparo;
use ControleConcreto\Dominio\Lote\Lote;
use ControleConcreto\Dominio\Lote\TipoDeAmostragem;
use ControleConcreto\Dominio\Obra\Obra;
use ControleConcreto\Infraestrutura\Banco\Conexao;
use ControleConcreto\Infraestrutura\Banco\Migrador;
use ControleConcreto\Infraestrutura\Repositorio\AgendaDoLaboratorioEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeConcretagensEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeElementosEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeLotesEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeObrasEmSqlite;

/*
 * Fábricas compartilhadas entre os arquivos de teste. Ficam aqui, carregadas
 * antes de tudo, porque o executor inclui os testes em ordem alfabética — e
 * uma função definida em EstruturaTest.php não existe ainda quando
 * ConcretagemTest.php roda.
 */

/** Laje C30, abatimento 100 ± 20 mm, 42 m³. */
function lajeDeTeste(float $volume = 42.0): ElementoEstrutural
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
function pilaresDeTeste(float $volume = 30.0): ElementoEstrutural
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

/** Concretagem da laje de teste em 10/03/2026, com "hoje" fixado no mesmo dia. */
function concretagemDeTeste(): Concretagem
{
    return new Concretagem(
        'obr-2026-007',
        lajeDeTeste(),
        new DateTimeImmutable('2026-03-10'),
        'Concreteira Litoral',
        'Marcus Bomfim',
        new DateTimeImmutable('2026-03-10'),
    );
}

/** Um instante qualquer, para os testes de janela. */
function momento(string $dataHora): DateTimeImmutable
{
    return new DateTimeImmutable($dataHora);
}

/** Um horário no dia da concretagem de teste. */
function hora(string $horario): DateTimeImmutable
{
    return new DateTimeImmutable("2026-03-10 {$horario}");
}

/** Carga típica: saiu 8h, chegou 8h50, abatimento dentro da faixa de 100 ± 20. */
function chegaCarga(
    Concretagem $concretagem,
    int $abatimento = 100,
    string $saida = '08:00',
    string $chegada = '08:50',
    float $volume = 8.0,
    string $notaFiscal = 'NF-1001',
): Carga {
    return $concretagem->receberCarga($notaFiscal, 'ABC-1D23', $volume, hora($saida), hora($chegada), $abatimento);
}

/** Molda o par padrão de exemplares — 7 e 28 dias — da carga, às 9h. */
function moldaPadrao(Concretagem $concretagem, int $cargaNumero = 1): array
{
    return $concretagem->moldar(
        $cargaNumero,
        hora('09:00'),
        [IdadeDeEnsaio::SeteDias, IdadeDeEnsaio::VinteEOitoDias],
    );
}

/** SQLite em memória com as migrations reais aplicadas. */
function bancoDeTeste(): PDO
{
    $conexao = Conexao::emMemoria();
    Migrador::padrao($conexao)->aplicar();

    return $conexao;
}

function obraDeTeste(): Obra
{
    return new Obra('OBR-2026-007', 'Edifício Vista Serra', 'Construtora Vale Verde', 'Marcus Bomfim', 'CREA-SP 123456/D');
}

/**
 * Banco com a obra e a laje de teste já gravadas, e os repositórios montados.
 *
 * @return array{
 *     conexao: PDO,
 *     obras: RepositorioDeObrasEmSqlite,
 *     elementos: RepositorioDeElementosEmSqlite,
 *     concretagens: RepositorioDeConcretagensEmSqlite,
 *     agenda: AgendaDoLaboratorioEmSqlite
 * }
 */
function ambienteComObra(): array
{
    $conexao = bancoDeTeste();

    $obras = new RepositorioDeObrasEmSqlite($conexao);
    $elementos = new RepositorioDeElementosEmSqlite($conexao);

    $obras->salvar(obraDeTeste());
    $elementos->salvar('OBR-2026-007', lajeDeTeste());
    $elementos->salvar('OBR-2026-007', pilaresDeTeste());

    $concretagens = new RepositorioDeConcretagensEmSqlite($conexao);
    $lotes = new RepositorioDeLotesEmSqlite($conexao, $concretagens);

    return [
        'conexao' => $conexao,
        'obras' => $obras,
        'elementos' => $elementos,
        'concretagens' => $concretagens,
        'agenda' => new AgendaDoLaboratorioEmSqlite($conexao),
        'lotes' => $lotes,
        'formarLote' => new FormarLote($conexao, $concretagens, $lotes),
        'julgarLote' => new JulgarLote($lotes),
    ];
}

/**
 * Concretagem concluída e numerada, com uma carga de 8 m³ por resistência
 * pedida, cada carga com exemplar de 28 dias rompido nos dois corpos de prova
 * no valor dado. Nulo deixa o exemplar aguardando.
 *
 * @param array<int, ?float> $resistenciasEmMPa
 */
function concretagemComResultados(
    int $numero,
    array $resistenciasEmMPa,
    ?ElementoEstrutural $elemento = null,
    string $data = '2026-03-10',
): Concretagem {
    $concretagem = new Concretagem(
        'OBR-2026-007',
        $elemento ?? lajeDeTeste(),
        new DateTimeImmutable($data),
        'Usina',
        'Marcus',
        new DateTimeImmutable($data),
    );
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

        [$exemplar] = $concretagem->moldar($carga->numero, new DateTimeImmutable("{$data} 09:00"), [IdadeDeEnsaio::VinteEOitoDias]);

        if ($mpa === null) {
            continue;
        }

        // kN que dá exatamente esse MPa num cilindro de 10 cm.
        $kN = $mpa * DiametroDoCorpoDeProva::DezCentimetros->areaEmMm2() / 1000;
        $rompidoEm = (new DateTimeImmutable("{$data} 09:00"))->modify('+28 days');

        foreach ($exemplar->corposDeProva() as $cp) {
            $cp->romper(new ResultadoDeEnsaio($kN, DiametroDoCorpoDeProva::DezCentimetros, $rompidoEm), $rompidoEm);
        }
    }

    $concretagem->concluir();

    return $concretagem;
}

function loteC30(TipoDeAmostragem $amostragem = TipoDeAmostragem::Parcial): Lote
{
    return new Lote('OBR-2026-007', ClasseDeResistencia::C30, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, $amostragem);
}
