<?php

declare(strict_types=1);

namespace ControleConcreto\Web;

use ControleConcreto\Aplicacao\Autenticador;
use ControleConcreto\Aplicacao\DescartarCorpoDeProva;
use ControleConcreto\Aplicacao\FormarLote;
use ControleConcreto\Aplicacao\JulgarLote;
use ControleConcreto\Aplicacao\OperacoesDeConcretagem;
use ControleConcreto\Aplicacao\RegistrarRompimento;
use ControleConcreto\Aplicacao\TratarNaoConformidade;
use ControleConcreto\Dominio\Usuario\Papel;
use ControleConcreto\Infraestrutura\Repositorio\AgendaDoLaboratorioEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeConcretagensEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeElementosEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeLotesEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeNaoConformidadesEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeObrasEmSqlite;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeUsuariosEmSqlite;
use ControleConcreto\Web\Controlador\ControladorDeAcesso;
use ControleConcreto\Web\Controlador\ControladorDeConcretagens;
use ControleConcreto\Web\Controlador\ControladorDeLotes;
use ControleConcreto\Web\Controlador\ControladorDeNaoConformidades;
use ControleConcreto\Web\Controlador\ControladorDeObras;
use ControleConcreto\Web\Controlador\ControladorDoLaboratorio;
use PDO;

/**
 * Monta a aplicação: repositórios, casos de uso, controladores e rotas.
 *
 * É o único lugar que conhece todas as classes concretas. O public/index.php
 * chama isto com a conexão e a sessão de verdade; os testes chamam com um
 * banco em memória e uma sessão em memória, e atravessam o mesmo roteador,
 * os mesmos controladores e os mesmos templates que o navegador atravessa.
 */
final class Montagem
{
    private function __construct()
    {
    }

    public static function roteador(PDO $conexao, Sessao $sessao, Visao $visao): Roteador
    {
        $obras = new RepositorioDeObrasEmSqlite($conexao);
        $elementos = new RepositorioDeElementosEmSqlite($conexao);
        $concretagens = new RepositorioDeConcretagensEmSqlite($conexao);
        $lotes = new RepositorioDeLotesEmSqlite($conexao, $concretagens);
        $naoConformidades = new RepositorioDeNaoConformidadesEmSqlite($conexao);
        $agenda = new AgendaDoLaboratorioEmSqlite($conexao);
        $usuarios = new RepositorioDeUsuariosEmSqlite($conexao);

        $guarda = new Guarda($sessao, $usuarios, $visao);

        // Quem está logado e o token chegam a todo template pelo layout.
        $visao->definirUsuario($guarda->usuarioAtual());
        $visao->definirTokenDaSessao($sessao->token());

        $acesso = new ControladorDeAcesso(new Autenticador($usuarios), $visao, $sessao);

        $laboratorio = new ControladorDoLaboratorio(
            $agenda,
            new RegistrarRompimento($concretagens),
            new DescartarCorpoDeProva($concretagens),
            $visao,
            $sessao,
        );

        $controladorDeObras = new ControladorDeObras($obras, $elementos, $concretagens, $lotes, $naoConformidades, $visao, $sessao);

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
            $naoConformidades,
            new FormarLote($conexao, $concretagens, $lotes),
            new JulgarLote($conexao, $lotes, $naoConformidades),
            $visao,
            $sessao,
        );

        $controladorDeNaoConformidades = new ControladorDeNaoConformidades(
            $obras,
            $lotes,
            $naoConformidades,
            new TratarNaoConformidade($naoConformidades),
            $visao,
            $sessao,
        );

        $roteador = new Roteador();

        // As permissões moram no enum Papel; aqui só se diz qual cada rota exige.
        $operar = static fn (Papel $papel): bool => $papel->podeOperar();
        $decidir = static fn (Papel $papel): bool => $papel->podeDecidir();

        $roteador->get('/entrar', $acesso->formulario(...));
        $roteador->post('/entrar', $acesso->entrar(...));
        $roteador->post('/sair', $acesso->sair(...));

        // A agenda é a tela principal: é o que o laboratório abre de manhã.
        $roteador->get('/', $guarda->autenticado($laboratorio->agenda(...)));

        $roteador->get('/obras', $guarda->autenticado($controladorDeObras->lista(...)));
        $roteador->get('/obras/nova', $guarda->com($decidir, $controladorDeObras->nova(...)));
        $roteador->post('/obras', $guarda->com($decidir, $controladorDeObras->criar(...)));
        $roteador->get('/obras/{codigo}', $guarda->autenticado($controladorDeObras->detalhe(...)));
        $roteador->get('/obras/{codigo}/elementos/novo', $guarda->com($decidir, $controladorDeObras->novoElemento(...)));
        $roteador->post('/obras/{codigo}/elementos', $guarda->com($decidir, $controladorDeObras->criarElemento(...)));

        $roteador->get('/obras/{codigo}/concretagens/nova', $guarda->com($operar, $controladorDeConcretagens->nova(...)));
        $roteador->post('/obras/{codigo}/concretagens', $guarda->com($operar, $controladorDeConcretagens->criar(...)));
        $roteador->get('/obras/{codigo}/concretagens/{numero}', $guarda->autenticado($controladorDeConcretagens->detalhe(...)));
        $roteador->post('/obras/{codigo}/concretagens/{numero}/cargas', $guarda->com($operar, $controladorDeConcretagens->receberCarga(...)));
        $roteador->post('/obras/{codigo}/concretagens/{numero}/moldagens', $guarda->com($operar, $controladorDeConcretagens->moldar(...)));
        $roteador->post('/obras/{codigo}/concretagens/{numero}/concluir', $guarda->com($operar, $controladorDeConcretagens->concluir(...)));
        $roteador->post('/obras/{codigo}/concretagens/{numero}/cancelar', $guarda->com($operar, $controladorDeConcretagens->cancelar(...)));

        // O corpo de prova pertence à concretagem, então romper e descartar moram aqui.
        $roteador->post('/obras/{codigo}/concretagens/{numero}/corpos-de-prova/{identificacao}/romper', $guarda->com($operar, $laboratorio->romper(...)));
        $roteador->post('/obras/{codigo}/concretagens/{numero}/corpos-de-prova/{identificacao}/descartar', $guarda->com($operar, $laboratorio->descartar(...)));

        $roteador->get('/obras/{codigo}/lotes/novo', $guarda->com($decidir, $controladorDeLotes->novo(...)));
        $roteador->post('/obras/{codigo}/lotes', $guarda->com($decidir, $controladorDeLotes->formar(...)));
        $roteador->get('/obras/{codigo}/lotes/{numero}', $guarda->autenticado($controladorDeLotes->detalhe(...)));
        $roteador->post('/obras/{codigo}/lotes/{numero}/julgar', $guarda->com($decidir, $controladorDeLotes->julgar(...)));

        $roteador->get('/obras/{codigo}/lotes/{numero}/nao-conformidade', $guarda->autenticado($controladorDeNaoConformidades->detalhe(...)));
        $roteador->post('/obras/{codigo}/lotes/{numero}/nao-conformidade/providencias', $guarda->com($decidir, $controladorDeNaoConformidades->registrarProvidencia(...)));
        $roteador->post('/obras/{codigo}/lotes/{numero}/nao-conformidade/encerrar', $guarda->com($decidir, $controladorDeNaoConformidades->encerrar(...)));

        return $roteador;
    }
}
