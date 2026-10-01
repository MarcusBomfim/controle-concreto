<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * As credenciais de entrada — e o limite de tentativas.
 *
 * Senha boa não protege de nada se der para tentar um milhão de vezes. O
 * limite é o que transforma "senha de 8 caracteres" em algo que não se
 * quebra por força bruta pela porta da frente.
 */
final class EntrarRequest extends FormRequest
{
    /** Tentativas para o par e-mail + IP antes de bloquear. */
    private const TENTATIVAS_POR_CONTA = 5;

    /** Tentativas para o IP inteiro, qualquer que seja o e-mail. */
    private const TENTATIVAS_POR_IP = 20;

    private const JANELA_DA_CONTA_EM_SEGUNDOS = 60;

    private const JANELA_DO_IP_EM_SEGUNDOS = 300;

    /** Quantos segundos faltam, segundo o contador que de fato bloqueou. */
    private ?int $segundosDeBloqueio = null;

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
            'email' => $this->emailNormalizado(),
            'password' => (string) $this->validated('senha'),
            'ativo' => true,
        ];
    }

    public function emailNormalizado(): string
    {
        return mb_strtolower(trim((string) $this->validated('email')));
    }

    /**
     * Recusa a tentativa se o limite já estourou.
     *
     * São dois contadores, de propósito:
     *
     *  - **e-mail + IP**: 5 tentativas por minuto. Segura a força bruta
     *    contra uma conta específica. Contar só por e-mail deixaria
     *    qualquer um trancar a conta alheia de fora — negação de serviço
     *    disfarçada de segurança.
     *  - **IP sozinho**: 20 tentativas em 5 minutos. Esse é o que fecha o
     *    buraco do primeiro: sem ele, um atacante varre mil e-mails
     *    diferentes do mesmo IP sem nunca estourar o contador de nenhum.
     *
     * Erro de validação, e não exceção genérica: a mensagem volta presa ao
     * campo de e-mail, como qualquer outro erro do formulário.
     */
    public function garantirQueNaoEstourouOLimite(): void
    {
        foreach ([$this->chaveDaConta() => self::TENTATIVAS_POR_CONTA, $this->chaveDoIp() => self::TENTATIVAS_POR_IP] as $chave => $maximo) {
            if (!RateLimiter::tooManyAttempts($chave, $maximo)) {
                continue;
            }

            event(new Lockout($this));

            $segundos = RateLimiter::availableIn($chave);
            $this->segundosDeBloqueio = $segundos;

            throw ValidationException::withMessages([
                'email' => $segundos >= 60
                    ? sprintf('Muitas tentativas. Tente de novo em %d minuto(s).', (int) ceil($segundos / 60))
                    : sprintf('Muitas tentativas. Tente de novo em %d segundos.', $segundos),
            ]);
        }
    }

    public function contarTentativaFalha(): void
    {
        RateLimiter::hit($this->chaveDaConta(), self::JANELA_DA_CONTA_EM_SEGUNDOS);
        RateLimiter::hit($this->chaveDoIp(), self::JANELA_DO_IP_EM_SEGUNDOS);
    }

    /** Entrou: o contador da conta zera. O do IP não — ele vigia o conjunto. */
    public function zerarTentativas(): void
    {
        RateLimiter::clear($this->chaveDaConta());
    }

    /**
     * O mesmo número que foi mostrado a quem tentou entrar.
     *
     * Antes isto devolvia o máximo entre os dois contadores, e o log dizia
     * "293 segundos" enquanto a tela dizia "53" — o contador do IP tinha
     * janela maior, mas não era ele que estava bloqueando. Log que
     * discorda da tela é pior do que log nenhum: na investigação, é ele
     * que vai ser levado a sério.
     */
    public function segundosAteLiberar(): int
    {
        return $this->segundosDeBloqueio ?? 0;
    }

    private function chaveDaConta(): string
    {
        // transliterate: o e-mail entra numa chave de cache, e acento ali
        // já deu dor de cabeça em driver que não aceita UTF-8 na chave.
        return 'entrar:' . Str::transliterate($this->emailNormalizado()) . '|' . $this->ip();
    }

    private function chaveDoIp(): string
    {
        return 'entrar-ip:' . $this->ip();
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['email' => 'e-mail', 'senha' => 'senha'];
    }
}
