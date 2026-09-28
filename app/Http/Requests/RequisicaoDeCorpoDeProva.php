<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * O que romper e descartar têm em comum: voltar para onde se estava.
 *
 * O mesmo formulário aparece na agenda do laboratório e na tela da
 * concretagem, e o campo `voltar` diz de onde veio. Só se aceita caminho
 * local: um destino absoluto vindo do formulário seria um redirecionamento
 * aberto — a pessoa clica em "Registrar" no nosso domínio e o navegador a
 * larga em outro site. `//evil.example` também é absoluto, por isso as duas
 * checagens.
 */
abstract class RequisicaoDeCorpoDeProva extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    final public function rules(): array
    {
        return ['voltar' => ['nullable', 'string', 'max:300']] + $this->regras();
    }

    /** @return array<string, list<mixed>> */
    abstract protected function regras(): array;

    public function destino(): string
    {
        $voltar = (string) $this->validated('voltar', '');

        return str_starts_with($voltar, '/') && !str_starts_with($voltar, '//')
            ? $voltar
            : route('agenda');
    }
}
