<?php

declare(strict_types=1);

namespace ControleConcreto\Web\Controlador;

use ControleConcreto\Aplicacao\FormarLote;
use ControleConcreto\Aplicacao\JulgarLote;
use ControleConcreto\Dominio\Concretagem\Concretagem;
use ControleConcreto\Dominio\Concretagem\RepositorioDeConcretagens;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Lote\CondicaoDePreparo;
use ControleConcreto\Dominio\Lote\RepositorioDeLotes;
use ControleConcreto\Dominio\Lote\TipoDeAmostragem;
use ControleConcreto\Dominio\Obra\RepositorioDeObras;
use ControleConcreto\Web\Requisicao;
use ControleConcreto\Web\Resposta;
use ControleConcreto\Web\Sessao;
use ControleConcreto\Web\Visao;
use Throwable;

/**
 * Formar o lote e julgá-lo — e mostrar a conta inteira quando julgado.
 *
 * A classe e o grupo do lote não são perguntados: saem da primeira
 * concretagem escolhida. Se as outras não combinarem, o domínio recusa com
 * a mensagem certa. Pedir para digitar "C30" de novo só abriria espaço para
 * o engano que a regra existe para impedir.
 */
final class ControladorDeLotes
{
    public function __construct(
        private readonly RepositorioDeObras $obras,
        private readonly RepositorioDeConcretagens $concretagens,
        private readonly RepositorioDeLotes $lotes,
        private readonly FormarLote $formar,
        private readonly JulgarLote $julgar,
        private readonly Visao $visao,
        private readonly Sessao $sessao,
    ) {
    }

    public function novo(Requisicao $requisicao): Resposta
    {
        $obra = $this->obras->porCodigo($requisicao->parametro('codigo'));

        if ($obra === null) {
            return $this->naoEncontrado('Obra não encontrada', "Nenhuma obra com o código {$requisicao->parametro('codigo')}.");
        }

        // Só entra em lote concretagem concluída que ainda não está em nenhum.
        $disponiveis = array_values(array_filter(
            $this->concretagens->daObra($obra->codigo),
            fn (Concretagem $c): bool => $c->estaConcluida()
                && $this->lotes->loteDaConcretagem($obra->codigo, $c->numero()) === null,
        ));

        return Resposta::html($this->visao->renderizar('lotes.novo', [
            'obra' => $obra,
            'disponiveis' => $disponiveis,
            'condicoes' => CondicaoDePreparo::cases(),
            'amostragens' => TipoDeAmostragem::cases(),
            'erro' => $this->sessao->tirarErro(),
        ], 'Novo lote — ' . $obra->nome));
    }

    public function formar(Requisicao $requisicao): Resposta
    {
        $obra = $this->obras->porCodigo($requisicao->parametro('codigo'));

        if ($obra === null) {
            return $this->naoEncontrado('Obra não encontrada', "Nenhuma obra com o código {$requisicao->parametro('codigo')}.");
        }

        $formulario = caminho('obras', $obra->codigo, 'lotes', 'novo');

        if (!$this->sessao->tokenValido($requisicao->campo('token'))) {
            $this->sessao->guardarErro('A sessão expirou. Tente de novo.');

            return Resposta::redirecionar($formulario);
        }

        try {
            $marcadas = $requisicao->corpo['concretagens'] ?? [];
            $numeros = array_map('intval', is_array($marcadas) ? array_filter($marcadas, 'is_string') : []);

            if ($numeros === []) {
                throw new ExcecaoDeDominio('Escolha ao menos uma concretagem para o lote.');
            }

            $primeira = $this->concretagens->porNumero($obra->codigo, $numeros[array_key_first($numeros)])
                ?? throw new ExcecaoDeDominio('Uma das concretagens escolhidas não existe mais.');

            $condicao = CondicaoDePreparo::tryFrom($requisicao->campo('condicao'))
                ?? throw new ExcecaoDeDominio('Escolha a condição de preparo do concreto.');

            $amostragem = TipoDeAmostragem::tryFrom($requisicao->campo('amostragem'))
                ?? throw new ExcecaoDeDominio('Escolha o tipo de amostragem.');

            $lote = $this->formar->executar(
                $obra->codigo,
                $primeira->elemento->classe,
                $primeira->elemento->tipo->grupo(),
                $condicao,
                $amostragem,
                array_values($numeros),
            );

            $this->sessao->guardarMensagem(sprintf(
                '%s formado com %d concretagem(ns) e %s m³. Julgue quando todos os exemplares de 28 dias estiverem resolvidos.',
                $lote->identificacao(),
                count($lote->concretagens()),
                number_format($lote->volumeEmM3(), 1, ',', '.'),
            ));

            return Resposta::redirecionar(caminho('obras', $obra->codigo, 'lotes', $lote->numero()));
        } catch (ExcecaoDeDominio $erro) {
            $this->sessao->guardarErro($erro->getMessage());
        } catch (Throwable) {
            $this->sessao->guardarErro('Não foi possível formar o lote. Confira os dados e tente de novo.');
        }

        return Resposta::redirecionar($formulario);
    }

    public function detalhe(Requisicao $requisicao): Resposta
    {
        $obra = $this->obras->porCodigo($requisicao->parametro('codigo'));
        $lote = $obra === null ? null : $this->lotes->porNumero($obra->codigo, (int) $requisicao->parametro('numero'));

        if ($obra === null || $lote === null) {
            return $this->naoEncontrado(
                'Lote não encontrado',
                "Não existe lote de número {$requisicao->parametro('numero')} na obra {$requisicao->parametro('codigo')}.",
            );
        }

        return Resposta::html($this->visao->renderizar('lotes.detalhe', [
            'obra' => $obra,
            'lote' => $lote,
            'mensagem' => $this->sessao->tirarMensagem(),
            'erro' => $this->sessao->tirarErro(),
        ], sprintf('Lote nº %d — %s', $lote->numero(), $obra->nome)));
    }

    public function julgar(Requisicao $requisicao): Resposta
    {
        $codigo = $requisicao->parametro('codigo');
        $numero = (int) $requisicao->parametro('numero');
        $destino = caminho('obras', $codigo, 'lotes', $numero);

        if (!$this->sessao->tokenValido($requisicao->campo('token'))) {
            $this->sessao->guardarErro('A sessão expirou. Tente de novo.');

            return Resposta::redirecionar($destino);
        }

        try {
            $lote = $this->julgar->executar($codigo, $numero);
            $estimativa = $lote->estimativa();

            $this->sessao->guardarMensagem(sprintf(
                'Lote nº %d julgado: fck,est = %s MPa contra fck = %s MPa. %s.',
                $lote->numero(),
                number_format($estimativa?->fckEstimadoEmMPa ?? 0.0, 1, ',', '.'),
                number_format($lote->classe->fck(), 1, ',', '.'),
                $lote->situacao()->rotulo(),
            ));
        } catch (ExcecaoDeDominio $erro) {
            $this->sessao->guardarErro($erro->getMessage());
        }

        return Resposta::redirecionar($destino);
    }

    private function naoEncontrado(string $titulo, string $detalhe): Resposta
    {
        return Resposta::naoEncontrado($this->visao->renderizar('erro', [
            'titulo' => $titulo,
            'detalhe' => $detalhe,
        ], $titulo));
    }
}
