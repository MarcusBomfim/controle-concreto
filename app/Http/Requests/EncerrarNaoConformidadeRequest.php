<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dominio\NaoConformidade\Desfecho;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * O desfecho e o parecer que encerram o tratamento.
 *
 * `Rule::enum` garante que o desfecho existe; que ele está **sustentado**
 * pelas providências registradas é a entidade quem decide — a tela só oferece
 * os possíveis, mas um POST não vem da tela, vem do navegador.
 */
final class EncerrarNaoConformidadeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'desfecho' => ['required', Rule::enum(Desfecho::class)],
            'parecer' => ['required', 'string', 'max:2000'],
        ];
    }

    public function desfecho(): Desfecho
    {
        return Desfecho::from((string) $this->validated('desfecho'));
    }

    public function parecer(): string
    {
        return (string) $this->validated('parecer');
    }
}
