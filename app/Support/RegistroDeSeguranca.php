<?php

declare(strict_types=1);

namespace App\Support;

use App\Dominio\Usuario\Papel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * O que aconteceu com o acesso, num arquivo separado do log da aplicação.
 *
 * Log de segurança serve para responder perguntas depois do fato: quem
 * entrou, de onde, quantas vezes errou a senha, quem tentou fazer o que não
 * podia. Sem ele, um acesso indevido não deixa rastro nenhum — e num
 * sistema que sustenta laudo de estrutura, "quem lançou esse resultado" é
 * uma pergunta que vai ser feita.
 *
 * Centralizar aqui não é organização: é o que permite garantir, num lugar
 * só, que **a senha nunca entra no log**. Espalhado pelos controladores,
 * basta um `Log::info($request->all())` distraído para o arquivo virar uma
 * lista de credenciais em texto puro.
 */
final class RegistroDeSeguranca
{
    private const CANAL = 'seguranca';

    private function __construct()
    {
    }

    public static function loginAceito(string $email, Papel|string $papel, Request $requisicao): void
    {
        self::registrar('login.aceito', $requisicao, [
            'email' => $email,
            'papel' => $papel instanceof Papel ? $papel->value : $papel,
        ]);
    }

    public static function loginRecusado(string $email, Request $requisicao): void
    {
        self::registrar('login.recusado', $requisicao, ['email' => $email]);
    }

    public static function limiteDeTentativas(string $email, int $segundos, Request $requisicao): void
    {
        self::registrar('login.bloqueado', $requisicao, [
            'email' => $email,
            'liberado_em_segundos' => $segundos,
        ]);
    }

    public static function saida(string $email, Request $requisicao): void
    {
        self::registrar('saida', $requisicao, ['email' => $email]);
    }

    public static function contaDesativada(string $email, Request $requisicao): void
    {
        self::registrar('conta.desativada', $requisicao, ['email' => $email]);
    }

    public static function acessoNegado(?string $email, Request $requisicao): void
    {
        self::registrar('acesso.negado', $requisicao, [
            'email' => $email ?? '(sem sessão)',
            'alvo' => $requisicao->method() . ' ' . $requisicao->path(),
        ]);
    }

    /**
     * O IP e o agente entram sempre; a senha, nunca.
     *
     * @param array<string, mixed> $contexto
     */
    private static function registrar(string $evento, Request $requisicao, array $contexto): void
    {
        Log::channel(self::CANAL)->info($evento, $contexto + [
            'ip' => $requisicao->ip(),
            'agente' => substr((string) $requisicao->userAgent(), 0, 200),
        ]);
    }
}
