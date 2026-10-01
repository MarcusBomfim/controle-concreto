<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\EntrarRequest;
use App\Support\RegistroDeSeguranca;
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
 *
 * O que o framework **não** faz sozinho, e por isso está aqui: contar as
 * tentativas e registrar o que aconteceu.
 */
final class AcessoController extends Controller
{
    public function formulario(): View
    {
        return view('acesso.entrar');
    }

    public function entrar(EntrarRequest $requisicao): RedirectResponse
    {
        $email = $requisicao->emailNormalizado();

        // Antes de conferir a senha: o limite já estourou? Se sim, isto
        // lança erro de validação e a senha nem chega a ser comparada.
        try {
            $requisicao->garantirQueNaoEstourouOLimite();
        } catch (\Illuminate\Validation\ValidationException $bloqueio) {
            RegistroDeSeguranca::limiteDeTentativas($email, $requisicao->segundosAteLiberar(), $requisicao);

            throw $bloqueio;
        }

        if (!Auth::attempt($requisicao->credenciais())) {
            $requisicao->contarTentativaFalha();
            RegistroDeSeguranca::loginRecusado($email, $requisicao);

            // Uma mensagem só, para não dizer se foi o e-mail ou a senha:
            // duas mensagens diferentes entregam a lista de contas a quem
            // ficar tentando.
            return back()
                ->withInput($requisicao->only('email'))
                ->with('erro', 'E-mail ou senha incorretos.');
        }

        $requisicao->zerarTentativas();

        // Troca o id da sessão: sem isso, um id capturado antes do login
        // continuaria valendo depois dele.
        $requisicao->session()->regenerate();

        $conta = Auth::user();

        RegistroDeSeguranca::loginAceito($email, $conta?->papel ?? '-', $requisicao);

        return redirect()
            ->intended(route('agenda'))
            ->with('mensagem', 'Bem-vindo, ' . $conta?->nome . '.');
    }

    public function sair(Request $requisicao): RedirectResponse
    {
        RegistroDeSeguranca::saida((string) (Auth::user()?->email ?? ''), $requisicao);

        Auth::logout();

        $requisicao->session()->invalidate();
        $requisicao->session()->regenerateToken();

        return redirect()->route('acesso.formulario');
    }
}
