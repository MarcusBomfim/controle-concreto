<?php

declare(strict_types=1);

use ControleConcreto\Dominio\Concreto\Abatimento;
use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\ExcecaoDeDominio;

grupo('Classe de resistência');

teste('o valor do caso é o fck em MPa', function (): void {
    igualAproximado(30.0, ClasseDeResistencia::C30->fck());
    igual('C30', ClasseDeResistencia::C30->rotulo());
});

teste('encontra a classe pelo fck', function (): void {
    igual(ClasseDeResistencia::C25, ClasseDeResistencia::deFck(25));
});

teste('recusa fck fora das classes da norma', function (): void {
    // A NBR 8953 não tem C27, e acima de C60 pula de 10 em 10: não há C65.
    lanca(ExcecaoDeDominio::class, static fn () => ClasseDeResistencia::deFck(27), 'Não existe classe');
    lanca(ExcecaoDeDominio::class, static fn () => ClasseDeResistencia::deFck(65), 'Não existe classe');
});

teste('C15 não é estrutural; C20 em diante é', function (): void {
    falso(ClasseDeResistencia::C15->ehEstrutural(), 'C15');
    verdadeiro(ClasseDeResistencia::C20->ehEstrutural(), 'C20');
    verdadeiro(ClasseDeResistencia::C50->ehEstrutural(), 'C50');
});

teste('grupo II é alto desempenho', function (): void {
    falso(ClasseDeResistencia::C50->ehDeAltoDesempenho(), 'C50 é grupo I');
    verdadeiro(ClasseDeResistencia::C55->ehDeAltoDesempenho(), 'C55 é grupo II');
});

grupo('Abatimento');

teste('tolerância cresce com o abatimento especificado', function (): void {
    igual(10, (new Abatimento(80))->toleranciaEmMm(), 'até 90 mm');
    igual(20, (new Abatimento(100))->toleranciaEmMm(), '100 a 150 mm');
    igual(20, (new Abatimento(150))->toleranciaEmMm(), 'limite da faixa');
    igual(30, (new Abatimento(160))->toleranciaEmMm(), '160 em diante');
});

teste('aceita medida dentro da faixa e recusa fora', function (): void {
    $abatimento = new Abatimento(100);

    verdadeiro($abatimento->aceita(80), 'limite inferior');
    verdadeiro($abatimento->aceita(100), 'exato');
    verdadeiro($abatimento->aceita(120), 'limite superior');
    falso($abatimento->aceita(79), 'um abaixo');
    falso($abatimento->aceita(121), 'um acima');
});

teste('descreve a faixa aceita', function (): void {
    igual('100 ± 20 mm', (new Abatimento(100))->faixa());
    igual(80, (new Abatimento(100))->minimoAceito());
    igual(120, (new Abatimento(100))->maximoAceito());
});

teste('recusa abatimento fora da faixa usual', function (): void {
    lanca(ExcecaoDeDominio::class, static fn () => new Abatimento(5), 'fora da faixa');
    lanca(ExcecaoDeDominio::class, static fn () => new Abatimento(300), 'fora da faixa');
});

teste('compara pelo valor', function (): void {
    verdadeiro((new Abatimento(100))->ehIgualA(new Abatimento(100)), 'iguais');
    falso((new Abatimento(100))->ehIgualA(new Abatimento(120)), 'diferentes');
});
