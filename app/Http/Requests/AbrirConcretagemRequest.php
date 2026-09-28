<?php

declare(strict_types=1);

namespace App\Http\Requests;

use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;

final class AbrirConcretagemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'elemento' => ['required', 'string', 'max:30'],

            // "A data ainda não chegou" também é regra da entidade. Aqui a
            // checagem existe para o formulário poder apontar o campo; lá
            // ela existe para valer em qualquer caminho.
            'data' => ['required', 'date', 'before_or_equal:today'],

            'fornecedor' => ['required', 'string', 'max:120'],
            'responsavel' => ['required', 'string', 'max:160'],
        ];
    }

    public function dataDaConcretagem(): DateTimeImmutable
    {
        return new DateTimeImmutable((string) $this->validated('data'));
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'elemento' => 'elemento estrutural',
            'data' => 'data da concretagem',
            'responsavel' => 'responsável pela concretagem',
        ];
    }
}
