<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\NaoConformidade;

/**
 * O que o engenheiro pode fazer diante de um lote reprovado.
 *
 * A sequência é a da NBR 12655 e da seção de não conformidades da NBR 6118,
 * do mais barato ao mais caro: primeiro rever o projeto com o fck que se
 * obteve; se não fechar, ir buscar a resistência real na peça — ensaio não
 * destrutivo para localizar, testemunho extraído para medir, prova de carga
 * para comprovar; e só no fim reforçar ou demolir. Transcrito de memória;
 * confira com o texto vigente.
 */
enum TipoDeProvidencia: string
{
    case RevisaoDeProjeto = 'revisao_de_projeto';
    case EnsaioNaoDestrutivo = 'ensaio_nao_destrutivo';
    case ExtracaoDeTestemunhos = 'extracao_de_testemunhos';
    case ProvaDeCarga = 'prova_de_carga';
    case Reforco = 'reforco';
    case Demolicao = 'demolicao';

    public function rotulo(): string
    {
        return match ($this) {
            self::RevisaoDeProjeto => 'Revisão do projeto',
            self::EnsaioNaoDestrutivo => 'Ensaio não destrutivo',
            self::ExtracaoDeTestemunhos => 'Extração de testemunhos',
            self::ProvaDeCarga => 'Prova de carga',
            self::Reforco => 'Reforço',
            self::Demolicao => 'Demolição',
        };
    }

    public function descricao(): string
    {
        return match ($this) {
            self::RevisaoDeProjeto => 'O projetista verifica se a estrutura atende com o fck estimado no lugar do fck de projeto.',
            self::EnsaioNaoDestrutivo => 'Esclerometria ou ultrassom para localizar as regiões de menor resistência. Não mede o fck; orienta a extração.',
            self::ExtracaoDeTestemunhos => 'Testemunhos extraídos da peça e rompidos (NBR 7680): a resistência real do concreto que está lá.',
            self::ProvaDeCarga => 'Carregamento controlado da peça para comprovar o desempenho estrutural.',
            self::Reforco => 'Reforço da peça, projetado para o fck que se obteve.',
            self::Demolicao => 'Demolição e reconstrução da peça.',
        };
    }

    /** Só o testemunho extraído devolve um fck medido na peça. */
    public function informaResistencia(): bool
    {
        return $this === self::ExtracaoDeTestemunhos;
    }
}
