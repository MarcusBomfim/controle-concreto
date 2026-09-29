<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Conta;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Desativar uma conta vale já, não quando a sessão expirar.
 *
 * O `Auth::attempt` filtra por `ativo` e impede o login de uma conta
 * desativada — mas quem já estava logado continuaria dentro até a sessão
 * morrer. Este middleware fecha essa janela: a cada requisição, se a conta
 * carregada da sessão não está mais ativa, a sessão cai.
 *
 * Era o que a classe Guarda fazia ao reler o usuário do banco em toda
 * requisição, em vez de confiar no que estava na sessão.
 */
final class ExigirContaAtiva
{
    public function handle(Request $requisicao, Closure $seguir): Response
    {
        $conta = Auth::user();

        if ($conta instanceof Conta && !$conta->ativo) {
            Auth::logout();
            $requisicao->session()->invalidate();
            $requisicao->session()->regenerateToken();

            return redirect()
                ->route('acesso.formulario')
                ->with('erro', 'Esta conta foi desativada. Procure o responsável pela obra.');
        }

        return $seguir($requisicao);
    }
}
