<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Aplicacao\OperacoesDeConcretagem;
use App\Dominio\Concretagem\Concretagem;
use App\Dominio\Concretagem\RepositorioDeConcretagens;
use App\Dominio\Ensaio\IdadeDeEnsaio;
use App\Dominio\Estrutura\RepositorioDeElementos;
use App\Dominio\ExcecaoDeDominio;
use App\Dominio\Lote\RepositorioDeLotes;
use App\Dominio\Obra\Obra;
use App\Dominio\Obra\RepositorioDeObras;
use App\Http\Requests\AbrirConcretagemRequest;
use App\Http\Requests\MoldarRequest;
use App\Http\Requests\ReceberCargaRequest;
use App\Support\Formato;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A tela do dia de concretagem: abrir, receber cada caminhão, moldar os
 * corpos de prova, concluir.
 *
 * Os horários chegam do formulário só como hora (07:55), porque a data é a
 * da concretagem — o domínio recusa carga de outro dia de qualquer jeito.
 */
final class ConcretagemController extends Controller
{
    public function __construct(
        private readonly RepositorioDeObras $obras,
        private readonly RepositorioDeElementos $elementos,
        private readonly RepositorioDeConcretagens $concretagens,
        private readonly RepositorioDeLotes $lotes,
        private readonly OperacoesDeConcretagem $operacoes,
    ) {
    }

    public function create(string $obraCodigo): View
    {
        $obra = $this->obraOuFalha($obraCodigo);

        return view('concretagens.create', [
            'obra' => $obra,
            'elementos' => $this->elementos->daObra($obra->codigo),
            'hoje' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        ]);
    }

    public function store(AbrirConcretagemRequest $requisicao, string $obraCodigo): RedirectResponse
    {
        $obra = $this->obraOuFalha($obraCodigo);

        try {
            $concretagem = $this->operacoes->abrir(
                $obra->codigo,
                $requisicao->validated('elemento'),
                $requisicao->dataDaConcretagem(),
                $requisicao->validated('fornecedor'),
                $requisicao->validated('responsavel'),
            );
        } catch (ExcecaoDeDominio $erro) {
            return back()->withInput()->with('erro', $erro->getMessage());
        }

        return redirect()
            ->route('concretagens.show', [$obra->codigo, $concretagem->numero()])
            ->with('mensagem', sprintf(
                'Concretagem nº %d aberta para %s. Registre cada caminhão assim que chegar.',
                $concretagem->numero(),
                $concretagem->elemento->identificacao(),
            ));
    }

    public function show(string $obraCodigo, int $numero): View
    {
        [$obra, $concretagem] = $this->localizar($obraCodigo, $numero);

        return view('concretagens.show', [
            'obra' => $obra,
            'concretagem' => $concretagem,
            'loteNumero' => $this->lotes->loteDaConcretagem($obra->codigo, $concretagem->numero()),
            'agora' => new DateTimeImmutable('now'),
            'idades' => IdadeDeEnsaio::cases(),
        ]);
    }

    public function receberCarga(ReceberCargaRequest $requisicao, string $obraCodigo, int $numero): RedirectResponse
    {
        return $this->agir($obraCodigo, $numero, function (Obra $obra, Concretagem $concretagem) use ($requisicao): string {
            $carga = $this->operacoes->receberCarga(
                $obra->codigo,
                $concretagem->numero(),
                $requisicao->validated('nota_fiscal'),
                $requisicao->validated('placa'),
                $requisicao->volumeEmM3(),
                $this->momentoNoDia($concretagem, $requisicao->validated('saida')),
                $this->momentoNoDia($concretagem, $requisicao->validated('chegada')),
                (int) $requisicao->validated('abatimento_mm'),
                $requisicao->validated('observacao'),
            );

            if ($carga->foiDevolvida()) {
                return sprintf(
                    'Carga %d (NF %s) DEVOLVIDA: %s. O registro fica para a conversa com a usina.',
                    $carga->numero,
                    $carga->notaFiscal,
                    mb_strtolower($carga->devolucao?->rotulo() ?? ''),
                );
            }

            return sprintf(
                'Carga %d (NF %s) aceita: %s, abatimento %d mm, %d min de transporte. Molde os corpos de prova.',
                $carga->numero,
                $carga->notaFiscal,
                Formato::metrosCubicos($carga->volumeEmM3),
                $carga->abatimentoMedidoEmMm,
                $carga->tempoDeTransporteEmMinutos(),
            );
        });
    }

    public function moldar(MoldarRequest $requisicao, string $obraCodigo, int $numero): RedirectResponse
    {
        return $this->agir($obraCodigo, $numero, function (Obra $obra, Concretagem $concretagem) use ($requisicao): string {
            $cargaNumero = (int) $requisicao->validated('carga');

            $exemplares = $this->operacoes->moldar(
                $obra->codigo,
                $concretagem->numero(),
                $cargaNumero,
                $this->momentoNoDia($concretagem, $requisicao->validated('hora')),
                $requisicao->idades(),
            );

            return sprintf(
                '%d exemplar(es) moldado(s) da carga %d: %s. Cada um tem dois corpos de prova, já na agenda do laboratório.',
                count($exemplares),
                $cargaNumero,
                implode(', ', array_map(static fn ($e): string => $e->idade->rotulo(), $exemplares)),
            );
        });
    }

    public function concluir(string $obraCodigo, int $numero): RedirectResponse
    {
        return $this->agir($obraCodigo, $numero, function (Obra $obra, Concretagem $concretagem): string {
            $this->operacoes->concluir($obra->codigo, $concretagem->numero());

            return sprintf(
                'Concretagem nº %d concluída com %s. Quando os exemplares de 28 dias romperem, forme o lote.',
                $concretagem->numero(),
                Formato::metrosCubicos($concretagem->volumeAceitoEmM3()),
            );
        });
    }

    public function cancelar(string $obraCodigo, int $numero): RedirectResponse
    {
        return $this->agir($obraCodigo, $numero, function (Obra $obra, Concretagem $concretagem): string {
            $this->operacoes->cancelar($obra->codigo, $concretagem->numero());

            return "Concretagem nº {$concretagem->numero()} cancelada.";
        });
    }

    /**
     * O esqueleto comum das ações de POST: localizar, agir, guardar a
     * mensagem e voltar para a tela da concretagem.
     *
     * Na versão em PHP puro este método também conferia o token anti-CSRF
     * em toda ação. Aqui isso é middleware, e sai do controlador.
     *
     * @param callable(Obra, Concretagem): string $acao devolve a mensagem de sucesso
     */
    private function agir(string $obraCodigo, int $numero, callable $acao): RedirectResponse
    {
        [$obra, $concretagem] = $this->localizar($obraCodigo, $numero);

        $destino = redirect()->route('concretagens.show', [$obra->codigo, $concretagem->numero()]);

        try {
            return $destino->with('mensagem', $acao($obra, $concretagem));
        } catch (ExcecaoDeDominio $erro) {
            // Regra de negócio: a mensagem já está escrita para quem está no canteiro.
            return $destino->with('erro', $erro->getMessage());
        }
    }

    /** @return array{0: Obra, 1: Concretagem} */
    private function localizar(string $obraCodigo, int $numero): array
    {
        $obra = $this->obraOuFalha($obraCodigo);

        $concretagem = $this->concretagens->porNumero($obra->codigo, $numero)
            ?? throw new NotFoundHttpException(
                "Não existe concretagem de número {$numero} na obra {$obra->codigo}."
            );

        return [$obra, $concretagem];
    }

    /** Junta a data da concretagem com uma hora vinda do formulário. */
    private function momentoNoDia(Concretagem $concretagem, string $hora): DateTimeImmutable
    {
        return new DateTimeImmutable($concretagem->data->format('Y-m-d') . ' ' . $hora);
    }

    private function obraOuFalha(string $codigo): Obra
    {
        return $this->obras->porCodigo($codigo)
            ?? throw new NotFoundHttpException("Nenhuma obra com o código {$codigo}.");
    }
}
