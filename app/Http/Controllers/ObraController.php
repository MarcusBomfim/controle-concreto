<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Dominio\Concretagem\Concretagem;
use App\Dominio\Concretagem\RepositorioDeConcretagens;
use App\Dominio\Estrutura\RepositorioDeElementos;
use App\Dominio\ExcecaoDeDominio;
use App\Dominio\Lote\RepositorioDeLotes;
use App\Dominio\Obra\Obra;
use App\Dominio\Obra\RepositorioDeObras;
use App\Http\Requests\GravarObraRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Cadastro de obras e a página que reúne tudo de uma obra.
 *
 * As dependências chegam pelo construtor, resolvidas pelo Service Container:
 * o controlador pede as interfaces do domínio e não sabe que existe banco
 * do outro lado. É a mesma ideia da classe Montagem da versão em PHP puro,
 * com a diferença de que ninguém precisa escrever a montagem.
 */
final class ObraController extends Controller
{
    public function __construct(
        private readonly RepositorioDeObras $obras,
        private readonly RepositorioDeElementos $elementos,
        private readonly RepositorioDeConcretagens $concretagens,
        private readonly RepositorioDeLotes $lotes,
    ) {
    }

    public function index(): View
    {
        $linhas = [];

        foreach ($this->obras->todas() as $obra) {
            $concretagens = $this->concretagens->daObra($obra->codigo);

            $linhas[] = [
                'obra' => $obra,
                'elementos' => count($this->elementos->daObra($obra->codigo)),
                'concretagens' => count($concretagens),
                'emAndamento' => count(array_filter(
                    $concretagens,
                    static fn (Concretagem $c): bool => $c->situacao()->aceitaCarga(),
                )),
                'lotes' => count($this->lotes->daObra($obra->codigo)),
            ];
        }

        return view('obras.index', ['linhas' => $linhas]);
    }

    public function create(): View
    {
        return view('obras.create');
    }

    public function store(GravarObraRequest $requisicao): RedirectResponse
    {
        try {
            $obra = new Obra(
                $requisicao->validated('codigo'),
                $requisicao->validated('nome'),
                $requisicao->validated('cliente'),
                $requisicao->validated('responsavel_tecnico'),
                $requisicao->validated('registro_profissional'),
            );

            // O repositório atualiza em silêncio quando o código já existe;
            // pela tela, cadastrar por cima de outra obra é erro, não edição.
            if ($this->obras->existe($obra->codigo)) {
                throw new ExcecaoDeDominio("Já existe uma obra com o código {$obra->codigo}.");
            }

            $this->obras->salvar($obra);
        } catch (ExcecaoDeDominio $erro) {
            /*
             * A mensagem do domínio já está escrita para quem vai ler —
             * withInput() devolve o que foi digitado, para o formulário não
             * voltar vazio.
             */
            return back()->withInput()->with('erro', $erro->getMessage());
        }

        return redirect()
            ->route('obras.show', $obra->codigo)
            ->with('mensagem', "Obra {$obra->codigo} cadastrada. Agora cadastre os elementos que serão concretados.");
    }

    public function show(string $codigo): View
    {
        $obra = $this->obras->porCodigo($codigo);

        if ($obra === null) {
            throw new NotFoundHttpException("Nenhuma obra com o código {$codigo}.");
        }

        $concretagens = $this->concretagens->daObra($obra->codigo);

        // O que já entrou na forma de cada peça, para comparar com o previsto.
        $volumePorElemento = [];

        foreach ($concretagens as $concretagem) {
            $codigoDoElemento = $concretagem->elemento->codigo;

            $volumePorElemento[$codigoDoElemento] =
                ($volumePorElemento[$codigoDoElemento] ?? 0.0) + $concretagem->volumeAceitoEmM3();
        }

        return view('obras.show', [
            'obra' => $obra,
            'elementos' => $this->elementos->daObra($obra->codigo),
            'volumePorElemento' => $volumePorElemento,
            'concretagens' => $concretagens,
            'lotes' => $this->lotes->daObra($obra->codigo),
        ]);
    }
}
