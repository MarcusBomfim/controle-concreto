<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Aplicacao\DescartarCorpoDeProva;
use App\Aplicacao\RegistrarRompimento;
use App\Dominio\ExcecaoDeDominio;
use App\Http\Requests\DescartarRequest;
use App\Http\Requests\RomperRequest;
use Illuminate\Http\RedirectResponse;

/**
 * O que a prensa faz com um cilindro: rompe, ou descarta com motivo.
 *
 * As duas ações são POST vindos de dois lugares — a agenda do laboratório e
 * a tela da concretagem — e voltam para onde vieram. O resultado se lança
 * direto da agenda porque é na agenda que a pessoa da prensa está quando o
 * cilindro rompe; obrigá-la a navegar até a obra seria repetir o caminho
 * que a planilha já impunha.
 */
final class CorpoDeProvaController extends Controller
{
    public function __construct(
        private readonly RegistrarRompimento $registrar,
        private readonly DescartarCorpoDeProva $descartar,
    ) {
    }

    public function romper(
        RomperRequest $requisicao,
        string $obraCodigo,
        int $numero,
        string $identificacao,
    ): RedirectResponse {
        $destino = redirect()->to($requisicao->destino());

        try {
            $corpoDeProva = $this->registrar->executar(
                $obraCodigo,
                $numero,
                $identificacao,
                $requisicao->cargaEmKN(),
                $requisicao->diametroEmMm(),
                $requisicao->rompidoEm(),
            );
        } catch (ExcecaoDeDominio $erro) {
            // A entidade é quem sabe a regra da janela, e a mensagem dela já
            // está escrita para quem está na prensa: sobe intacta.
            return $destino->with('erro', $erro->getMessage());
        }

        return $destino->with('mensagem', sprintf(
            '%s rompido: %s.',
            $corpoDeProva->identificacao,
            $corpoDeProva->resultado()?->descricao() ?? '',
        ));
    }

    public function descartar(
        DescartarRequest $requisicao,
        string $obraCodigo,
        int $numero,
        string $identificacao,
    ): RedirectResponse {
        $destino = redirect()->to($requisicao->destino());

        try {
            $corpoDeProva = $this->descartar->executar(
                $obraCodigo,
                $numero,
                $identificacao,
                $requisicao->motivo(),
            );
        } catch (ExcecaoDeDominio $erro) {
            return $destino->with('erro', $erro->getMessage());
        }

        return $destino->with(
            'mensagem',
            "{$corpoDeProva->identificacao} descartado. O motivo ficou registrado.",
        );
    }
}
