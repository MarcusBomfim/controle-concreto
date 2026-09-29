<?php

declare(strict_types=1);

namespace Tests\Apoio;

use App\Dominio\Usuario\Papel;
use App\Dominio\Usuario\RepositorioDeUsuarios;
use App\Dominio\Usuario\Usuario;
use App\Models\Conta;

/**
 * Contas de teste e o login delas.
 *
 * As contas são criadas pelo domínio — `Usuario::criar` gera o hash — e o
 * login é feito com `actingAs`, que põe a conta no guard sem passar pelo
 * formulário. Quem testa o formulário é o AcessoTest.
 */
trait Autenticacao
{
    protected function conta(Papel $papel, string $senha = 'Segredo@123'): Conta
    {
        $email = $papel->value . '@teste.dev';

        app(RepositorioDeUsuarios::class)->salvar(
            Usuario::criar($email, $papel->rotulo() . ' de teste', $papel, $senha),
        );

        return Conta::query()->findOrFail($email);
    }

    /** Acesso completo: cadastros, concretagens, laboratório, lotes e não conformidades. */
    protected function comoEngenheiro(): Conta
    {
        $conta = $this->conta(Papel::Engenheiro);
        $this->actingAs($conta);

        return $conta;
    }

    /** Opera, mas não decide: concretagem e prensa, sem lote. */
    protected function comoLaboratorista(): Conta
    {
        $conta = $this->conta(Papel::Laboratorista);
        $this->actingAs($conta);

        return $conta;
    }

    /** Somente leitura. */
    protected function comoGestor(): Conta
    {
        $conta = $this->conta(Papel::Gestor);
        $this->actingAs($conta);

        return $conta;
    }
}
