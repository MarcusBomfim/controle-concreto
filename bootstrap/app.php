<?php

use App\Http\Middleware\ExigirContaAtiva;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

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
    })->create();
