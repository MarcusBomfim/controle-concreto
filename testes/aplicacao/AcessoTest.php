<?php

declare(strict_types=1);

use ControleConcreto\Aplicacao\Autenticador;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Usuario\Papel;
use ControleConcreto\Dominio\Usuario\Usuario;
use ControleConcreto\Infraestrutura\Repositorio\RepositorioDeUsuariosEmSqlite;

grupo('Papel: permissões');

teste('engenheiro faz tudo', function (): void {
    verdadeiro(Papel::Engenheiro->podeConsultar(), 'consulta');
    verdadeiro(Papel::Engenheiro->podeOperar(), 'opera');
    verdadeiro(Papel::Engenheiro->podeDecidir(), 'decide');
});

teste('laboratorista opera mas não decide sobre a estrutura', function (): void {
    verdadeiro(Papel::Laboratorista->podeOperar(), 'recebe carga, molda, rompe');
    falso(Papel::Laboratorista->podeDecidir(), 'não forma nem julga lote');
});

teste('gestor só consulta', function (): void {
    verdadeiro(Papel::Gestor->podeConsultar(), 'consulta');
    falso(Papel::Gestor->podeOperar(), 'não opera');
    falso(Papel::Gestor->podeDecidir(), 'não decide');
});

grupo('Usuário');

teste('cria a conta com hash, nunca com a senha', function (): void {
    $usuario = Usuario::criar('Marcus@Concreto.dev', 'Marcus', Papel::Engenheiro, 'Segredo@123');

    igual('marcus@concreto.dev', $usuario->email, 'e-mail em minúsculas');
    verdadeiro(str_starts_with($usuario->hashDaSenha(), '$2y$'), 'formato bcrypt');
    falso(str_contains($usuario->hashDaSenha(), 'Segredo@123'), 'senha não aparece no hash');
});

teste('confere a senha certa e recusa a errada', function (): void {
    $usuario = Usuario::criar('a@concreto.dev', 'A', Papel::Gestor, 'Segredo@123');

    verdadeiro($usuario->senhaConfere('Segredo@123'), 'senha certa');
    falso($usuario->senhaConfere('segredo@123'), 'diferença de caixa');
    falso($usuario->senhaConfere(''), 'vazia');
});

teste('recusa senha curta', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => Usuario::criar('a@concreto.dev', 'A', Papel::Gestor, '1234567'),
        'ao menos 8 caracteres',
    );
});

teste('recusa e-mail inválido', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => Usuario::criar('nao-e-email', 'A', Papel::Gestor, 'Segredo@123'),
        'E-mail inválido',
    );
});

teste('hash novo não precisa de atualização', function (): void {
    $usuario = Usuario::criar('a@concreto.dev', 'A', Papel::Gestor, 'Segredo@123');

    falso($usuario->hashPrecisaAtualizar(), 'custo atual');
});

grupo('Repositório de usuários');

teste('grava e encontra pelo e-mail em qualquer caixa', function (): void {
    $repositorio = new RepositorioDeUsuariosEmSqlite(bancoDeTeste());
    $repositorio->salvar(Usuario::criar('marcus@concreto.dev', 'Marcus', Papel::Engenheiro, 'Segredo@123'));

    $lido = $repositorio->porEmail('MARCUS@CONCRETO.DEV');

    verdadeiro($lido !== null, 'encontrado');
    igual('Marcus', $lido?->nome);
    igual(Papel::Engenheiro, $lido?->papel);
    verdadeiro($lido?->senhaConfere('Segredo@123') ?? false, 'hash sobreviveu ao banco');
});

teste('preserva conta desativada', function (): void {
    $repositorio = new RepositorioDeUsuariosEmSqlite(bancoDeTeste());

    $usuario = Usuario::criar('a@concreto.dev', 'A', Papel::Gestor, 'Segredo@123');
    $usuario->desativar();
    $repositorio->salvar($usuario);

    falso($repositorio->porEmail('a@concreto.dev')?->estaAtivo() ?? true, 'continua desativada');
});

teste('salvar de novo atualiza em vez de duplicar', function (): void {
    $conexao = bancoDeTeste();
    $repositorio = new RepositorioDeUsuariosEmSqlite($conexao);

    $repositorio->salvar(Usuario::criar('a@concreto.dev', 'Nome antigo', Papel::Gestor, 'Segredo@123'));
    $repositorio->salvar(Usuario::criar('a@concreto.dev', 'Nome novo', Papel::Engenheiro, 'Segredo@123'));

    igual(1, (int) $conexao->query('SELECT COUNT(*) FROM usuarios')->fetchColumn());
    igual('Nome novo', $repositorio->porEmail('a@concreto.dev')?->nome);
});

grupo('Autenticador');

function autenticadorComConta(bool $ativa = true): Autenticador
{
    $repositorio = new RepositorioDeUsuariosEmSqlite(bancoDeTeste());

    $usuario = Usuario::criar('marcus@concreto.dev', 'Marcus', Papel::Engenheiro, 'Segredo@123');

    if (!$ativa) {
        $usuario->desativar();
    }

    $repositorio->salvar($usuario);

    return new Autenticador($repositorio);
}

teste('autentica com e-mail e senha corretos', function (): void {
    $usuario = autenticadorComConta()->autenticar('marcus@concreto.dev', 'Segredo@123');

    verdadeiro($usuario !== null, 'autenticado');
    igual(Papel::Engenheiro, $usuario?->papel);
});

teste('recusa senha errada', function (): void {
    igual(null, autenticadorComConta()->autenticar('marcus@concreto.dev', 'errada'));
});

teste('recusa e-mail inexistente sem lançar exceção', function (): void {
    // Tem que devolver null igualzinho à senha errada: qualquer diferença de
    // comportamento entregaria a lista de contas a quem ficasse tentando.
    igual(null, autenticadorComConta()->autenticar('ninguem@concreto.dev', 'Segredo@123'));
});

teste('recusa conta desativada mesmo com a senha certa', function (): void {
    igual(null, autenticadorComConta(false)->autenticar('marcus@concreto.dev', 'Segredo@123'));
});

teste('e-mail inexistente e senha errada custam tempo parecido', function (): void {
    // Não é um teste de segurança rigoroso — é um alarme: se a isca sumir e
    // a conta inexistente passar a responder em microssegundos, isto acusa.
    $autenticador = autenticadorComConta();

    $inicio = hrtime(true);
    $autenticador->autenticar('marcus@concreto.dev', 'senha-errada');
    $comConta = hrtime(true) - $inicio;

    $inicio = hrtime(true);
    $autenticador->autenticar('ninguem@concreto.dev', 'senha-errada');
    $semConta = hrtime(true) - $inicio;

    // bcrypt leva dezenas de milissegundos; a busca no SQLite, microssegundos.
    // Se a conta inexistente não passar pelo bcrypt, a razão dispara.
    $razao = $semConta / max(1, $comConta);

    verdadeiro($razao > 0.2 && $razao < 5.0, "razão de tempo fora do esperado: {$razao}");
});
