<?php

declare(strict_types=1);

namespace App\Support;

use DateTimeInterface;

/**
 * Formatação para as telas, num lugar só.
 *
 * Na versão em PHP puro isto era um arquivo de funções globais carregado
 * pelo autoload. Aqui é uma classe de métodos estáticos, que os templates
 * importam com `@use`. A diferença que importa não é de estilo: função
 * global não se testa sem carregar o arquivo inteiro, e estes métodos têm
 * teste próprio — formatação errada é bug visível, e já mordeu antes.
 */
final class Formato
{
    private function __construct()
    {
    }

    /** Número no padrão brasileiro: 1.234,56 */
    public static function numero(float $valor, int $casas = 2): string
    {
        return number_format($valor, $casas, ',', '.');
    }

    /** Resistência com uma casa, como pede a NBR 5739: "32,4 MPa". */
    public static function mpa(?float $valor): string
    {
        return $valor === null ? '—' : self::numero($valor, 1) . ' MPa';
    }

    public static function metrosCubicos(float $valor): string
    {
        return self::numero($valor, 1) . ' m³';
    }

    public static function data(DateTimeInterface $data): string
    {
        return $data->format('d/m/Y');
    }

    public static function dataHora(DateTimeInterface $momento): string
    {
        return $momento->format('d/m/Y H:i');
    }

    public static function hora(DateTimeInterface $momento): string
    {
        return $momento->format('H:i');
    }

    /**
     * Largura de barra de progresso, para o atributo `style`.
     *
     * Com ponto, e não vírgula: o valor vai para um `width` do CSS, e
     * "27,3%" é declaração inválida — o navegador ignora em silêncio e a
     * barra aparece cheia. Foi exatamente esse bug que passou despercebido
     * no projeto irmão até alguém abrir a tela.
     */
    public static function larguraCss(float $percentual): string
    {
        return number_format(max(0.0, min(100.0, $percentual)), 1, '.', '');
    }
}
