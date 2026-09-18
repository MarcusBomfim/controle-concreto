<?php

declare(strict_types=1);

namespace ControleConcreto\Web;

use ControleConcreto\Dominio\Usuario\Usuario;
use RuntimeException;

/**
 * Renderiza um template PHP dentro do layout.
 *
 * As variáveis chegam ao template por extract, e a função e() fica disponível
 * lá dentro. Escapar é obrigação de quem escreve o template — e o nome curto
 * existe justamente para não haver desculpa de esquecer.
 */
final class Visao
{
    private string $tokenDaSessao = '';
    private ?Usuario $usuarioAtual = null;

    public function __construct(private readonly string $diretorio)
    {
    }

    public static function padrao(): self
    {
        return new self(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'visoes');
    }

    /** O token anti-CSRF chega a todo template, para os formulários. */
    public function definirTokenDaSessao(string $token): void
    {
        $this->tokenDaSessao = $token;
    }

    /** Quem está logado chega a todo template como $usuarioAtual — os botões dependem do papel. */
    public function definirUsuario(?Usuario $usuario): void
    {
        $this->usuarioAtual = $usuario;
    }

    /** @param array<string, mixed> $dados */
    public function renderizar(string $template, array $dados = [], string $titulo = ''): string
    {
        $dados['token'] = $this->tokenDaSessao;
        $dados['usuarioAtual'] = $this->usuarioAtual;

        $conteudo = $this->capturar($template, $dados);

        return $this->capturar('layout', [
            'conteudo' => $conteudo,
            'titulo' => $titulo,
            'token' => $this->tokenDaSessao,
            'usuarioAtual' => $this->usuarioAtual,
            'mensagem' => $dados['mensagem'] ?? null,
            'erro' => $dados['erro'] ?? null,
        ]);
    }

    /** @param array<string, mixed> $dados */
    private function capturar(string $template, array $dados): string
    {
        $arquivo = $this->diretorio . DIRECTORY_SEPARATOR
            . str_replace('.', DIRECTORY_SEPARATOR, $template) . '.php';

        if (!is_file($arquivo)) {
            throw new RuntimeException("Template não encontrado: {$template}");
        }

        extract($dados, EXTR_SKIP);

        ob_start();

        try {
            require $arquivo;

            return (string) ob_get_clean();
        } catch (\Throwable $erro) {
            // Sem isto, um erro no meio do template deixaria o buffer aberto.
            ob_end_clean();

            throw $erro;
        }
    }
}
