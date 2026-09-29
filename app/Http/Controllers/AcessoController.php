<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EntrarRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Entrar e sair.
 *
 * É o controlador que mais encolheu na migração, e vale dizer o que sumiu.
 * O `Auth::attempt` do Laravel faz, numa chamada:
 *
 *  - busca a conta pelas credenciais que não são senha — inclusive
 *    `ativo => true`, então conta desativada nem é encontrada;
 *  - confere o hash com `Hash::check`, em tempo constante;
 *  - regrava o hash se o custo padrão do PHP subiu desde que ele foi feito;
 *  - envolve tudo num `Timebox` de 200 ms, para que e-mail existente e
 *    e-mail inexistente demorem o mesmo.
 *
 * Os dois últimos itens eram código nosso: a classe `Autenticador` conferia
 * `password_needs_rehash` e comparava contra um hash de mentira para não
 * entregar, pelo tempo de resposta, quais contas existem. O `Timebox` faz
 * isso melhor — ele cobre o caminho inteiro, não só a conferência da senha.
 */
final class AcessoController extends Controller
{
    public function formulario(): View
    {
        return view('acesso.entrar');
    }

    public function entrar(EntrarRequest $requisicao): RedirectResponse
    {
        if (!Auth::attempt($requisicao->credenciais())) {
            // Uma mensagem só, para não dizer se foi o e-mail ou a senha:
            // duas mensagens diferentes entregam a lista de contas a quem
            // ficar tentando.
            return back()
                ->withInput($requisicao->only('email'))
                ->with('erro', 'E-mail ou senha incorretos.');
        }

        // Troca o id da sessão: sem isso, um id capturado antes do login
        // continuaria valendo depois dele.
        $requisicao->session()->regenerate();

        return redirect()
            ->intended(route('agenda'))
            ->with('mensagem', 'Bem-vindo, ' . Auth::user()?->nome . '.');
    }

    public function sair(Request $requisicao): RedirectResponse
    {
        Auth::logout();

        $requisicao->session()->invalidate();
        $requisicao->session()->regenerateToken();

        return redirect()->route('acesso.formulario');
    }
}
