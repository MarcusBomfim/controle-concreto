<?php

declare(strict_types=1);

use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Lote\CalculadoraDeFckEstimado;
use ControleConcreto\Dominio\Lote\CondicaoDePreparo;
use ControleConcreto\Dominio\Lote\Psi6;
use ControleConcreto\Dominio\Lote\TipoDeAmostragem;

/*
 * Os valores esperados abaixo foram calculados à mão a partir das fórmulas
 * como transcritas em CalculadoraDeFckEstimado. Se a norma vigente disser
 * outra coisa, a calculadora e estes números mudam juntos.
 */

grupo('ψ6: a tabela');

teste('lê a tabela por n e condição', function (): void {
    igualAproximado(0.86, Psi6::para(6, CondicaoDePreparo::A));
    igualAproximado(0.81, Psi6::para(6, CondicaoDePreparo::B));
    igualAproximado(0.81, Psi6::para(6, CondicaoDePreparo::C), 'B e C dividem a linha');
    igualAproximado(0.91, Psi6::para(16, CondicaoDePreparo::A));
});

teste('n intermediário usa o maior n tabulado abaixo — o lado conservador', function (): void {
    igualAproximado(0.87, Psi6::para(9, CondicaoDePreparo::A), 'usa n = 8');
    igualAproximado(0.88, Psi6::para(11, CondicaoDePreparo::A), 'usa n = 10');
});

teste('acima de 16 vale o de 16', function (): void {
    igualAproximado(0.89, Psi6::para(100, CondicaoDePreparo::C));
});

teste('recusa n menor que 2', function (): void {
    lanca(ExcecaoDeDominio::class, static fn () => Psi6::para(1, CondicaoDePreparo::A));
});

grupo('fck estimado: amostragem parcial');

teste('n = 6: fórmula 2·(f1+f2)/2 − f3, com piso ψ6·f1', function (): void {
    // Ordenado: 29,8 30,5 31,1 32,4 33,6 35,0 → m = 3
    // fórmula = 2·(29,8 + 30,5)/2 − 31,1 = 60,3 − 31,1 = 29,2
    // piso    = 0,86 × 29,8 = 25,6
    $e = CalculadoraDeFckEstimado::calcular(
        [32.4, 29.8, 31.1, 33.6, 30.5, 35.0],
        TipoDeAmostragem::Parcial,
        CondicaoDePreparo::A,
    );

    igualAproximado(29.2, $e->fckEstimadoEmMPa);
    igualAproximado(29.2, $e->valorDaFormula ?? 0.0);
    igualAproximado(0.86, $e->psi6 ?? 0.0);
    igualAproximado(25.6, $e->pisoDoPsi6 ?? 0.0);
    falso($e->pisoPrevaleceu(), 'a fórmula venceu');
    igual([29.8, 30.5, 31.1, 32.4, 33.6, 35.0], $e->valoresOrdenados, 'ordenou');
    igual(6, $e->numeroDeExemplares);
});

teste('o piso de ψ6 prevalece quando a fórmula cai demais', function (): void {
    // Ordenado: 30,0 30,2 36,0 36,5 37,0 38,0 → m = 3
    // fórmula = 2·(30,0 + 30,2)/2 − 36,0 = 60,2 − 36,0 = 24,2
    // piso    = 0,86 × 30,0 = 25,8  → vale o piso
    $e = CalculadoraDeFckEstimado::calcular(
        [30.0, 30.2, 36.0, 36.5, 37.0, 38.0],
        TipoDeAmostragem::Parcial,
        CondicaoDePreparo::A,
    );

    igualAproximado(25.8, $e->fckEstimadoEmMPa);
    igualAproximado(24.2, $e->valorDaFormula ?? 0.0);
    verdadeiro($e->pisoPrevaleceu(), 'o piso venceu');
});

teste('n ímpar despreza o maior valor', function (): void {
    // n = 7 → m = 3. O 40,0 nem entra na conta.
    // fórmula = 2·(28 + 29)/2 − 30 = 57 − 30 = 27,0
    // piso    = 0,87 × 28 = 24,4
    $e = CalculadoraDeFckEstimado::calcular(
        [40.0, 33.0, 28.0, 31.0, 29.0, 32.0, 30.0],
        TipoDeAmostragem::Parcial,
        CondicaoDePreparo::A,
    );

    igualAproximado(27.0, $e->fckEstimadoEmMPa);
    igualAproximado(0.87, $e->psi6 ?? 0.0, 'ψ6 de n = 7');
});

teste('a condição de preparo muda o piso', function (): void {
    // Mesmos dados do primeiro caso, condição C: ψ6 = 0,81 → piso = 24,1.
    $e = CalculadoraDeFckEstimado::calcular(
        [32.4, 29.8, 31.1, 33.6, 30.5, 35.0],
        TipoDeAmostragem::Parcial,
        CondicaoDePreparo::C,
    );

    igualAproximado(0.81, $e->psi6 ?? 0.0);
    igualAproximado(24.1, $e->pisoDoPsi6 ?? 0.0);
    igualAproximado(29.2, $e->fckEstimadoEmMPa, 'a fórmula continua vencendo');
});

teste('menos de 6 exemplares é amostra insuficiente', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => CalculadoraDeFckEstimado::calcular(
            [30.0, 31.0, 32.0, 33.0, 34.0],
            TipoDeAmostragem::Parcial,
            CondicaoDePreparo::A,
        ),
        'ao menos 6 exemplares',
    );
});

teste('n = 20 na parcial já usa o percentil', function (): void {
    // i = ⌈0,05 × 20⌉ = 1 → f1.
    $valores = range(30.0, 49.0, 1.0);

    $e = CalculadoraDeFckEstimado::calcular($valores, TipoDeAmostragem::Parcial, CondicaoDePreparo::A);

    igualAproximado(30.0, $e->fckEstimadoEmMPa);
    igual(null, $e->psi6, 'sem piso no percentil');
});

grupo('fck estimado: amostragem total');

teste('n ≤ 20: vale o menor exemplar', function (): void {
    $e = CalculadoraDeFckEstimado::calcular(
        [31.0, 28.5, 33.0, 30.0],
        TipoDeAmostragem::Total,
        CondicaoDePreparo::A,
    );

    igualAproximado(28.5, $e->fckEstimadoEmMPa);
    igual(null, $e->valorDaFormula);
});

teste('n > 20: vale o percentil de 5 %', function (): void {
    // 25 valores de 25,0 a 49,0. i = ⌈0,05 × 25⌉ = ⌈1,25⌉ = 2 → f2 = 26,0.
    $valores = range(25.0, 49.0, 1.0);

    $e = CalculadoraDeFckEstimado::calcular($valores, TipoDeAmostragem::Total, CondicaoDePreparo::A);

    igualAproximado(26.0, $e->fckEstimadoEmMPa);
    igual(25, $e->numeroDeExemplares);
});

teste('n = 40: i = 2', function (): void {
    // ⌈0,05 × 40⌉ = ⌈2,0⌉ = 2 → f2.
    $valores = range(20.0, 59.0, 1.0);

    igualAproximado(21.0, CalculadoraDeFckEstimado::calcular($valores, TipoDeAmostragem::Total, CondicaoDePreparo::A)->fckEstimadoEmMPa);
});

teste('sem exemplar não há conta', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => CalculadoraDeFckEstimado::calcular([], TipoDeAmostragem::Total, CondicaoDePreparo::A),
        'Não há exemplar',
    );
});

grupo('Estimativa: memória de cálculo');

teste('sabe se atende o fck de projeto', function (): void {
    $e = CalculadoraDeFckEstimado::calcular(
        [32.4, 29.8, 31.1, 33.6, 30.5, 35.0],
        TipoDeAmostragem::Parcial,
        CondicaoDePreparo::A,
    );

    verdadeiro($e->atende(25.0), '29,2 atende C25');
    falso($e->atende(30.0), '29,2 não atende C30');
    igualAproximado(29.8, $e->menorExemplar());
    igualAproximado(35.0, $e->maiorExemplar());
    igualAproximado(32.1, $e->media());
});
