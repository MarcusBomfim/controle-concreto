<?php

declare(strict_types=1);

use ControleConcreto\Aplicacao\OperacoesDeConcretagem;
use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;
use ControleConcreto\Dominio\Usuario\Papel;
use ControleConcreto\Dominio\Usuario\Usuario;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeUsuariosEmSqlite;
use ControleConcreto\Web\Montagem;
use ControleConcreto\Web\Requisicao;
use ControleConcreto\Web\Resposta;
use ControleConcreto\Web\Sessao;
use ControleConcreto\Web\Visao;

/*
 * A aplicação inteira, sem servidor: banco em memória, sessão em memória e a
 * mesma Montagem que o public/index.php usa. Cada requisição atravessa o
 * roteador, a guarda, o controlador e o template de verdade.
 *
 * Qualquer aviso do PHP durante a renderização vira falha: um template que
 * lê variável indefinida não pode passar em silêncio.
 */

/** @return array{requisitar: callable(string, string, array=): Resposta, app: array, sessao: Sessao} */
function aplicacaoWeb(): array
{
    $app = ambienteComObra();
    $sessao = Sessao::emMemoria();
    $visao = Visao::padrao();

    $usuarios = new RepositorioDeUsuariosEmSqlite($app['conexao']);
    $usuarios->salvar(Usuario::criar('eng@teste.dev', 'Engenheira de Teste', Papel::Engenheiro, 'SenhaDeTeste1'));
    $usuarios->salvar(Usuario::criar('lab@teste.dev', 'Laboratorista de Teste', Papel::Laboratorista, 'SenhaDeTeste1'));
    $usuarios->salvar(Usuario::criar('gestor@teste.dev', 'Gestor de Teste', Papel::Gestor, 'SenhaDeTeste1'));

    $requisitar = static function (string $metodo, string $caminho, array $corpo = []) use ($app, $sessao, $visao): Resposta {
        $anterior = set_error_handler(static function (int $nivel, string $mensagem, string $arquivo, int $linha): bool {
            throw new ErrorException($mensagem, 0, $nivel, $arquivo, $linha);
        });

        try {
            // A montagem é por requisição, como no index.php: a guarda lê o
            // usuário da sessão a cada pedido.
            return Montagem::roteador($app['conexao'], $sessao, $visao)
                ->despachar(new Requisicao($metodo, $caminho, [], $corpo));
        } finally {
            set_error_handler($anterior);
        }
    };

    return ['requisitar' => $requisitar, 'app' => $app, 'sessao' => $sessao];
}

/** Faz o login pela rota, como o navegador faria, e devolve o token da sessão. */
function entrarComo(callable $requisitar, Sessao $sessao, string $email): string
{
    $resposta = $requisitar('POST', '/entrar', [
        'token' => $sessao->token(),
        'email' => $email,
        'senha' => 'SenhaDeTeste1',
    ]);

    igual(303, $resposta->status, 'login redireciona');
    igual('/', $resposta->cabecalhos['Location'] ?? null, 'login volta para a agenda');

    return $sessao->token();
}

function contem(string $trecho, string $texto, string $contexto): void
{
    verdadeiro(str_contains($texto, $trecho), $contexto . " — esperava encontrar \"{$trecho}\"");
}

/**
 * Concretagem de 28 dias atrás com um exemplar de 28 dias moldado: os dois
 * corpos de prova estão na janela de rompimento neste exato momento.
 */
function concretagemNaJanela(array $app): int
{
    $operacoes = new OperacoesDeConcretagem($app['obras'], $app['elementos'], $app['concretagens']);

    // Moldado às 10h de 28 dias atrás: a janela de ± 20 h cobre o dia de hoje inteiro.
    $moldagem = (new DateTimeImmutable('today'))->modify('-28 days')->setTime(10, 0);

    $concretagem = $operacoes->abrir('OBR-2026-007', 'L3-P4', $moldagem, 'Usina', 'Marcus');
    $operacoes->receberCarga('OBR-2026-007', $concretagem->numero(), 'NF-1', null, 8.0, $moldagem->modify('-60 minutes'), $moldagem->modify('-10 minutes'), 100, null);
    $operacoes->moldar('OBR-2026-007', $concretagem->numero(), 1, $moldagem, [IdadeDeEnsaio::VinteEOitoDias]);

    return $concretagem->numero();
}

grupo('Fluxo web: acesso');

teste('sem login, a agenda redireciona para o login guardando o destino', function (): void {
    ['requisitar' => $requisitar] = aplicacaoWeb();

    $resposta = $requisitar('GET', '/');

    igual(303, $resposta->status);
    igual('/entrar?voltar=%2F', $resposta->cabecalhos['Location'] ?? null);
});

teste('a página de login renderiza com o formulário e o token', function (): void {
    ['requisitar' => $requisitar] = aplicacaoWeb();

    $resposta = $requisitar('GET', '/entrar');

    igual(200, $resposta->status);
    contem('name="senha"', $resposta->corpo, 'campo de senha');
    contem('name="token"', $resposta->corpo, 'token anti-CSRF');
});

teste('senha errada volta para o login com a mensagem, sem logar', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao] = aplicacaoWeb();

    $resposta = $requisitar('POST', '/entrar', ['token' => $sessao->token(), 'email' => 'eng@teste.dev', 'senha' => 'errada']);

    igual(303, $resposta->status);
    contem('aviso--erro', $requisitar('GET', '/entrar')->corpo, 'o erro aparece na página seguinte');
    igual(null, $sessao->emailAtual());
});

teste('login certo abre a agenda com o usuário no topo', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao] = aplicacaoWeb();
    entrarComo($requisitar, $sessao, 'lab@teste.dev');

    $agenda = $requisitar('GET', '/');

    igual(200, $agenda->status);
    contem('Agenda do laboratório', $agenda->corpo, 'título');
    contem('Laboratorista de Teste', $agenda->corpo, 'quem entrou');
});

teste('sair encerra a sessão', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao] = aplicacaoWeb();
    $token = entrarComo($requisitar, $sessao, 'lab@teste.dev');

    igual(303, $requisitar('POST', '/sair', ['token' => $token])->status);
    igual(303, $requisitar('GET', '/')->status, 'a agenda volta a pedir login');
});

grupo('Fluxo web: o dia de concretagem, pelo laboratorista');

teste('abre a concretagem, recebe o caminhão, molda e conclui pelos formulários', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao, 'app' => $app] = aplicacaoWeb();
    $token = entrarComo($requisitar, $sessao, 'lab@teste.dev');

    igual(200, $requisitar('GET', '/obras/OBR-2026-007/concretagens/nova')->status, 'o formulário abre');

    $hoje = (new DateTimeImmutable('today'))->format('Y-m-d');

    $aberta = $requisitar('POST', '/obras/OBR-2026-007/concretagens', [
        'token' => $token,
        'elemento' => 'L3-P4',
        'data' => $hoje,
        'fornecedor' => 'Concreteira Litoral',
        'responsavel' => 'Helena Duarte',
    ]);

    igual(303, $aberta->status);
    igual('/obras/OBR-2026-007/concretagens/1', $aberta->cabecalhos['Location'] ?? null);

    $carga = $requisitar('POST', '/obras/OBR-2026-007/concretagens/1/cargas', [
        'token' => $token,
        'nota_fiscal' => 'NF-77',
        'placa' => 'abc-1d23',
        'volume_m3' => '8,0',
        'saida' => '07:00',
        'chegada' => '07:45',
        'abatimento_mm' => '105',
        'observacao' => '',
    ]);

    igual(303, $carga->status);

    $pagina = $requisitar('GET', '/obras/OBR-2026-007/concretagens/1');
    contem('Carga 1 (NF NF-77) aceita', $pagina->corpo, 'mensagem de sucesso');
    contem('ABC-1D23', $pagina->corpo, 'placa normalizada');

    $devolvida = $requisitar('POST', '/obras/OBR-2026-007/concretagens/1/cargas', [
        'token' => $token,
        'nota_fiscal' => 'NF-78',
        'volume_m3' => '8,0',
        'saida' => '07:30',
        'chegada' => '08:20',
        'abatimento_mm' => '160',
    ]);

    igual(303, $devolvida->status);
    contem('DEVOLVIDA', $requisitar('GET', '/obras/OBR-2026-007/concretagens/1')->corpo, '160 mm contra 100 ± 20: devolvida');

    $moldagem = $requisitar('POST', '/obras/OBR-2026-007/concretagens/1/moldagens', [
        'token' => $token,
        'carga' => '1',
        'hora' => '08:00',
        'idades' => ['7', '28'],
    ]);

    igual(303, $moldagem->status);
    igual(4, $app['agenda']->totalEmCura(), 'dois exemplares, quatro cilindros');

    $recusada = $requisitar('POST', '/obras/OBR-2026-007/concretagens/1/moldagens', [
        'token' => $token,
        'carga' => '2',
        'hora' => '08:30',
        'idades' => ['28'],
    ]);

    igual(303, $recusada->status);
    contem('foi devolvida', $requisitar('GET', '/obras/OBR-2026-007/concretagens/1')->corpo, 'carga devolvida não gera corpo de prova');

    igual(303, $requisitar('POST', '/obras/OBR-2026-007/concretagens/1/concluir', ['token' => $token])->status);
    contem('Concluída', $requisitar('GET', '/obras/OBR-2026-007/concretagens/1')->corpo, 'situação');
});

teste('formulário com token forjado não grava nada', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao, 'app' => $app] = aplicacaoWeb();
    entrarComo($requisitar, $sessao, 'lab@teste.dev');

    $resposta = $requisitar('POST', '/obras/OBR-2026-007/concretagens', [
        'token' => 'forjado',
        'elemento' => 'L3-P4',
        'data' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        'fornecedor' => 'Usina',
        'responsavel' => 'Alguém',
    ]);

    igual(303, $resposta->status);
    igual([], $app['concretagens']->daObra('OBR-2026-007'), 'nada gravado');
});

teste('laboratorista não cadastra nem forma lote', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao] = aplicacaoWeb();
    $token = entrarComo($requisitar, $sessao, 'lab@teste.dev');

    igual(403, $requisitar('GET', '/obras/nova')->status, 'cadastro de obra');
    igual(403, $requisitar('GET', '/obras/OBR-2026-007/lotes/novo')->status, 'formulário de lote');
    igual(403, $requisitar('POST', '/obras/OBR-2026-007/lotes', ['token' => $token])->status, 'POST direto também');

    $obra = $requisitar('GET', '/obras/OBR-2026-007');
    igual(200, $obra->status);
    falso(str_contains($obra->corpo, 'lotes/novo'), 'a interface esconde o que ele não pode');
    contem('concretagens/nova', $obra->corpo, 'mas mostra o que pode');
});

grupo('Fluxo web: a prensa');

teste('registra o resultado direto da agenda e o cilindro sai da lista', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao, 'app' => $app] = aplicacaoWeb();
    $numero = concretagemNaJanela($app);
    $token = entrarComo($requisitar, $sessao, 'lab@teste.dev');

    $agenda = $requisitar('GET', '/');
    contem('C1-28d-A', $agenda->corpo, 'o cilindro está na agenda');
    contem('name="carga_kn"', $agenda->corpo, 'com o formulário de resultado na linha');

    $resposta = $requisitar('POST', "/obras/OBR-2026-007/concretagens/{$numero}/corpos-de-prova/c1-28d-a/romper", [
        'token' => $token,
        'voltar' => '/',
        'carga_kn' => '245,5',
        'diametro_mm' => '100',
        'rompido_em' => (new DateTimeImmutable('now'))->format('Y-m-d\TH:i'),
    ]);

    igual(303, $resposta->status);
    igual('/', $resposta->cabecalhos['Location'] ?? null);

    $depois = $requisitar('GET', '/');
    contem('C1-28d-A rompido: 245,5 kN', $depois->corpo, 'mensagem com a conversão');
    contem('31,3 MPa', $depois->corpo, '245,5 kN em 10 cm dá 31,3 MPa');
    igual(1, $app['agenda']->totalEmCura(), 'só o B continua em cura');
});

teste('o campo "voltar" não vira redirecionamento aberto', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao, 'app' => $app] = aplicacaoWeb();
    $numero = concretagemNaJanela($app);
    $token = entrarComo($requisitar, $sessao, 'lab@teste.dev');

    $resposta = $requisitar('POST', "/obras/OBR-2026-007/concretagens/{$numero}/corpos-de-prova/C1-28d-B/descartar", [
        'token' => $token,
        'voltar' => 'https://evil.example',
        'motivo' => 'Quebrou na desforma',
    ]);

    igual('/', $resposta->cabecalhos['Location'] ?? null, 'destino externo cai para a agenda');
});

grupo('Fluxo web: lote, julgamento e não conformidade, pelo engenheiro');

teste('forma o lote, julga, trata a não conformidade e encerra', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao, 'app' => $app] = aplicacaoWeb();

    // Três exemplares C30 abaixo de 30: amostragem total dá fck,est = 26,4.
    $numero = $app['concretagens']->salvar(concretagemComResultados(0, [27.0, 26.4, 28.1]));

    $token = entrarComo($requisitar, $sessao, 'eng@teste.dev');

    $formulario = $requisitar('GET', '/obras/OBR-2026-007/lotes/novo');
    igual(200, $formulario->status);
    contem('name="concretagens[]"', $formulario->corpo, 'a concretagem concluída está disponível');

    $formado = $requisitar('POST', '/obras/OBR-2026-007/lotes', [
        'token' => $token,
        'concretagens' => [(string) $numero],
        'condicao' => 'a',
        'amostragem' => 'total',
    ]);

    igual(303, $formado->status);
    igual('/obras/OBR-2026-007/lotes/1', $formado->cabecalhos['Location'] ?? null);

    $lote = $requisitar('GET', '/obras/OBR-2026-007/lotes/1');
    contem('Julgar lote', $lote->corpo, 'o engenheiro vê o botão');

    igual(303, $requisitar('POST', '/obras/OBR-2026-007/lotes/1/julgar', ['token' => $token])->status);

    $julgado = $requisitar('GET', '/obras/OBR-2026-007/lotes/1');
    contem('Não conforme', $julgado->corpo, 'veredito');
    contem('26,4 MPa', $julgado->corpo, 'fck estimado na memória de cálculo');
    contem('A não conformidade foi aberta', $julgado->corpo, 'a mensagem avisa');
    contem('nao-conformidade', $julgado->corpo, 'e a página linka para ela');

    $nc = $requisitar('GET', '/obras/OBR-2026-007/lotes/1/nao-conformidade');
    igual(200, $nc->status);
    contem('faltaram 3,6 MPa', $nc->corpo, 'o tamanho do problema');
    contem('Nenhum desfecho está sustentado ainda', $nc->corpo, 'sem providência não encerra');

    $providencia = $requisitar('POST', '/obras/OBR-2026-007/lotes/1/nao-conformidade/providencias', [
        'token' => $token,
        'tipo' => 'extracao_de_testemunhos',
        'realizada_em' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        'resultado' => 'favoravel',
        'fck_obtido_mpa' => '31,2',
        'descricao' => 'Três testemunhos extraídos da laje e rompidos conforme a NBR 7680.',
        'responsavel' => 'Laboratório Litoral',
    ]);

    igual(303, $providencia->status);

    $comProvidencia = $requisitar('GET', '/obras/OBR-2026-007/lotes/1/nao-conformidade');
    contem('Já é possível encerrar: estrutura aceita', $comProvidencia->corpo, 'a mensagem diz o que a providência sustenta');
    contem('name="desfecho"', $comProvidencia->corpo, 'o formulário de encerramento aparece');

    $encerrada = $requisitar('POST', '/obras/OBR-2026-007/lotes/1/nao-conformidade/encerrar', [
        'token' => $token,
        'desfecho' => 'estrutura_aceita',
        'parecer' => 'Testemunhos a 31,2 MPa: a laje atende ao projeto.',
    ]);

    igual(303, $encerrada->status);

    $final = $requisitar('GET', '/obras/OBR-2026-007/lotes/1/nao-conformidade');
    contem('Encerrada', $final->corpo, 'situação');
    contem('Parecer de encerramento', $final->corpo, 'o parecer fica na página');
    falso(str_contains($final->corpo, 'name="desfecho"'), 'sem formulário depois de encerrar');

    contem('Estrutura aceita', $requisitar('GET', '/obras/OBR-2026-007')->corpo, 'a obra lista o desfecho');
});

teste('a recusa do domínio chega como mensagem, não como erro 500', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao, 'app' => $app] = aplicacaoWeb();
    $numero = $app['concretagens']->salvar(concretagemComResultados(0, [27.0, 26.4]));
    $token = entrarComo($requisitar, $sessao, 'eng@teste.dev');

    // Amostragem parcial exige seis exemplares; há dois.
    $requisitar('POST', '/obras/OBR-2026-007/lotes', [
        'token' => $token,
        'concretagens' => [(string) $numero],
        'condicao' => 'a',
        'amostragem' => 'parcial',
    ]);
    $requisitar('POST', '/obras/OBR-2026-007/lotes/1/julgar', ['token' => $token]);

    $lote = $requisitar('GET', '/obras/OBR-2026-007/lotes/1');
    contem('aviso--erro', $lote->corpo, 'o erro aparece');
    contem('ao menos 6 exemplares', $lote->corpo, 'com a mensagem do domínio');
});

grupo('Fluxo web: gestor');

teste('gestor vê tudo e não mexe em nada', function (): void {
    ['requisitar' => $requisitar, 'sessao' => $sessao, 'app' => $app] = aplicacaoWeb();
    $numero = concretagemNaJanela($app);
    $token = entrarComo($requisitar, $sessao, 'gestor@teste.dev');

    $agenda = $requisitar('GET', '/');
    igual(200, $agenda->status);
    contem('C1-28d-A', $agenda->corpo, 'vê o cilindro');
    falso(str_contains($agenda->corpo, 'name="carga_kn"'), 'sem formulário de resultado');

    igual(200, $requisitar('GET', "/obras/OBR-2026-007/concretagens/{$numero}")->status, 'consulta a concretagem');
    igual(403, $requisitar('POST', "/obras/OBR-2026-007/concretagens/{$numero}/corpos-de-prova/C1-28d-A/romper", ['token' => $token])->status, 'não rompe');
    igual(403, $requisitar('GET', '/obras/OBR-2026-007/concretagens/nova')->status, 'não abre concretagem');
    igual(403, $requisitar('GET', '/obras/nova')->status, 'não cadastra');
    igual(404, $requisitar('GET', '/obras/NAO-EXISTE')->status, '404 continua 404');
});
