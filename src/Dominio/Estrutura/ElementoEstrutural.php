<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Estrutura;

use ControleConcreto\Dominio\Concreto\Abatimento;
use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Regras;

/**
 * Uma peça da estrutura que será concretada: "Laje L3 do 4º pavimento".
 *
 * Carrega a especificação do projeto — classe de resistência e abatimento —
 * que é a referência contra a qual cada caminhão e cada corpo de prova serão
 * julgados. A peça é a unidade do controle: é ela que passa ou não passa.
 */
final class ElementoEstrutural
{
    public readonly string $codigo;
    public readonly TipoDeElemento $tipo;
    public readonly string $descricao;
    public readonly ?string $pavimento;
    public readonly ClasseDeResistencia $classe;
    public readonly Abatimento $abatimento;
    public readonly float $volumePrevistoEmM3;

    public function __construct(
        string $codigo,
        TipoDeElemento $tipo,
        string $descricao,
        ?string $pavimento,
        ClasseDeResistencia $classe,
        Abatimento $abatimento,
        float $volumePrevistoEmM3,
    ) {
        $this->codigo = strtoupper(Regras::textoObrigatorio($codigo, 'Código do elemento', 30));
        $this->tipo = $tipo;
        $this->descricao = Regras::textoObrigatorio($descricao, 'Descrição do elemento', 200);

        $pavimentoLimpo = $pavimento === null ? '' : trim($pavimento);
        $this->pavimento = $pavimentoLimpo === '' ? null : $pavimentoLimpo;

        if ($tipo->exigeConcretoEstrutural() && !$classe->ehEstrutural()) {
            throw new ExcecaoDeDominio(sprintf(
                '%s exige concreto estrutural, e %s não é: a NBR 6118 pede no mínimo C20.',
                $tipo->rotulo(),
                $classe->rotulo(),
            ));
        }

        $this->classe = $classe;
        $this->abatimento = $abatimento;
        $this->volumePrevistoEmM3 = Regras::numeroPositivo($volumePrevistoEmM3, 'Volume previsto');
    }

    public function fckDeProjeto(): float
    {
        return $this->classe->fck();
    }

    public function identificacao(): string
    {
        $base = "{$this->codigo} — {$this->descricao}";

        return $this->pavimento === null ? $base : "{$base} ({$this->pavimento})";
    }

    /** Quantos lotes de aceitação o volume previsto vai gerar, no mínimo. */
    public function lotesPrevistos(): int
    {
        return (int) ceil($this->volumePrevistoEmM3 / $this->tipo->volumeMaximoDoLoteEmM3());
    }
}
