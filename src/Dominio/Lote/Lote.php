<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Lote;

use DateTimeImmutable;
use ControleConcreto\Dominio\Concretagem\Concretagem;
use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\Ensaio\Exemplar;
use ControleConcreto\Dominio\Estrutura\GrupoDeSolicitacao;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Regras;

/**
 * O lote de aceitação: o conjunto de concreto que a norma julga de uma vez.
 *
 * A NBR 12655 limita o lote por volume, por grupo de solicitação e por
 * tempo, para que os exemplares representem um concreto homogêneo. Uma
 * concretagem pequena não se julga sozinha — junta-se a outras do mesmo
 * fck e do mesmo grupo, em até três dias, e o lote é julgado quando todos
 * os exemplares de 28 dias tiverem sido rompidos ou descartados.
 */
final class Lote
{
    /** Transcrito da Tabela 6 da NBR 12655; confira com o texto vigente. */
    public const MAXIMO_DE_DIAS_DE_CONCRETAGEM = 3;

    public readonly string $obraCodigo;
    public readonly ClasseDeResistencia $classe;
    public readonly GrupoDeSolicitacao $grupo;
    public readonly CondicaoDePreparo $condicao;
    public readonly TipoDeAmostragem $amostragem;

    private int $numero = 0;
    private SituacaoDoLote $situacao = SituacaoDoLote::Aberto;

    /** @var Concretagem[] */
    private array $concretagens = [];

    private ?EstimativaDeFck $estimativa = null;
    private ?DateTimeImmutable $julgadoEm = null;

    public function __construct(
        string $obraCodigo,
        ClasseDeResistencia $classe,
        GrupoDeSolicitacao $grupo,
        CondicaoDePreparo $condicao,
        TipoDeAmostragem $amostragem,
    ) {
        $this->obraCodigo = strtoupper(Regras::textoObrigatorio($obraCodigo, 'Código da obra', 20));
        $this->classe = $classe;
        $this->grupo = $grupo;
        $this->condicao = $condicao;
        $this->amostragem = $amostragem;
    }

    public static function reconstituir(
        string $obraCodigo,
        int $numero,
        ClasseDeResistencia $classe,
        GrupoDeSolicitacao $grupo,
        CondicaoDePreparo $condicao,
        TipoDeAmostragem $amostragem,
        SituacaoDoLote $situacao,
        ?EstimativaDeFck $estimativa,
        ?DateTimeImmutable $julgadoEm,
    ): self {
        $lote = new self($obraCodigo, $classe, $grupo, $condicao, $amostragem);
        $lote->definirNumero($numero);
        $lote->situacao = $situacao;
        $lote->estimativa = $estimativa;
        $lote->julgadoEm = $julgadoEm;

        return $lote;
    }

    public function numero(): int
    {
        return $this->numero;
    }

    public function definirNumero(int $numero): void
    {
        $this->numero = Regras::inteiroPositivo($numero, 'Número do lote');
    }

    public function situacao(): SituacaoDoLote
    {
        return $this->situacao;
    }

    /**
     * Inclui uma concretagem no lote, conferindo as quatro condições da
     * norma: mesmo fck, mesmo grupo, volume dentro do limite e no máximo
     * três dias de concretagem.
     */
    public function adicionarConcretagem(Concretagem $concretagem): void
    {
        $this->exigirAberto('incluir concretagem');

        if (!$concretagem->estaConcluida()) {
            throw new ExcecaoDeDominio(sprintf(
                'A concretagem %d ainda está %s. Só concretagem concluída entra em lote.',
                $concretagem->numero(),
                mb_strtolower($concretagem->situacao()->rotulo()),
            ));
        }

        if ($concretagem->obraCodigo !== $this->obraCodigo) {
            throw new ExcecaoDeDominio('A concretagem é de outra obra.');
        }

        if ($concretagem->elemento->classe !== $this->classe) {
            throw new ExcecaoDeDominio(sprintf(
                'O lote é de %s e a concretagem %d é de %s. Um lote tem um fck só.',
                $this->classe->rotulo(),
                $concretagem->numero(),
                $concretagem->elemento->classe->rotulo(),
            ));
        }

        if ($concretagem->elemento->tipo->grupo() !== $this->grupo) {
            throw new ExcecaoDeDominio(sprintf(
                'O lote é de peças em %s e a concretagem %d é de %s. Os grupos não se misturam.',
                mb_strtolower($this->grupo->rotulo()),
                $concretagem->numero(),
                mb_strtolower($concretagem->elemento->tipo->rotulo()),
            ));
        }

        foreach ($this->concretagens as $existente) {
            if ($existente->numero() === $concretagem->numero()) {
                throw new ExcecaoDeDominio("A concretagem {$concretagem->numero()} já está neste lote.");
            }
        }

        $volumeFinal = $this->volumeEmM3() + $concretagem->volumeAceitoEmM3();
        $limite = $this->grupo->volumeMaximoDoLoteEmM3();

        if ($volumeFinal > $limite + Regras::TOLERANCIA) {
            throw new ExcecaoDeDominio(sprintf(
                'Com a concretagem %d o lote iria a %s m³, acima do limite de %d m³ para %s. Abra outro lote.',
                $concretagem->numero(),
                number_format($volumeFinal, 1, ',', '.'),
                $limite,
                mb_strtolower($this->grupo->rotulo()),
            ));
        }

        $dias = $this->diasDeConcretagem();
        $dias[$concretagem->data->format('Y-m-d')] = true;

        if (count($dias) > self::MAXIMO_DE_DIAS_DE_CONCRETAGEM) {
            throw new ExcecaoDeDominio(sprintf(
                'O lote já cobre %d dias de concretagem, o máximo da norma. A concretagem %d, de %s, vai para outro lote.',
                self::MAXIMO_DE_DIAS_DE_CONCRETAGEM,
                $concretagem->numero(),
                $concretagem->data->format('d/m/Y'),
            ));
        }

        $this->concretagens[] = $concretagem;
    }

    /** Recoloca uma concretagem vinda do banco, sem revalidar. */
    public function anexarConcretagem(Concretagem $concretagem): void
    {
        $this->concretagens[] = $concretagem;
    }

    /** @return Concretagem[] */
    public function concretagens(): array
    {
        return $this->concretagens;
    }

    public function volumeEmM3(): float
    {
        return round(array_sum(array_map(
            static fn (Concretagem $c): float => $c->volumeAceitoEmM3(),
            $this->concretagens,
        )), 2);
    }

    /** @return array<string, true> datas ISO distintas */
    private function diasDeConcretagem(): array
    {
        $dias = [];

        foreach ($this->concretagens as $concretagem) {
            $dias[$concretagem->data->format('Y-m-d')] = true;
        }

        return $dias;
    }

    /** @return Exemplar[] só os de 28 dias, de todas as concretagens */
    public function exemplaresDeAceitacao(): array
    {
        $todos = [];

        foreach ($this->concretagens as $concretagem) {
            foreach ($concretagem->exemplaresDeAceitacao() as $exemplar) {
                $todos[] = $exemplar;
            }
        }

        return $todos;
    }

    /** @return Exemplar[] */
    public function exemplaresComResultado(): array
    {
        return array_values(array_filter(
            $this->exemplaresDeAceitacao(),
            static fn (Exemplar $e): bool => $e->temResultado(),
        ));
    }

    /** @return Exemplar[] ainda com corpo de prova esperando a prensa */
    public function exemplaresPendentes(): array
    {
        return array_values(array_filter(
            $this->exemplaresDeAceitacao(),
            static fn (Exemplar $e): bool => $e->aguardaRompimento(),
        ));
    }

    /** @return Exemplar[] sem resultado e sem nada mais para romper: os dois se perderam */
    public function exemplaresPerdidos(): array
    {
        return array_values(array_filter(
            $this->exemplaresDeAceitacao(),
            static fn (Exemplar $e): bool => !$e->temResultado() && !$e->aguardaRompimento(),
        ));
    }

    public function podeSerJulgado(): bool
    {
        return $this->situacao === SituacaoDoLote::Aberto
            && $this->concretagens !== []
            && $this->exemplaresPendentes() === []
            && $this->exemplaresComResultado() !== [];
    }

    /**
     * Faz a conta da norma e decide.
     *
     * Só julga com todos os exemplares de 28 dias resolvidos — rompidos ou
     * descartados. Julgar com resultado pendente seria escolher quais
     * cilindros contam, e é exatamente isso que a norma existe para impedir.
     */
    public function julgar(DateTimeImmutable $agora): EstimativaDeFck
    {
        $this->exigirAberto('julgar');

        if ($this->concretagens === []) {
            throw new ExcecaoDeDominio('O lote está vazio: inclua concretagens antes de julgar.');
        }

        $pendentes = $this->exemplaresPendentes();

        if ($pendentes !== []) {
            throw new ExcecaoDeDominio(sprintf(
                'Ainda há %d exemplar(es) de 28 dias aguardando rompimento: %s. Julgue quando todos estiverem resolvidos.',
                count($pendentes),
                implode(', ', array_map(static fn (Exemplar $e): string => $e->identificacao(), $pendentes)),
            ));
        }

        $resistencias = array_map(
            static fn (Exemplar $e): float => $e->resistenciaEmMPa() ?? 0.0,
            $this->exemplaresComResultado(),
        );

        $this->estimativa = CalculadoraDeFckEstimado::calcular($resistencias, $this->amostragem, $this->condicao);
        $this->julgadoEm = $agora;
        $this->situacao = $this->estimativa->atende($this->classe->fck())
            ? SituacaoDoLote::Aceito
            : SituacaoDoLote::NaoConforme;

        return $this->estimativa;
    }

    public function estimativa(): ?EstimativaDeFck
    {
        return $this->estimativa;
    }

    public function julgadoEm(): ?DateTimeImmutable
    {
        return $this->julgadoEm;
    }

    public function foiAceito(): bool
    {
        return $this->situacao === SituacaoDoLote::Aceito;
    }

    public function identificacao(): string
    {
        return sprintf('Lote %d · %s · %s', $this->numero, $this->classe->rotulo(), $this->grupo->rotulo());
    }

    private function exigirAberto(string $acao): void
    {
        if ($this->situacao === SituacaoDoLote::Aberto) {
            return;
        }

        throw new ExcecaoDeDominio(sprintf(
            'O lote %d já foi julgado (%s) e não é possível %s.',
            $this->numero,
            mb_strtolower($this->situacao->rotulo()),
            $acao,
        ));
    }
}
