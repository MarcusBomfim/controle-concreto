<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Dominio\NaoConformidade\Providencia;
use App\Dominio\NaoConformidade\ResultadoDaProvidencia;
use App\Dominio\NaoConformidade\TipoDeProvidencia;
use DateTimeImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Um passo do tratamento do lote reprovado.
 *
 * O que este Form Request **não** confere, de propósito: o tamanho mínimo da
 * descrição, o fck obrigatório no testemunho, o fck proibido nos outros
 * tipos, e "ensaio não destrutivo só pode ser informativo". São regras com
 * razão de norma atrás, e a razão está escrita na `Providencia`. Repeti-las
 * aqui as duplicaria sem o porquê — e a mensagem da entidade é melhor do que
 * "o campo descrição deve ter no mínimo 20 caracteres".
 */
final class RegistrarProvidenciaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, list<mixed>> */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::enum(TipoDeProvidencia::class)],
            'realizada_em' => ['required', 'date', 'before_or_equal:today'],
            'resultado' => ['required', Rule::enum(ResultadoDaProvidencia::class)],
            'descricao' => ['required', 'string', 'max:2000'],
            'responsavel' => ['required', 'string', 'max:160'],
            'fck_obtido_mpa' => ['nullable', 'numeric', 'gt:0'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $fck = $this->input('fck_obtido_mpa');

        if (is_scalar($fck)) {
            $texto = str_replace(',', '.', (string) $fck);

            // Campo em branco é "não informado", não "zero".
            $this->merge(['fck_obtido_mpa' => trim($texto) === '' ? null : $texto]);
        }
    }

    /** Monta a providência; a entidade é quem recusa o que não fecha. */
    public function providencia(): Providencia
    {
        $fck = $this->validated('fck_obtido_mpa');

        return new Providencia(
            TipoDeProvidencia::from((string) $this->validated('tipo')),
            new DateTimeImmutable((string) $this->validated('realizada_em')),
            (string) $this->validated('descricao'),
            ResultadoDaProvidencia::from((string) $this->validated('resultado')),
            (string) $this->validated('responsavel'),
            $fck === null ? null : (float) $fck,
        );
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'tipo' => 'tipo da providência',
            'realizada_em' => 'data da providência',
            'descricao' => 'descrição',
            'fck_obtido_mpa' => 'fck obtido',
        ];
    }
}
