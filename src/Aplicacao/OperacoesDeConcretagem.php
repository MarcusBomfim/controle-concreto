<?php

declare(strict_types=1);

namespace ControleConcreto\Aplicacao;

use DateTimeImmutable;
use ControleConcreto\Dominio\Concretagem\Carga;
use ControleConcreto\Dominio\Concretagem\Concretagem;
use ControleConcreto\Dominio\Concretagem\RepositorioDeConcretagens;
use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;
use ControleConcreto\Dominio\Estrutura\RepositorioDeElementos;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Obra\RepositorioDeObras;

/**
 * O dia a dia do canteiro, do ponto de vista da aplicação: abrir a
 * concretagem, receber cada caminhão, moldar, concluir.
 *
 * Cada método é carregar → agir no domínio → gravar. As regras moram nas
 * entidades; aqui só se localiza o agregado certo e se persiste o que ele
 * decidiu.
 */
final class OperacoesDeConcretagem
{
    public function __construct(
        private readonly RepositorioDeObras $obras,
        private readonly RepositorioDeElementos $elementos,
        private readonly RepositorioDeConcretagens $concretagens,
    ) {
    }

    public function abrir(
        string $obraCodigo,
        string $elementoCodigo,
        DateTimeImmutable $data,
        string $fornecedor,
        string $responsavel,
    ): Concretagem {
        if (!$this->obras->existe($obraCodigo)) {
            throw new ExcecaoDeDominio("A obra {$obraCodigo} não foi encontrada.");
        }

        $elemento = $this->elementos->porCodigo($obraCodigo, $elementoCodigo);

        if ($elemento === null) {
            throw new ExcecaoDeDominio("O elemento {$elementoCodigo} não existe na obra {$obraCodigo}.");
        }

        $concretagem = new Concretagem($obraCodigo, $elemento, $data, $fornecedor, $responsavel);
        $numero = $this->concretagens->salvar($concretagem);
        $concretagem->definirNumero($numero);

        return $concretagem;
    }

    public function receberCarga(
        string $obraCodigo,
        int $numero,
        string $notaFiscal,
        ?string $placa,
        float $volumeEmM3,
        DateTimeImmutable $saidaDaUsina,
        DateTimeImmutable $chegada,
        int $abatimentoMedidoEmMm,
        ?string $observacao,
    ): Carga {
        $concretagem = $this->carregar($obraCodigo, $numero);

        $carga = $concretagem->receberCarga(
            $notaFiscal,
            $placa,
            $volumeEmM3,
            $saidaDaUsina,
            $chegada,
            $abatimentoMedidoEmMm,
            $observacao,
        );

        $this->concretagens->salvar($concretagem);

        return $carga;
    }

    /**
     * @param  IdadeDeEnsaio[] $idades
     * @return \ControleConcreto\Dominio\Ensaio\Exemplar[]
     */
    public function moldar(string $obraCodigo, int $numero, int $cargaNumero, DateTimeImmutable $momento, array $idades): array
    {
        $concretagem = $this->carregar($obraCodigo, $numero);

        $exemplares = $concretagem->moldar($cargaNumero, $momento, $idades);

        $this->concretagens->salvar($concretagem);

        return $exemplares;
    }

    public function concluir(string $obraCodigo, int $numero): Concretagem
    {
        $concretagem = $this->carregar($obraCodigo, $numero);
        $concretagem->concluir();
        $this->concretagens->salvar($concretagem);

        return $concretagem;
    }

    public function cancelar(string $obraCodigo, int $numero): Concretagem
    {
        $concretagem = $this->carregar($obraCodigo, $numero);
        $concretagem->cancelar();
        $this->concretagens->salvar($concretagem);

        return $concretagem;
    }

    private function carregar(string $obraCodigo, int $numero): Concretagem
    {
        return $this->concretagens->porNumero($obraCodigo, $numero)
            ?? throw new ExcecaoDeDominio("Não existe concretagem de número {$numero} na obra {$obraCodigo}.");
    }
}
