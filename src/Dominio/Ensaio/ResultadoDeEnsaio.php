<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Ensaio;

use DateTimeImmutable;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Regras;

/**
 * O que a prensa mediu: a força em que o cilindro rompeu.
 *
 * A prensa entrega força, em kN. Resistência é força por área, em MPa — e
 * 1 MPa é exatamente 1 N/mm², o que torna a conta direta: kN × 1000 dividido
 * pela área em mm². É a única conta que este objeto faz, e ele existe para
 * que ela seja feita num lugar só.
 */
final class ResultadoDeEnsaio
{
    /** Acima disto é erro de leitura ou de unidade, não concreto. */
    private const CARGA_MAXIMA_PLAUSIVEL_EM_KN = 5000.0;

    public readonly float $cargaDeRupturaEmKN;
    public readonly DiametroDoCorpoDeProva $diametro;
    public readonly DateTimeImmutable $rompidoEm;

    public function __construct(
        float $cargaDeRupturaEmKN,
        DiametroDoCorpoDeProva $diametro,
        DateTimeImmutable $rompidoEm,
    ) {
        Regras::numeroPositivo($cargaDeRupturaEmKN, 'Carga de ruptura');

        if ($cargaDeRupturaEmKN > self::CARGA_MAXIMA_PLAUSIVEL_EM_KN) {
            throw new ExcecaoDeDominio(sprintf(
                'Carga de %s kN não é plausível para um corpo de prova. Confira a unidade: a prensa mede em kN, não em N.',
                number_format($cargaDeRupturaEmKN, 1, ',', '.'),
            ));
        }

        $this->cargaDeRupturaEmKN = $cargaDeRupturaEmKN;
        $this->diametro = $diametro;
        $this->rompidoEm = $rompidoEm;
    }

    /** Resistência à compressão, com uma casa decimal como pede a NBR 5739. */
    public function resistenciaEmMPa(): float
    {
        return round($this->cargaDeRupturaEmKN * 1000 / $this->diametro->areaEmMm2(), 1);
    }

    public function descricao(): string
    {
        return sprintf(
            '%s kN em cilindro de %s → %s MPa',
            number_format($this->cargaDeRupturaEmKN, 1, ',', '.'),
            $this->diametro->rotulo(),
            number_format($this->resistenciaEmMPa(), 1, ',', '.'),
        );
    }
}
