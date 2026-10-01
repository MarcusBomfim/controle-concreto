<?php

use App\Http\Middleware\ExigirContaAtiva;
use App\Support\RegistroDeSeguranca;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Quem não está logado vai para /entrar — e o Laravel guarda a URL
         * pretendida na sessão, para o login devolver a pessoa ao lugar
         * certo com redirect()->intended().
         *
         * Na versão em PHP puro isso era "?voltar=" na query string, que
         * precisava ser validado contra redirecionamento aberto a cada uso.
         * Aqui o destino nunca passa pelo navegador.
         */
        $middleware->redirectGuestsTo('/entrar');

        $middleware->alias(['conta.ativa' => ExigirContaAtiva::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        /*
         * Tentativa de fazer o que o papel não permite vai para o log de
         * segurança. Interessa pouco quando é engano de navegação, e
         * interessa muito quando é alguém batendo numa rota de POST que a
         * tela nunca ofereceu.
         *
         * O callback devolve null de propósito: ele só registra e deixa o
         * Laravel renderizar o 403 normalmente.
         *
         * O tipo é o AccessDeniedHttpException do Symfony, e não a
         * AuthorizationException do Laravel: o `prepareException` do
         * handler converte uma na outra **antes** de consultar os
         * callbacks, então um callback tipado na exceção original nunca
         * seria chamado. Assim também pega o `abort(403)` direto.
         */
        $exceptions->render(function (AccessDeniedHttpException $erro, Request $requisicao) {
            RegistroDeSeguranca::acessoNegado(Auth::user()?->email, $requisicao);

            return null;
        });
    })->create();
