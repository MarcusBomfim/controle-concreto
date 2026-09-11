<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio;

/** Validações compartilhadas. Cada método devolve o valor já normalizado. */
final class Regras
{
    /**
     * Resistência de concreto se mede em MPa com uma casa decimal; abatimento,
     * em milímetros inteiros. Uma tolerância de 0,01 cobre o arredondamento de
     * ponto flutuante sem esconder diferença que importe.
     */
    public const TOLERANCIA = 0.01;

    private function __construct()
    {
    }

    public static function textoObrigatorio(string $valor, string $campo, int $tamanhoMaximo): string
    {
        $limpo = trim($valor);

        if ($limpo === '') {
            throw new ExcecaoDeDominio("{$campo} é obrigatório.");
        }

        if (mb_strlen($limpo) > $tamanhoMaximo) {
            throw new ExcecaoDeDominio("{$campo} pode ter no máximo {$tamanhoMaximo} caracteres.");
        }

        return $limpo;
    }

    public static function numeroPositivo(float $valor, string $campo): float
    {
        if ($valor <= 0) {
            throw new ExcecaoDeDominio("{$campo} precisa ser maior que zero.");
        }

        return $valor;
    }

    public static function inteiroPositivo(int $valor, string $campo): int
    {
        if ($valor <= 0) {
            throw new ExcecaoDeDominio("{$campo} precisa ser maior que zero.");
        }

        return $valor;
    }

    public static function naoNegativo(float $valor, string $campo): float
    {
        if ($valor < 0) {
            throw new ExcecaoDeDominio("{$campo} não pode ser negativo.");
        }

        return $valor;
    }

    public static function maiorOuIgual(float $a, float $b): bool
    {
        return $a - $b >= -self::TOLERANCIA;
    }
}
