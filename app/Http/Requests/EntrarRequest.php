<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class EntrarRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:160'],
            'senha' => ['required', 'string'],
        ];
    }

    /**
     * As credenciais no formato que o guard espera.
     *
     * `password` é o nome que o `EloquentUserProvider` reconhece — ele
     * separa a senha das demais chaves antes de montar a consulta. As
     * outras viram cláusulas `where`, e é por isso que `ativo` pode entrar
     * aqui: conta desativada simplesmente não é encontrada.
     *
     * @return array<string, mixed>
     */
    public function credenciais(): array
    {
        return [
            'email' => mb_strtolower(trim((string) $this->validated('email'))),
            'password' => (string) $this->validated('senha'),
            'ativo' => true,
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['email' => 'e-mail', 'senha' => 'senha'];
    }
}
