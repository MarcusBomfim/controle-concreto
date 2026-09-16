<?php

declare(strict_types=1);

/*
 * Funções de apoio aos templates.
 *
 * Ficam no namespace global de propósito: os templates são PHP simples, sem
 * declaração de namespace, e chamariam o namespace global de qualquer jeito.
 * Declarar dentro de ControleConcreto\Web faria "e($x)" no template procurar
 * uma função global que não existe — erro fatal, e só em tempo de execução.
 *
 * O arquivo é carregado pelo autoload, então basta incluir src/autoload.php.
 */

if (!function_exists('e')) {
    /**
     * Escapa para HTML.
     *
     * O nome é curto porque aparece em toda interpolação de template, e
     * qualquer atrito aqui vira desculpa para esquecer — que é exatamente
     * como nasce um XSS.
     */
    function e(mixed $valor): string
    {
        return htmlspecialchars((string) $valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('numeroBr')) {
    /** Número no padrão brasileiro: 1.234,56 */
    function numeroBr(float $valor, int $casas = 2): string
    {
        return number_format($valor, $casas, ',', '.');
    }
}

if (!function_exists('mpa')) {
    /** Resistência com uma casa, como a NBR 5739 pede: 32,4 MPa */
    function mpa(?float $valor): string
    {
        return $valor === null ? '—' : numeroBr($valor, 1) . ' MPa';
    }
}

if (!function_exists('metrosCubicos')) {
    function metrosCubicos(float $valor): string
    {
        return numeroBr($valor, 1) . ' m³';
    }
}

if (!function_exists('dataBr')) {
    function dataBr(DateTimeInterface $data): string
    {
        return $data->format('d/m/Y');
    }
}

if (!function_exists('dataHoraBr')) {
    function dataHoraBr(DateTimeInterface $momento): string
    {
        return $momento->format('d/m/Y H:i');
    }
}

if (!function_exists('horaBr')) {
    function horaBr(DateTimeInterface $momento): string
    {
        return $momento->format('H:i');
    }
}

if (!function_exists('caminho')) {
    /**
     * Monta um caminho a partir dos segmentos, codificando cada um.
     *
     *   caminho('obras', 'OBR 1', 'concretagens', 3)  →  /obras/OBR%201/concretagens/3
     *
     * Os templates montam muita URL com código de obra dentro, e um código
     * com espaço ou barra quebraria a rota se fosse colado sem codificar.
     */
    function caminho(string|int ...$segmentos): string
    {
        return '/' . implode('/', array_map(
            static fn (string|int $segmento): string => rawurlencode((string) $segmento),
            $segmentos,
        ));
    }
}
