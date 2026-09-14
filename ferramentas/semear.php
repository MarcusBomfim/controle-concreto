<?php

declare(strict_types=1);

/*
 * Carrega dados de demonstração.
 *
 *   php ferramentas/semear.php
 *
 * A carga é feita em PHP, pelo domínio e pelos repositórios, e não em SQL:
 * as datas são relativas a hoje, para a agenda do laboratório mostrar corpos
 * de prova em todos os estados — alguns vencidos, alguns na janela, alguns
 * para daqui a semanas. Um SQL com datas fixas envelheceria em uma semana.
 *
 * Idempotente: se a obra de demonstração já existe, não faz nada.
 */

require __DIR__ . '/../src/autoload.php';

use ControleConcreto\Aplicacao\RegistrarRompimento;
use ControleConcreto\Dominio\Concretagem\Concretagem;
use ControleConcreto\Dominio\Concreto\Abatimento;
use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;
use ControleConcreto\Dominio\Estrutura\ElementoEstrutural;
use ControleConcreto\Dominio\Estrutura\TipoDeElemento;
use ControleConcreto\Dominio\Obra\Obra;
use ControleConcreto\Infraestrutura\Banco\Conexao;
use ControleConcreto\Infraestrutura\Banco\Migrador;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeConcretagensEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeElementosEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeObrasEmSqlite;

$conexao = Conexao::abrir();

if (Migrador::padrao($conexao)->pendentes() !== []) {
    fwrite(STDERR, 'Há migrations pendentes. Rode php ferramentas/migrar.php antes.' . PHP_EOL);
    exit(1);
}

$obras = new RepositorioDeObrasEmSqlite($conexao);
$elementos = new RepositorioDeElementosEmSqlite($conexao);
$concretagens = new RepositorioDeConcretagensEmSqlite($conexao);

const OBRA = 'OBR-2026-007';

if ($obras->existe(OBRA)) {
    echo 'A obra de demonstração já existe. Nada a fazer.', PHP_EOL;
    exit(0);
}

$obras->salvar(new Obra(
    OBRA,
    'Edifício residencial Vista Serra',
    'Construtora Vale Verde Ltda.',
    'Marcus Bomfim',
    'CREA-SP 5069874521/D',
));

$sapatas = new ElementoEstrutural('SAP-B1', TipoDeElemento::Fundacao, 'Sapatas do bloco 1', null, ClasseDeResistencia::C25, new Abatimento(80), 18.0);
$pilares = new ElementoEstrutural('P-TER', TipoDeElemento::Pilar, 'Pilares do térreo', 'Térreo', ClasseDeResistencia::C35, new Abatimento(120), 30.0);
$laje = new ElementoEstrutural('L3-P4', TipoDeElemento::Laje, 'Laje L3', '4º pavimento', ClasseDeResistencia::C30, new Abatimento(100), 42.0);

foreach ([$sapatas, $pilares, $laje] as $elemento) {
    $elementos->salvar(OBRA, $elemento);
}

/** Monta um instante num dia relativo a hoje. */
function diasAtras(int $dias, string $hora): DateTimeImmutable
{
    return (new DateTimeImmutable("today -{$dias} days"))->modify($hora);
}

/**
 * Concretagem completa: recebe as cargas, molda 7 e 28 dias das aceitas,
 * conclui e grava.
 *
 * @param array<int, array{nf: string, volume: float, saida: string, chegada: string, abatimento: int}> $cargas
 */
function concretar(
    RepositorioDeConcretagensEmSqlite $repositorio,
    ElementoEstrutural $elemento,
    int $diasAtras,
    string $fornecedor,
    array $cargas,
    bool $concluir = true,
): int {
    $concretagem = new Concretagem(OBRA, $elemento, diasAtras($diasAtras, '00:00'), $fornecedor, 'Marcus Bomfim');

    foreach ($cargas as $dados) {
        $carga = $concretagem->receberCarga(
            $dados['nf'],
            null,
            $dados['volume'],
            diasAtras($diasAtras, $dados['saida']),
            diasAtras($diasAtras, $dados['chegada']),
            $dados['abatimento'],
        );

        if ($carga->foiAceita()) {
            $concretagem->moldar(
                $carga->numero,
                $carga->chegada->modify('+10 minutes'),
                [IdadeDeEnsaio::SeteDias, IdadeDeEnsaio::VinteEOitoDias],
            );
        }
    }

    if ($concluir) {
        $concretagem->concluir();
    }

    return $repositorio->salvar($concretagem);
}

// 27 dias atrás: os de 28 dias rompem amanhã. Dos de 7 dias, as cargas 1 e 2
// foram rompidas no dia certo, com resultado histórico; a carga 3 ficou para
// trás e venceu — de propósito, para a agenda mostrar o alerta.
$numeroSapatas = concretar($concretagens, $sapatas, 27, 'Concreteira Litoral', [
    ['nf' => 'NF-48211', 'volume' => 8.0, 'saida' => '07:10', 'chegada' => '07:55', 'abatimento' => 80],
    ['nf' => 'NF-48212', 'volume' => 8.0, 'saida' => '07:40', 'chegada' => '08:30', 'abatimento' => 90],
    ['nf' => 'NF-48213', 'volume' => 2.0, 'saida' => '08:20', 'chegada' => '09:05', 'abatimento' => 75],
]);

/*
 * Resultados históricos aos 7 dias: sapatas C25 rendendo uns 70% do fck na
 * primeira semana, que é o comportamento típico. A data de rompimento é
 * 7 dias depois da moldagem, dentro da janela — o domínio confere isso.
 */
$registrar = new RegistrarRompimento($concretagens);

foreach ([['C1-7d-A', 148.0], ['C1-7d-B', 141.5], ['C2-7d-A', 152.0], ['C2-7d-B', 155.5]] as [$cp, $cargaKN]) {
    $registrar->executar(OBRA, $numeroSapatas, $cp, $cargaKN, 100, diasAtras(20, '09:40'));
}

// 5 dias atrás: os de 7 dias rompem em dois dias. Uma carga devolvida por abatimento.
concretar($concretagens, $pilares, 5, 'Concreteira Litoral', [
    ['nf' => 'NF-49330', 'volume' => 8.0, 'saida' => '13:00', 'chegada' => '13:50', 'abatimento' => 120],
    ['nf' => 'NF-49331', 'volume' => 8.0, 'saida' => '13:30', 'chegada' => '14:25', 'abatimento' => 150],
    ['nf' => 'NF-49332', 'volume' => 8.0, 'saida' => '14:00', 'chegada' => '14:50', 'abatimento' => 115],
    ['nf' => 'NF-49333', 'volume' => 6.0, 'saida' => '14:40', 'chegada' => '15:30', 'abatimento' => 125],
]);

// Hoje: concretagem em andamento, duas cargas recebidas até agora.
concretar($concretagens, $laje, 0, 'Concreteira Litoral', [
    ['nf' => 'NF-50017', 'volume' => 8.0, 'saida' => '06:30', 'chegada' => '07:15', 'abatimento' => 100],
    ['nf' => 'NF-50018', 'volume' => 8.0, 'saida' => '07:00', 'chegada' => '07:50', 'abatimento' => 105],
], concluir: false);

$totalCp = (int) $conexao->query('SELECT COUNT(*) FROM corpos_de_prova')->fetchColumn();

printf(
    'Banco carregado: 1 obra, 3 elementos, 3 concretagens, %d corpos de prova e 4 resultados.%s',
    $totalCp,
    PHP_EOL,
);
