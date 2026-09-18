<?php

declare(strict_types=1);

namespace ControleConcreto\Web\Controlador;

use ControleConcreto\Dominio\Concretagem\Concretagem;
use ControleConcreto\Dominio\Concretagem\RepositorioDeConcretagens;
use ControleConcreto\Dominio\Concreto\Abatimento;
use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\Estrutura\ElementoEstrutural;
use ControleConcreto\Dominio\Estrutura\RepositorioDeElementos;
use ControleConcreto\Dominio\Estrutura\TipoDeElemento;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Lote\RepositorioDeLotes;
use ControleConcreto\Dominio\NaoConformidade\RepositorioDeNaoConformidades;
use ControleConcreto\Dominio\Obra\Obra;
use ControleConcreto\Dominio\Obra\RepositorioDeObras;
use ControleConcreto\Web\Requisicao;
use ControleConcreto\Web\Resposta;
use ControleConcreto\Web\Sessao;
use ControleConcreto\Web\Visao;
use Throwable;

/**
 * Cadastro de obras e de elementos, e a página que reúne tudo de uma obra:
 * as peças, as concretagens e os lotes.
 */
final class ControladorDeObras
{
    public function __construct(
        private readonly RepositorioDeObras $obras,
        private readonly RepositorioDeElementos $elementos,
        private readonly RepositorioDeConcretagens $concretagens,
        private readonly RepositorioDeLotes $lotes,
        private readonly RepositorioDeNaoConformidades $naoConformidades,
        private readonly Visao $visao,
        private readonly Sessao $sessao,
    ) {
    }

    public function lista(Requisicao $requisicao): Resposta
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

        return Resposta::html($this->visao->renderizar('obras.lista', [
            'linhas' => $linhas,
            'mensagem' => $this->sessao->tirarMensagem(),
            'erro' => $this->sessao->tirarErro(),
        ], 'Obras'));
    }

    public function nova(Requisicao $requisicao): Resposta
    {
        return Resposta::html($this->visao->renderizar('obras.nova', [
            'erro' => $this->sessao->tirarErro(),
        ], 'Nova obra'));
    }

    public function criar(Requisicao $requisicao): Resposta
    {
        if (!$this->sessao->tokenValido($requisicao->campo('token'))) {
            $this->sessao->guardarErro('A sessão expirou. Tente de novo.');

            return Resposta::redirecionar('/obras/nova');
        }

        try {
            $obra = new Obra(
                $requisicao->campo('codigo'),
                $requisicao->campo('nome'),
                $requisicao->campo('cliente'),
                $requisicao->campo('responsavel_tecnico'),
                $requisicao->campo('registro_profissional'),
            );

            // O repositório atualiza em silêncio quando o código já existe;
            // pela tela, cadastrar por cima de outra obra é erro, não edição.
            if ($this->obras->existe($obra->codigo)) {
                throw new ExcecaoDeDominio("Já existe uma obra com o código {$obra->codigo}.");
            }

            $this->obras->salvar($obra);
            $this->sessao->guardarMensagem("Obra {$obra->codigo} cadastrada. Agora cadastre os elementos que serão concretados.");

            return Resposta::redirecionar(caminho('obras', $obra->codigo));
        } catch (ExcecaoDeDominio $erro) {
            $this->sessao->guardarErro($erro->getMessage());
        } catch (Throwable) {
            $this->sessao->guardarErro('Não foi possível cadastrar a obra. Confira os dados e tente de novo.');
        }

        return Resposta::redirecionar('/obras/nova');
    }

    public function detalhe(Requisicao $requisicao): Resposta
    {
        $obra = $this->obras->porCodigo($requisicao->parametro('codigo'));

        if ($obra === null) {
            return $this->obraNaoEncontrada($requisicao->parametro('codigo'));
        }

        $concretagens = $this->concretagens->daObra($obra->codigo);

        // O que já entrou na forma de cada peça, para comparar com o previsto.
        $volumePorElemento = [];
        $lotePorConcretagem = [];

        foreach ($concretagens as $concretagem) {
            $codigo = $concretagem->elemento->codigo;
            $volumePorElemento[$codigo] = ($volumePorElemento[$codigo] ?? 0.0) + $concretagem->volumeAceitoEmM3();
            $lotePorConcretagem[$concretagem->numero()] = $this->lotes->loteDaConcretagem($obra->codigo, $concretagem->numero());
        }

        return Resposta::html($this->visao->renderizar('obras.detalhe', [
            'obra' => $obra,
            'elementos' => $this->elementos->daObra($obra->codigo),
            'volumePorElemento' => $volumePorElemento,
            'concretagens' => $concretagens,
            'lotePorConcretagem' => $lotePorConcretagem,
            'lotes' => $this->lotes->daObra($obra->codigo),
            'naoConformidades' => $this->naoConformidades->daObra($obra->codigo),
            'mensagem' => $this->sessao->tirarMensagem(),
            'erro' => $this->sessao->tirarErro(),
        ], $obra->nome));
    }

    public function novoElemento(Requisicao $requisicao): Resposta
    {
        $obra = $this->obras->porCodigo($requisicao->parametro('codigo'));

        if ($obra === null) {
            return $this->obraNaoEncontrada($requisicao->parametro('codigo'));
        }

        return Resposta::html($this->visao->renderizar('obras.novo-elemento', [
            'obra' => $obra,
            'tipos' => TipoDeElemento::cases(),
            'classes' => ClasseDeResistencia::cases(),
            'erro' => $this->sessao->tirarErro(),
        ], 'Novo elemento — ' . $obra->nome));
    }

    public function criarElemento(Requisicao $requisicao): Resposta
    {
        $obra = $this->obras->porCodigo($requisicao->parametro('codigo'));

        if ($obra === null) {
            return $this->obraNaoEncontrada($requisicao->parametro('codigo'));
        }

        $formulario = caminho('obras', $obra->codigo, 'elementos', 'novo');

        if (!$this->sessao->tokenValido($requisicao->campo('token'))) {
            $this->sessao->guardarErro('A sessão expirou. Tente de novo.');

            return Resposta::redirecionar($formulario);
        }

        try {
            $tipo = TipoDeElemento::tryFrom($requisicao->campo('tipo'))
                ?? throw new ExcecaoDeDominio('Escolha o tipo do elemento.');

            $elemento = new ElementoEstrutural(
                $requisicao->campo('codigo'),
                $tipo,
                $requisicao->campo('descricao'),
                $requisicao->campo('pavimento'),
                ClasseDeResistencia::deFck($requisicao->campoInteiro('fck')),
                new Abatimento($requisicao->campoInteiro('abatimento_mm')),
                $requisicao->campoDecimal('volume_previsto_m3'),
            );

            if ($this->elementos->porCodigo($obra->codigo, $elemento->codigo) !== null) {
                throw new ExcecaoDeDominio("Já existe o elemento {$elemento->codigo} nesta obra.");
            }

            $this->elementos->salvar($obra->codigo, $elemento);
            $this->sessao->guardarMensagem("Elemento {$elemento->codigo} cadastrado: {$elemento->classe->rotulo()}, abatimento {$elemento->abatimento->faixa()}.");

            return Resposta::redirecionar(caminho('obras', $obra->codigo));
        } catch (ExcecaoDeDominio $erro) {
            $this->sessao->guardarErro($erro->getMessage());
        } catch (Throwable) {
            $this->sessao->guardarErro('Não foi possível cadastrar o elemento. Confira os dados e tente de novo.');
        }

        return Resposta::redirecionar($formulario);
    }

    private function obraNaoEncontrada(string $codigo): Resposta
    {
        return Resposta::naoEncontrado($this->visao->renderizar('erro', [
            'titulo' => 'Obra não encontrada',
            'detalhe' => "Nenhuma obra com o código {$codigo}.",
        ], 'Obra não encontrada'));
    }
}
