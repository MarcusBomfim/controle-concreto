<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Concretagem;

use DateTimeImmutable;
use ControleConcreto\Dominio\Estrutura\ElementoEstrutural;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Regras;

/**
 * O evento de concretar uma peça num dia.
 *
 * É o agregado que recebe os caminhões e julga cada um contra a especificação
 * do elemento. A decisão de aceitar ou devolver mora aqui — a carga só
 * registra o veredito. Carga devolvida fica no histórico: não vira volume,
 * não gera corpo de prova, mas conta na conversa com a usina.
 */
final class Concretagem
{
    /**
     * Limite da NBR 7212 para descarregar o concreto depois da adição da água
     * na usina. Passou disso, o concreto começou a pegar dentro do caminhão.
     * Valor transcrito da norma; confira com o texto vigente antes de uso real.
     */
    public const TEMPO_MAXIMO_DE_TRANSPORTE_EM_MINUTOS = 150;

    public readonly string $obraCodigo;
    public readonly ElementoEstrutural $elemento;
    public readonly DateTimeImmutable $data;
    public readonly string $fornecedor;
    public readonly string $responsavel;

    /** Sequencial dentro da obra. Zero até ser gravada. */
    private int $numero = 0;

    private SituacaoDaConcretagem $situacao = SituacaoDaConcretagem::EmAndamento;

    /** @var Carga[] */
    private array $cargas = [];

    public function __construct(
        string $obraCodigo,
        ElementoEstrutural $elemento,
        DateTimeImmutable $data,
        string $fornecedor,
        string $responsavel,
        ?DateTimeImmutable $hoje = null,
    ) {
        $this->obraCodigo = strtoupper(Regras::textoObrigatorio($obraCodigo, 'Código da obra', 20));
        $this->elemento = $elemento;
        $this->fornecedor = Regras::textoObrigatorio($fornecedor, 'Fornecedor do concreto', 120);
        $this->responsavel = Regras::textoObrigatorio($responsavel, 'Responsável pela concretagem', 160);

        $dia = $data->setTime(0, 0);
        $limite = ($hoje ?? new DateTimeImmutable('today'))->setTime(0, 0);

        if ($dia > $limite) {
            throw new ExcecaoDeDominio(sprintf(
                'Não é possível registrar concretagem para %s: a data ainda não chegou.',
                $dia->format('d/m/Y'),
            ));
        }

        $this->data = $dia;
    }

    public static function reconstituir(
        string $obraCodigo,
        int $numero,
        ElementoEstrutural $elemento,
        DateTimeImmutable $data,
        string $fornecedor,
        string $responsavel,
        SituacaoDaConcretagem $situacao,
    ): self {
        $concretagem = new self($obraCodigo, $elemento, $data, $fornecedor, $responsavel, $data);
        $concretagem->definirNumero($numero);
        $concretagem->situacao = $situacao;

        return $concretagem;
    }

    public function numero(): int
    {
        return $this->numero;
    }

    public function definirNumero(int $numero): void
    {
        $this->numero = Regras::inteiroPositivo($numero, 'Número da concretagem');
    }

    public function situacao(): SituacaoDaConcretagem
    {
        return $this->situacao;
    }

    /**
     * Recebe um caminhão e decide na hora se ele entra.
     *
     * Duas checagens, na ordem em que o canteiro faz: primeiro o relógio
     * (a carga demorou demais?), depois o cone (o abatimento está na faixa?).
     * A primeira que falhar devolve o caminhão. A carga devolvida é
     * registrada com o motivo — apagar seria perder a prova.
     */
    public function receberCarga(
        string $notaFiscal,
        ?string $placa,
        float $volumeEmM3,
        DateTimeImmutable $saidaDaUsina,
        DateTimeImmutable $chegada,
        int $abatimentoMedidoEmMm,
        ?string $observacao = null,
    ): Carga {
        if (!$this->situacao->aceitaCarga()) {
            throw new ExcecaoDeDominio(sprintf(
                'A concretagem está %s e não recebe mais cargas.',
                mb_strtolower($this->situacao->rotulo()),
            ));
        }

        if ($chegada->format('Y-m-d') !== $this->data->format('Y-m-d')) {
            throw new ExcecaoDeDominio(sprintf(
                'A carga chegou em %s, mas a concretagem é de %s. Registre-a na concretagem do dia certo.',
                $chegada->format('d/m/Y'),
                $this->data->format('d/m/Y'),
            ));
        }

        $carga = new Carga(
            count($this->cargas) + 1,
            $notaFiscal,
            $placa,
            $volumeEmM3,
            $saidaDaUsina,
            $chegada,
            $abatimentoMedidoEmMm,
            $this->julgar($saidaDaUsina, $chegada, $abatimentoMedidoEmMm),
            $observacao,
        );

        $this->cargas[] = $carga;

        return $carga;
    }

    /** Recoloca uma carga vinda do banco, sem julgar de novo: o veredito já foi dado. */
    public function anexarCarga(Carga $carga): void
    {
        $this->cargas[] = $carga;
    }

    /** @return Carga[] */
    public function cargas(): array
    {
        return $this->cargas;
    }

    /** @return Carga[] */
    public function cargasAceitas(): array
    {
        return array_values(array_filter(
            $this->cargas,
            static fn (Carga $carga): bool => $carga->foiAceita(),
        ));
    }

    /** @return Carga[] */
    public function cargasDevolvidas(): array
    {
        return array_values(array_filter(
            $this->cargas,
            static fn (Carga $carga): bool => $carga->foiDevolvida(),
        ));
    }

    public function carga(int $numero): ?Carga
    {
        foreach ($this->cargas as $carga) {
            if ($carga->numero === $numero) {
                return $carga;
            }
        }

        return null;
    }

    /** Só o que entrou na forma conta como volume concretado. */
    public function volumeAceitoEmM3(): float
    {
        return round(array_sum(array_map(
            static fn (Carga $carga): float => $carga->volumeEmM3,
            $this->cargasAceitas(),
        )), 2);
    }

    public function volumeDevolvidoEmM3(): float
    {
        return round(array_sum(array_map(
            static fn (Carga $carga): float => $carga->volumeEmM3,
            $this->cargasDevolvidas(),
        )), 2);
    }

    public function concluir(): void
    {
        $this->exigirEmAndamento('concluir');

        if ($this->cargasAceitas() === []) {
            throw new ExcecaoDeDominio(
                'Não há carga aceita nesta concretagem. Sem concreto na forma não há o que concluir — cancele.'
            );
        }

        $this->situacao = SituacaoDaConcretagem::Concluida;
    }

    /**
     * Cancelar só cabe enquanto nenhum concreto entrou na forma. Depois disso
     * a peça existe, e o que se faz é concluir e controlar.
     */
    public function cancelar(): void
    {
        $this->exigirEmAndamento('cancelar');

        if ($this->cargasAceitas() !== []) {
            throw new ExcecaoDeDominio(sprintf(
                'Já entraram %s m³ na forma. Concretagem com concreto lançado não se cancela; conclua e controle.',
                number_format($this->volumeAceitoEmM3(), 1, ',', '.'),
            ));
        }

        $this->situacao = SituacaoDaConcretagem::Cancelada;
    }

    public function estaConcluida(): bool
    {
        return $this->situacao === SituacaoDaConcretagem::Concluida;
    }

    public function resumo(): string
    {
        return sprintf(
            'Concretagem %d - %s - %s - %d carga(s), %s m³ aceitos, %s m³ devolvidos - %s',
            $this->numero,
            $this->data->format('d/m/Y'),
            $this->elemento->identificacao(),
            count($this->cargas),
            number_format($this->volumeAceitoEmM3(), 1, ',', '.'),
            number_format($this->volumeDevolvidoEmM3(), 1, ',', '.'),
            mb_strtolower($this->situacao->rotulo()),
        );
    }

    private function julgar(
        DateTimeImmutable $saidaDaUsina,
        DateTimeImmutable $chegada,
        int $abatimentoMedidoEmMm,
    ): ?MotivoDeDevolucao {
        $minutos = (int) floor(($chegada->getTimestamp() - $saidaDaUsina->getTimestamp()) / 60);

        if ($minutos > self::TEMPO_MAXIMO_DE_TRANSPORTE_EM_MINUTOS) {
            return MotivoDeDevolucao::TempoDeTransporteExcedido;
        }

        if (!$this->elemento->abatimento->aceita($abatimentoMedidoEmMm)) {
            return MotivoDeDevolucao::AbatimentoForaDaFaixa;
        }

        return null;
    }

    private function exigirEmAndamento(string $acao): void
    {
        if ($this->situacao === SituacaoDaConcretagem::EmAndamento) {
            return;
        }

        throw new ExcecaoDeDominio(sprintf(
            'A concretagem está %s e não é possível %s.',
            mb_strtolower($this->situacao->rotulo()),
            $acao,
        ));
    }
}
