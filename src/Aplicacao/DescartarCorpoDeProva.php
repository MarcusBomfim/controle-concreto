<?php

declare(strict_types=1);

namespace ControleConcreto\Aplicacao;

use ControleConcreto\Dominio\Concretagem\RepositorioDeConcretagens;
use ControleConcreto\Dominio\Ensaio\CorpoDeProva;
use ControleConcreto\Dominio\ExcecaoDeDominio;

/**
 * Tira um corpo de prova do controle, com motivo.
 *
 * É o caminho para o cilindro que quebrou na desforma, que foi perdido, ou
 * que passou da janela sem ser rompido. Ele não some: fica como descartado,
 * com o motivo, e o exemplar dele segue com um cilindro só — o que a Etapa 6
 * vai levar em conta.
 */
final class DescartarCorpoDeProva
{
    public function __construct(private readonly RepositorioDeConcretagens $concretagens)
    {
    }

    public function executar(
        string $obraCodigo,
        int $concretagemNumero,
        string $identificacao,
        string $motivo,
    ): CorpoDeProva {
        $concretagem = $this->concretagens->porNumero($obraCodigo, $concretagemNumero);

        if ($concretagem === null) {
            throw new ExcecaoDeDominio(
                "Não existe concretagem de número {$concretagemNumero} na obra {$obraCodigo}."
            );
        }

        $corpoDeProva = $concretagem->corpoDeProva($identificacao);

        if ($corpoDeProva === null) {
            throw new ExcecaoDeDominio(sprintf(
                'Não existe corpo de prova "%s" na concretagem %d.',
                $identificacao,
                $concretagemNumero,
            ));
        }

        $corpoDeProva->descartar($motivo);

        $this->concretagens->salvar($concretagem);

        return $corpoDeProva;
    }
}
