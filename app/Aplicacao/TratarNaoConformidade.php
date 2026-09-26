<?php

declare(strict_types=1);

namespace App\Aplicacao;

use DateTimeImmutable;
use App\Dominio\ExcecaoDeDominio;
use App\Dominio\NaoConformidade\Desfecho;
use App\Dominio\NaoConformidade\NaoConformidade;
use App\Dominio\NaoConformidade\Providencia;
use App\Dominio\NaoConformidade\RepositorioDeNaoConformidades;

/**
 * O engenheiro conduz o tratamento: registra cada providência e, quando
 * uma delas sustenta uma decisão, encerra com o desfecho. As regras — o que
 * sustenta o quê — moram na entidade; aqui só se localiza e grava.
 */
final class TratarNaoConformidade
{
    public function __construct(private readonly RepositorioDeNaoConformidades $naoConformidades)
    {
    }

    public function registrarProvidencia(string $obraCodigo, int $loteNumero, Providencia $providencia): NaoConformidade
    {
        $naoConformidade = $this->carregar($obraCodigo, $loteNumero);
        $naoConformidade->registrarProvidencia($providencia);
        $this->naoConformidades->salvar($naoConformidade);

        return $naoConformidade;
    }

    public function encerrar(
        string $obraCodigo,
        int $loteNumero,
        Desfecho $desfecho,
        string $parecer,
        ?DateTimeImmutable $agora = null,
    ): NaoConformidade {
        $naoConformidade = $this->carregar($obraCodigo, $loteNumero);
        $naoConformidade->encerrar($desfecho, $parecer, $agora ?? new DateTimeImmutable('now'));
        $this->naoConformidades->salvar($naoConformidade);

        return $naoConformidade;
    }

    private function carregar(string $obraCodigo, int $loteNumero): NaoConformidade
    {
        return $this->naoConformidades->doLote($obraCodigo, $loteNumero)
            ?? throw new ExcecaoDeDominio(
                "O lote {$loteNumero} da obra {$obraCodigo} não tem não conformidade aberta: ou foi aceito, ou ainda não foi julgado."
            );
    }
}
