<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\NaoConformidade;

/**
 * Como a não conformidade termina. São três saídas, e cada uma precisa de
 * uma providência favorável que a sustente — o desfecho é conclusão, não
 * opinião.
 */
enum Desfecho: string
{
    case EstruturaAceita = 'estrutura_aceita';
    case Reforcada = 'reforcada';
    case Demolida = 'demolida';

    public function rotulo(): string
    {
        return match ($this) {
            self::EstruturaAceita => 'Estrutura aceita',
            self::Reforcada => 'Peça reforçada',
            self::Demolida => 'Peça demolida e refeita',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::EstruturaAceita => 'A revisão do projeto, os testemunhos ou a prova de carga mostraram que a peça atende como está.',
            self::Reforcada => 'A peça recebeu reforço projetado para a resistência que se obteve.',
            self::Demolida => 'A peça foi demolida e concretada de novo; o concreto novo passa por controle próprio.',
        };
    }

    /**
     * Quais providências, com resultado favorável, sustentam este desfecho.
     *
     * @return TipoDeProvidencia[]
     */
    public function providenciasQueSustentam(): array
    {
        return match ($this) {
            self::EstruturaAceita => [
                TipoDeProvidencia::RevisaoDeProjeto,
                TipoDeProvidencia::ExtracaoDeTestemunhos,
                TipoDeProvidencia::ProvaDeCarga,
            ],
            self::Reforcada => [TipoDeProvidencia::Reforco],
            self::Demolida => [TipoDeProvidencia::Demolicao],
        };
    }
}
