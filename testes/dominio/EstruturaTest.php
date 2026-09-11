<?php

declare(strict_types=1);

use ControleConcreto\Dominio\Concreto\Abatimento;
use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\Estrutura\ElementoEstrutural;
use ControleConcreto\Dominio\Estrutura\TipoDeElemento;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Obra\Obra;

function lajeDeTeste(float $volume = 42.0): ElementoEstrutural
{
    return new ElementoEstrutural(
        'l3-p4',
        TipoDeElemento::Laje,
        'Laje L3',
        '4º pavimento',
        ClasseDeResistencia::C30,
        new Abatimento(100),
        $volume,
    );
}

grupo('Obra');

teste('normaliza código e registro para maiúsculas', function (): void {
    $obra = new Obra('obr-2026-007', 'Edifício Vista Serra', 'Construtora Vale Verde', 'Marcus Bomfim', 'crea-sp 123456/d');

    igual('OBR-2026-007', $obra->codigo);
    igual('CREA-SP 123456/D', $obra->registroProfissional);
});

teste('recusa campo obrigatório em branco', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => new Obra('OBR-1', '', 'Cliente', 'Responsável', 'CREA-SP 1/D'),
        'Nome da obra é obrigatório',
    );
});

grupo('Tipo de elemento');

teste('pilar e parede têm lote menor que laje', function (): void {
    igual(50, TipoDeElemento::Pilar->volumeMaximoDoLoteEmM3());
    igual(50, TipoDeElemento::Parede->volumeMaximoDoLoteEmM3());
    igual(100, TipoDeElemento::Laje->volumeMaximoDoLoteEmM3());
    igual(100, TipoDeElemento::Fundacao->volumeMaximoDoLoteEmM3());
});

teste('piso pode ser concreto simples; pilar não', function (): void {
    falso(TipoDeElemento::Piso->exigeConcretoEstrutural(), 'piso');
    verdadeiro(TipoDeElemento::Pilar->exigeConcretoEstrutural(), 'pilar');
});

grupo('Elemento estrutural');

teste('guarda a especificação de projeto', function (): void {
    $laje = lajeDeTeste();

    igual('L3-P4', $laje->codigo);
    igual(ClasseDeResistencia::C30, $laje->classe);
    igualAproximado(30.0, $laje->fckDeProjeto());
    igual(100, $laje->abatimento->especificadoEmMm);
});

teste('monta a identificação com o pavimento', function (): void {
    igual('L3-P4 — Laje L3 (4º pavimento)', lajeDeTeste()->identificacao());
});

teste('pavimento em branco vira nulo', function (): void {
    $sapata = new ElementoEstrutural(
        'SAP-01',
        TipoDeElemento::Fundacao,
        'Sapata S1',
        '   ',
        ClasseDeResistencia::C25,
        new Abatimento(80),
        3.5,
    );

    igual(null, $sapata->pavimento);
    igual('SAP-01 — Sapata S1', $sapata->identificacao());
});

teste('elemento estrutural recusa C15', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => new ElementoEstrutural(
            'P1',
            TipoDeElemento::Pilar,
            'Pilar P1',
            'Térreo',
            ClasseDeResistencia::C15,
            new Abatimento(100),
            1.2,
        ),
        'exige concreto estrutural',
    );
});

teste('piso aceita C15', function (): void {
    $piso = new ElementoEstrutural(
        'PISO-01',
        TipoDeElemento::Piso,
        'Piso do estacionamento',
        'Subsolo',
        ClasseDeResistencia::C15,
        new Abatimento(80),
        60.0,
    );

    igual(ClasseDeResistencia::C15, $piso->classe);
});

teste('estima os lotes pelo volume e pelo tipo', function (): void {
    igual(1, lajeDeTeste(42.0)->lotesPrevistos(), '42 m³ de laje cabe em um lote de 100');
    igual(2, lajeDeTeste(142.0)->lotesPrevistos(), '142 m³ de laje precisa de dois');

    $pilares = new ElementoEstrutural(
        'P-T',
        TipoDeElemento::Pilar,
        'Pilares do térreo',
        'Térreo',
        ClasseDeResistencia::C35,
        new Abatimento(120),
        120.0,
    );

    igual(3, $pilares->lotesPrevistos(), '120 m³ de pilar em lotes de 50');
});

teste('recusa volume zerado', function (): void {
    lanca(ExcecaoDeDominio::class, static fn () => lajeDeTeste(0.0), 'Volume previsto');
});
