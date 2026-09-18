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

use ControleConcreto\Aplicacao\FormarLote;
use ControleConcreto\Aplicacao\JulgarLote;
use ControleConcreto\Aplicacao\RegistrarRompimento;
use ControleConcreto\Aplicacao\TratarNaoConformidade;
use ControleConcreto\Dominio\Concretagem\Concretagem;
use ControleConcreto\Dominio\Concreto\Abatimento;
use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;
use ControleConcreto\Dominio\Estrutura\ElementoEstrutural;
use ControleConcreto\Dominio\Estrutura\GrupoDeSolicitacao;
use ControleConcreto\Dominio\Estrutura\TipoDeElemento;
use ControleConcreto\Dominio\Lote\CondicaoDePreparo;
use ControleConcreto\Dominio\Lote\TipoDeAmostragem;
use ControleConcreto\Dominio\NaoConformidade\Providencia;
use ControleConcreto\Dominio\NaoConformidade\ResultadoDaProvidencia;
use ControleConcreto\Dominio\NaoConformidade\TipoDeProvidencia;
use ControleConcreto\Dominio\Obra\Obra;
use ControleConcreto\Dominio\Usuario\Papel;
use ControleConcreto\Dominio\Usuario\Usuario;
use ControleConcreto\Infraestrutura\Banco\Conexao;
use ControleConcreto\Infraestrutura\Banco\Migrador;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeConcretagensEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeElementosEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeLotesEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeNaoConformidadesEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeObrasEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeUsuariosEmSqlite;

$conexao = Conexao::abrir();

if (Migrador::padrao($conexao)->pendentes() !== []) {
    fwrite(STDERR, 'Há migrations pendentes. Rode php ferramentas/migrar.php antes.' . PHP_EOL);
    exit(1);
}

$obras = new RepositorioDeObrasEmSqlite($conexao);
$elementos = new RepositorioDeElementosEmSqlite($conexao);
$concretagens = new RepositorioDeConcretagensEmSqlite($conexao);
$lotes = new RepositorioDeLotesEmSqlite($conexao, $concretagens);
$naoConformidades = new RepositorioDeNaoConformidadesEmSqlite($conexao);
$usuarios = new RepositorioDeUsuariosEmSqlite($conexao);

const OBRA = 'OBR-2026-007';

/*
 * As contas ficam fora de qualquer SQL de propósito: o hash da senha precisa
 * ser gerado pelo password_hash() do PHP, com sal aleatório. Conta que já
 * existe não é sobrescrita — se você trocou a senha, ela fica.
 */
$contasNovas = 0;

foreach ([
    ['engenheiro@concreto.dev', 'Marcus Bomfim', Papel::Engenheiro, 'Engenheiro@123'],
    ['laboratorio@concreto.dev', 'Helena Duarte', Papel::Laboratorista, 'Laboratorio@123'],
    ['gestor@concreto.dev', 'Rafael Nunes', Papel::Gestor, 'Gestor@123'],
] as [$email, $nome, $papel, $senha]) {
    if (!$usuarios->existe($email)) {
        $usuarios->salvar(Usuario::criar($email, $nome, $papel, $senha));
        $contasNovas++;
    }
}

if ($obras->existe(OBRA)) {
    printf('A obra de demonstração já existe; %d conta(s) nova(s). Nada mais a fazer.%s', $contasNovas, PHP_EOL);
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
$vigas = new ElementoEstrutural('VIG-B1', TipoDeElemento::Viga, 'Vigas baldrame do bloco 1', null, ClasseDeResistencia::C25, new Abatimento(80), 12.0);

foreach ([$sapatas, $pilares, $laje, $vigas] as $elemento) {
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

/*
 * 28 dias atrás, no fim da tarde: os exemplares de 28 dias estão na janela
 * AGORA — é o que a agenda existe para mostrar, com o formulário de resultado
 * na própria linha. Os de 7 dias foram rompidos na hora certa.
 */
$numeroVigas = concretar($concretagens, $vigas, 28, 'Concreteira Litoral', [
    ['nf' => 'NF-48190', 'volume' => 7.0, 'saida' => '15:40', 'chegada' => '16:30', 'abatimento' => 85],
    ['nf' => 'NF-48191', 'volume' => 5.0, 'saida' => '16:20', 'chegada' => '17:15', 'abatimento' => 80],
]);

foreach ([['C1-7d-A', 150.5], ['C1-7d-B', 146.0], ['C2-7d-A', 158.0], ['C2-7d-B', 153.5]] as [$cp, $cargaKN]) {
    $registrar->executar(OBRA, $numeroVigas, $cp, $cargaKN, 100, diasAtras(21, '17:00'));
}

// 5 dias atrás: os de 7 dias rompem em dois dias. Uma carga devolvida por abatimento.
concretar($concretagens, $pilares, 5, 'Concreteira Litoral', [
    ['nf' => 'NF-49330', 'volume' => 8.0, 'saida' => '13:00', 'chegada' => '13:50', 'abatimento' => 120],
    ['nf' => 'NF-49331', 'volume' => 8.0, 'saida' => '13:30', 'chegada' => '14:25', 'abatimento' => 150],
    ['nf' => 'NF-49332', 'volume' => 8.0, 'saida' => '14:00', 'chegada' => '14:50', 'abatimento' => 115],
    ['nf' => 'NF-49333', 'volume' => 6.0, 'saida' => '14:40', 'chegada' => '15:30', 'abatimento' => 125],
]);

/*
 * 40 dias atrás: blocos de coroamento C25 cujos exemplares de 28 dias já
 * romperam — baixos. O lote foi julgado, reprovou por 3,1 MPa, e a não
 * conformidade está aberta com o primeiro passo dado. É a tela que mostra
 * o que acontece quando o concreto não passa.
 */
$blocos = new ElementoEstrutural('BLC-B1', TipoDeElemento::Fundacao, 'Blocos de coroamento do bloco 1', null, ClasseDeResistencia::C25, new Abatimento(80), 10.0);
$elementos->salvar(OBRA, $blocos);

$numeroBlocos = concretar($concretagens, $blocos, 40, 'Concreteira Litoral', [
    ['nf' => 'NF-47902', 'volume' => 4.0, 'saida' => '08:00', 'chegada' => '08:45', 'abatimento' => 80],
    ['nf' => 'NF-47903', 'volume' => 3.0, 'saida' => '08:30', 'chegada' => '09:20', 'abatimento' => 85],
    ['nf' => 'NF-47904', 'volume' => 3.0, 'saida' => '09:10', 'chegada' => '10:00', 'abatimento' => 75],
]);

// 7 dias: normais. 28 dias: 22,4 / 23,1 / 21,9 MPa — todos abaixo dos 25.
foreach ([
    ['C1-7d-A', 140.0, 33], ['C1-7d-B', 137.5, 33], ['C2-7d-A', 143.0, 33], ['C2-7d-B', 139.0, 33], ['C3-7d-A', 136.0, 33], ['C3-7d-B', 141.5, 33],
    ['C1-28d-A', 175.9, 12], ['C1-28d-B', 171.0, 12], ['C2-28d-A', 181.4, 12], ['C2-28d-B', 176.5, 12], ['C3-28d-A', 172.0, 12], ['C3-28d-B', 168.3, 12],
] as [$cp, $cargaKN, $diasAtras]) {
    $registrar->executar(OBRA, $numeroBlocos, $cp, $cargaKN, 100, diasAtras($diasAtras, '10:30'));
}

$formarLote = new FormarLote($conexao, $concretagens, $lotes);
$julgarLote = new JulgarLote($conexao, $lotes, $naoConformidades);

$loteDosBlocos = $formarLote->executar(OBRA, ClasseDeResistencia::C25, GrupoDeSolicitacao::Horizontal, CondicaoDePreparo::A, TipoDeAmostragem::Total, [$numeroBlocos]);
$julgarLote->executar(OBRA, $loteDosBlocos->numero(), diasAtras(11, '09:00'));

(new TratarNaoConformidade($naoConformidades))->registrarProvidencia(OBRA, $loteDosBlocos->numero(), new Providencia(
    TipoDeProvidencia::EnsaioNaoDestrutivo,
    diasAtras(8, '00:00'),
    'Esclerometria em 9 pontos dos três blocos; índices homogêneos, sem região crítica localizada. Extração de testemunhos programada.',
    ResultadoDaProvidencia::Informativo,
    'Laboratório Litoral',
));

// Hoje: concretagem em andamento, duas cargas recebidas até agora.
concretar($concretagens, $laje, 0, 'Concreteira Litoral', [
    ['nf' => 'NF-50017', 'volume' => 8.0, 'saida' => '06:30', 'chegada' => '07:15', 'abatimento' => 100],
    ['nf' => 'NF-50018', 'volume' => 8.0, 'saida' => '07:00', 'chegada' => '07:50', 'abatimento' => 105],
], concluir: false);

$totalCp = (int) $conexao->query('SELECT COUNT(*) FROM corpos_de_prova')->fetchColumn();

printf(
    'Banco carregado: 1 obra, 5 elementos, 5 concretagens, %d corpos de prova, 20 resultados, 1 lote reprovado com não conformidade aberta e %d conta(s).%s',
    $totalCp,
    $contasNovas,
    PHP_EOL,
);
