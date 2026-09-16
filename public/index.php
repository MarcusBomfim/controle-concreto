<?php

declare(strict_types=1);

/*
 * Ponto de entrada da aplicação web.
 *
 *   php -S localhost:8000 -t public public/index.php
 *
 * Antes da primeira execução:
 *   php ferramentas/migrar.php
 *   php ferramentas/semear.php
 */

/*
 * O servidor embutido do PHP não serve arquivo estático quando há um script de
 * roteamento. Devolver false devolve a tarefa a ele — é como o estilo.css
 * chega ao navegador sem passar pelo roteador.
 */
if (PHP_SAPI === 'cli-server') {
    $caminhoDoArquivo = __DIR__ . parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);

    if (is_file($caminhoDoArquivo)) {
        return false;
    }
}

require dirname(__DIR__) . '/src/autoload.php';

use ControleConcreto\Aplicacao\DescartarCorpoDeProva;
use ControleConcreto\Aplicacao\FormarLote;
use ControleConcreto\Aplicacao\JulgarLote;
use ControleConcreto\Aplicacao\OperacoesDeConcretagem;
use ControleConcreto\Aplicacao\RegistrarRompimento;
use ControleConcreto\Infraestrutura\Banco\Conexao;
use ControleConcreto\Infraestrutura\Banco\Migrador;
use ControleConcreto\Infraestrutura\Repositorio\AgendaDoLaboratorioEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeConcretagensEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeElementosEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeLotesEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeObrasEmSqlite;
use ControleConcreto\Web\Controlador\ControladorDeConcretagens;
use ControleConcreto\Web\Controlador\ControladorDeLotes;
use ControleConcreto\Web\Controlador\ControladorDeObras;
use ControleConcreto\Web\Controlador\ControladorDoLaboratorio;
use ControleConcreto\Web\Requisicao;
use ControleConcreto\Web\Resposta;
use ControleConcreto\Web\Roteador;
use ControleConcreto\Web\Sessao;
use ControleConcreto\Web\Visao;

$visao = Visao::padrao();

try {
    $conexao = Conexao::abrir();
    $pendentes = Migrador::padrao($conexao)->pendentes();
} catch (Throwable $erro) {
    // Sem banco não há aplicação: melhor dizer o que fazer do que dar erro 500.
    Resposta::html($visao->renderizar('erro', [
        'titulo' => 'Banco de dados indisponível',
        'detalhe' => 'Rode php ferramentas/migrar.php e tente de novo.',
    ], 'Banco indisponível'), 500)->enviar();

    return;
}

if ($pendentes !== []) {
    Resposta::html($visao->renderizar('erro', [
        'titulo' => 'Há migrations pendentes',
        'detalhe' => 'Rode php ferramentas/migrar.php antes de usar a aplicação.',
    ], 'Migrations pendentes'), 503)->enviar();

    return;
}

$sessao = new Sessao();
$sessao->iniciar();

// O token anti-CSRF chega a todo formulário pelo template.
$visao->definirTokenDaSessao($sessao->token());

$obras = new RepositorioDeObrasEmSqlite($conexao);
$elementos = new RepositorioDeElementosEmSqlite($conexao);
$concretagens = new RepositorioDeConcretagensEmSqlite($conexao);
$lotes = new RepositorioDeLotesEmSqlite($conexao, $concretagens);
$agenda = new AgendaDoLaboratorioEmSqlite($conexao);

$laboratorio = new ControladorDoLaboratorio(
    $agenda,
    new RegistrarRompimento($concretagens),
    new DescartarCorpoDeProva($concretagens),
    $visao,
    $sessao,
);

$controladorDeObras = new ControladorDeObras($obras, $elementos, $concretagens, $lotes, $visao, $sessao);

$controladorDeConcretagens = new ControladorDeConcretagens(
    $obras,
    $elementos,
    $concretagens,
    $lotes,
    new OperacoesDeConcretagem($obras, $elementos, $concretagens),
    $visao,
    $sessao,
);

$controladorDeLotes = new ControladorDeLotes(
    $obras,
    $concretagens,
    $lotes,
    new FormarLote($conexao, $concretagens, $lotes),
    new JulgarLote($lotes),
    $visao,
    $sessao,
);

$roteador = new Roteador();

// A agenda é a tela principal: é o que o laboratório abre de manhã.
$roteador->get('/', $laboratorio->agenda(...));

$roteador->get('/obras', $controladorDeObras->lista(...));
$roteador->get('/obras/nova', $controladorDeObras->nova(...));
$roteador->post('/obras', $controladorDeObras->criar(...));
$roteador->get('/obras/{codigo}', $controladorDeObras->detalhe(...));
$roteador->get('/obras/{codigo}/elementos/novo', $controladorDeObras->novoElemento(...));
$roteador->post('/obras/{codigo}/elementos', $controladorDeObras->criarElemento(...));

$roteador->get('/obras/{codigo}/concretagens/nova', $controladorDeConcretagens->nova(...));
$roteador->post('/obras/{codigo}/concretagens', $controladorDeConcretagens->criar(...));
$roteador->get('/obras/{codigo}/concretagens/{numero}', $controladorDeConcretagens->detalhe(...));
$roteador->post('/obras/{codigo}/concretagens/{numero}/cargas', $controladorDeConcretagens->receberCarga(...));
$roteador->post('/obras/{codigo}/concretagens/{numero}/moldagens', $controladorDeConcretagens->moldar(...));
$roteador->post('/obras/{codigo}/concretagens/{numero}/concluir', $controladorDeConcretagens->concluir(...));
$roteador->post('/obras/{codigo}/concretagens/{numero}/cancelar', $controladorDeConcretagens->cancelar(...));

// O corpo de prova pertence à concretagem, então romper e descartar moram aqui.
$roteador->post('/obras/{codigo}/concretagens/{numero}/corpos-de-prova/{identificacao}/romper', $laboratorio->romper(...));
$roteador->post('/obras/{codigo}/concretagens/{numero}/corpos-de-prova/{identificacao}/descartar', $laboratorio->descartar(...));

$roteador->get('/obras/{codigo}/lotes/novo', $controladorDeLotes->novo(...));
$roteador->post('/obras/{codigo}/lotes', $controladorDeLotes->formar(...));
$roteador->get('/obras/{codigo}/lotes/{numero}', $controladorDeLotes->detalhe(...));
$roteador->post('/obras/{codigo}/lotes/{numero}/julgar', $controladorDeLotes->julgar(...));

$roteador->despachar(Requisicao::dasSuperglobais())->enviar();
