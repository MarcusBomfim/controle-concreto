<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dominio\Concreto\Abatimento;
use App\Dominio\Concreto\ClasseDeResistencia;
use App\Dominio\Estrutura\ElementoEstrutural;
use App\Dominio\Estrutura\RepositorioDeElementos;
use App\Dominio\Estrutura\TipoDeElemento;
use App\Dominio\ExcecaoDeDominio;
use App\Dominio\Obra\Obra;
use App\Dominio\Obra\RepositorioDeObras;
use App\Http\Requests\GravarElementoRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ElementoController extends Controller
{
    public function __construct(
        private readonly RepositorioDeObras $obras,
        private readonly RepositorioDeElementos $elementos,
    ) {
    }

    public function create(string $obraCodigo): View
    {
        return view('elementos.create', [
            'obra' => $this->obraOuFalha($obraCodigo),
            'tipos' => TipoDeElemento::cases(),
            'classes' => ClasseDeResistencia::cases(),
        ]);
    }

    public function store(GravarElementoRequest $requisicao, string $obraCodigo): RedirectResponse
    {
        $obra = $this->obraOuFalha($obraCodigo);

        try {
            $elemento = new ElementoEstrutural(
                $requisicao->validated('codigo'),
                TipoDeElemento::from($requisicao->validated('tipo')),
                $requisicao->validated('descricao'),
                $requisicao->validated('pavimento'),
                ClasseDeResistencia::from((int) $requisicao->validated('fck')),
                new Abatimento((int) $requisicao->validated('abatimento_mm')),
                $requisicao->volumeEmM3(),
            );

            if ($this->elementos->porCodigo($obra->codigo, $elemento->codigo) !== null) {
                throw new ExcecaoDeDominio("Já existe o elemento {$elemento->codigo} nesta obra.");
            }

            $this->elementos->salvar($obra->codigo, $elemento);
        } catch (ExcecaoDeDominio $erro) {
            return back()->withInput()->with('erro', $erro->getMessage());
        }

        return redirect()
            ->route('obras.show', $obra->codigo)
            ->with('mensagem', sprintf(
                'Elemento %s cadastrado: %s, abatimento %s.',
                $elemento->codigo,
                $elemento->classe->rotulo(),
                $elemento->abatimento->faixa(),
            ));
    }

    private function obraOuFalha(string $codigo): Obra
    {
        return $this->obras->porCodigo($codigo)
            ?? throw new NotFoundHttpException("Nenhuma obra com o código {$codigo}.");
    }
}
