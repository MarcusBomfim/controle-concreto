<?php

declare(strict_types=1);

namespace ControleConcreto\Aplicacao;

use DateTimeImmutable;
use ControleConcreto\Dominio\Concretagem\RepositorioDeConcretagens;
use ControleConcreto\Dominio\Ensaio\CorpoDeProva;
use ControleConcreto\Dominio\Ensaio\DiametroDoCorpoDeProva;
use ControleConcreto\Dominio\Ensaio\ResultadoDeEnsaio;
use ControleConcreto\Dominio\ExcecaoDeDominio;

/**
 * O laboratório lança o que a prensa mediu.
 *
 * O caso de uso não sabe a regra da janela: quem sabe é o corpo de prova.
 * Aqui só se acha o cilindro certo, entrega o resultado e grava. Se a
 * entidade recusar, a mensagem dela sobe intacta — já está escrita para
 * quem está na prensa.
 */
final class RegistrarRompimento
{
    public function __construct(private readonly RepositorioDeConcretagens $concretagens)
    {
    }

    public function executar(
        string $obraCodigo,
        int $concretagemNumero,
        string $identificacao,
        float $cargaDeRupturaEmKN,
        int $diametroEmMm,
        DateTimeImmutable $rompidoEm,
        ?DateTimeImmutable $agora = null,
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
                'Não existe corpo de prova "%s" na concretagem %d. Confira a etiqueta do cilindro.',
                $identificacao,
                $concretagemNumero,
            ));
        }

        $corpoDeProva->romper(
            new ResultadoDeEnsaio($cargaDeRupturaEmKN, DiametroDoCorpoDeProva::deMm($diametroEmMm), $rompidoEm),
            $agora,
        );

        $this->concretagens->salvar($concretagem);

        return $corpoDeProva;
    }
}
