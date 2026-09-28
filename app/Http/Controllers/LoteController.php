<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Aplicacao\FormarLote;
use App\Aplicacao\JulgarLote;
use App\Dominio\Concretagem\Concretagem;
use App\Dominio\Concretagem\RepositorioDeConcretagens;
use App\Dominio\ExcecaoDeDominio;
use App\Dominio\Lote\CondicaoDePreparo;
use App\Dominio\Lote\Lote;
use App\Dominio\Lote\RepositorioDeLotes;
use App\Dominio\Lote\TipoDeAmostragem;
use App\Dominio\NaoConformidade\RepositorioDeNaoConformidades;
use App\Dominio\Obra\Obra;
use App\Dominio\Obra\RepositorioDeObras;
use App\Http\Requests\FormarLoteRequest;
use App\Support\Formato;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Formar o lote e julgá-lo — e mostrar a conta inteira quando julgado.
 *
 * A classe e o grupo do lote não são perguntados: saem da primeira
 * concretagem escolhida. Se as outras não combinarem, o domínio recusa com
 * a mensagem certa.
 */
final class LoteController extends Controller
{
    public function __construct(
        private readonly RepositorioDeObras $obras,
        private readonly RepositorioDeConcretagens $concretagens,
        private readonly RepositorioDeLotes $lotes,
        private readonly RepositorioDeNaoConformidades $naoConformidades,
        private readonly FormarLote $formar,
        private readonly JulgarLote $julgar,
    ) {
    }

    public function create(string $obraCodigo): View
    {
        $obra = $this->obraOuFalha($obraCodigo);

        // Só entra em lote concretagem concluída que ainda não está em nenhum.
        $disponiveis = array_values(array_filter(
            $this->concretagens->daObra($obra->codigo),
            fn (Concretagem $c): bool => $c->estaConcluida()
                && $this->lotes->loteDaConcretagem($obra->codigo, $c->numero()) === null,
        ));

        return view('lotes.create', [
            'obra' => $obra,
            'disponiveis' => $disponiveis,
            'condicoes' => CondicaoDePreparo::cases(),
            'amostragens' => TipoDeAmostragem::cases(),
        ]);
    }

    public function store(FormarLoteRequest $requisicao, string $obraCodigo): RedirectResponse
    {
        $obra = $this->obraOuFalha($obraCodigo);
        $numeros = $requisicao->numerosDeConcretagem();

        try {
            /*
             * A classe e o grupo saem da primeira concretagem marcada. Se as
             * outras não combinarem, quem recusa é o Lote — com a mensagem
             * que diz qual concretagem destoa e por quê.
             */
            $primeira = $this->concretagens->porNumero($obra->codigo, $numeros[0])
                ?? throw new ExcecaoDeDominio("Não existe concretagem de número {$numeros[0]} na obra {$obra->codigo}.");

            $lote = $this->formar->executar(
                $obra->codigo,
                $primeira->elemento->classe,
                $primeira->elemento->tipo->grupo(),
                $requisicao->condicao(),
                $requisicao->amostragem(),
                $numeros,
            );
        } catch (ExcecaoDeDominio $erro) {
            return back()->withInput()->with('erro', $erro->getMessage());
        }

        return redirect()
            ->route('lotes.show', [$obra->codigo, $lote->numero()])
            ->with('mensagem', sprintf(
                '%s formado com %d concretagem(ns) e %s. Julgue quando todos os exemplares de 28 dias estiverem resolvidos.',
                $lote->identificacao(),
                count($lote->concretagens()),
                Formato::metrosCubicos($lote->volumeEmM3()),
            ));
    }

    public function show(string $obraCodigo, int $numero): View
    {
        [$obra, $lote] = $this->localizar($obraCodigo, $numero);

        return view('lotes.show', [
            'obra' => $obra,
            'lote' => $lote,
            'naoConformidade' => $this->naoConformidades->doLote($obra->codigo, $lote->numero()),
        ]);
    }

    public function julgar(string $obraCodigo, int $numero): RedirectResponse
    {
        [$obra, $lote] = $this->localizar($obraCodigo, $numero);

        $destino = redirect()->route('lotes.show', [$obra->codigo, $lote->numero()]);

        try {
            $julgado = $this->julgar->executar($obra->codigo, $lote->numero());
        } catch (ExcecaoDeDominio $erro) {
            return $destino->with('erro', $erro->getMessage());
        }

        return $destino->with('mensagem', sprintf(
            'Lote nº %d julgado: fck,est = %s contra fck = %s de projeto. %s.%s',
            $julgado->numero(),
            Formato::mpa($julgado->estimativa()?->fckEstimadoEmMPa),
            Formato::mpa($julgado->classe->fck()),
            $julgado->situacao()->rotulo(),
            $julgado->foiAceito() ? '' : ' A não conformidade foi aberta: registre as providências.',
        ));
    }

    /** @return array{0: Obra, 1: Lote} */
    private function localizar(string $obraCodigo, int $numero): array
    {
        $obra = $this->obraOuFalha($obraCodigo);

        $lote = $this->lotes->porNumero($obra->codigo, $numero)
            ?? throw new NotFoundHttpException("Não existe lote de número {$numero} na obra {$obra->codigo}.");

        return [$obra, $lote];
    }

    private function obraOuFalha(string $codigo): Obra
    {
        return $this->obras->porCodigo($codigo)
            ?? throw new NotFoundHttpException("Nenhuma obra com o código {$codigo}.");
    }
}
