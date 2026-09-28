<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Aplicacao\TratarNaoConformidade;
use App\Dominio\ExcecaoDeDominio;
use App\Dominio\Lote\Lote;
use App\Dominio\Lote\RepositorioDeLotes;
use App\Dominio\NaoConformidade\Desfecho;
use App\Dominio\NaoConformidade\NaoConformidade;
use App\Dominio\NaoConformidade\RepositorioDeNaoConformidades;
use App\Dominio\NaoConformidade\ResultadoDaProvidencia;
use App\Dominio\NaoConformidade\TipoDeProvidencia;
use App\Dominio\Obra\Obra;
use App\Dominio\Obra\RepositorioDeObras;
use App\Http\Requests\EncerrarNaoConformidadeRequest;
use App\Http\Requests\RegistrarProvidenciaRequest;
use DateTimeImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * A página do lote reprovado: o histórico do tratamento, o formulário da
 * próxima providência e o encerramento — que só aparece com os desfechos
 * que as providências já sustentam.
 */
final class NaoConformidadeController extends Controller
{
    public function __construct(
        private readonly RepositorioDeObras $obras,
        private readonly RepositorioDeLotes $lotes,
        private readonly RepositorioDeNaoConformidades $naoConformidades,
        private readonly TratarNaoConformidade $tratar,
    ) {
    }

    public function show(string $obraCodigo, int $numero): View
    {
        [$obra, $lote, $naoConformidade] = $this->localizar($obraCodigo, $numero);

        return view('nao-conformidades.show', [
            'obra' => $obra,
            'lote' => $lote,
            'naoConformidade' => $naoConformidade,
            'tipos' => TipoDeProvidencia::cases(),
            'resultados' => ResultadoDaProvidencia::cases(),
            'hoje' => (new DateTimeImmutable('today'))->format('Y-m-d'),
        ]);
    }

    public function registrarProvidencia(
        RegistrarProvidenciaRequest $requisicao,
        string $obraCodigo,
        int $numero,
    ): RedirectResponse {
        [$obra, $lote] = $this->localizar($obraCodigo, $numero);

        $destino = redirect()->route('nao-conformidades.show', [$obra->codigo, $lote->numero()]);

        try {
            $providencia = $requisicao->providencia();
            $naoConformidade = $this->tratar->registrarProvidencia($obra->codigo, $lote->numero(), $providencia);
        } catch (ExcecaoDeDominio $erro) {
            return $destino->withInput()->with('erro', $erro->getMessage());
        }

        $possiveis = $naoConformidade->desfechosPossiveis();

        return $destino->with('mensagem', sprintf(
            '%s registrada como %s.%s',
            $providencia->tipo->rotulo(),
            mb_strtolower($providencia->resultado->rotulo()),
            $possiveis === [] ? '' : ' Já é possível encerrar: ' . implode(', ', array_map(
                static fn (Desfecho $desfecho): string => mb_strtolower($desfecho->rotulo()),
                $possiveis,
            )) . '.',
        ));
    }

    public function encerrar(
        EncerrarNaoConformidadeRequest $requisicao,
        string $obraCodigo,
        int $numero,
    ): RedirectResponse {
        [$obra, $lote] = $this->localizar($obraCodigo, $numero);

        $destino = redirect()->route('nao-conformidades.show', [$obra->codigo, $lote->numero()]);

        try {
            $naoConformidade = $this->tratar->encerrar(
                $obra->codigo,
                $lote->numero(),
                $requisicao->desfecho(),
                $requisicao->parecer(),
            );
        } catch (ExcecaoDeDominio $erro) {
            return $destino->withInput()->with('erro', $erro->getMessage());
        }

        return $destino->with('mensagem', sprintf(
            'Não conformidade do lote %d encerrada: %s.',
            $naoConformidade->loteNumero,
            $naoConformidade->desfecho()?->rotulo() ?? '',
        ));
    }

    /** @return array{0: Obra, 1: Lote, 2: NaoConformidade} */
    private function localizar(string $obraCodigo, int $numero): array
    {
        $obra = $this->obras->porCodigo($obraCodigo)
            ?? throw new NotFoundHttpException("Nenhuma obra com o código {$obraCodigo}.");

        $lote = $this->lotes->porNumero($obra->codigo, $numero)
            ?? throw new NotFoundHttpException("Não existe lote de número {$numero} na obra {$obra->codigo}.");

        $naoConformidade = $this->naoConformidades->doLote($obra->codigo, $numero)
            ?? throw new NotFoundHttpException(
                "O lote {$numero} da obra {$obra->codigo} não tem não conformidade: "
                . 'ou foi aceito, ou ainda não foi julgado.'
            );

        return [$obra, $lote, $naoConformidade];
    }
}
