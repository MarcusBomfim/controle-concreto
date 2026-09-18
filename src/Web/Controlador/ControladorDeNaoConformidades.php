<?php

declare(strict_types=1);

namespace ControleConcreto\Web\Controlador;

use DateTimeImmutable;
use ControleConcreto\Aplicacao\TratarNaoConformidade;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Lote\RepositorioDeLotes;
use ControleConcreto\Dominio\NaoConformidade\Desfecho;
use ControleConcreto\Dominio\NaoConformidade\Providencia;
use ControleConcreto\Dominio\NaoConformidade\RepositorioDeNaoConformidades;
use ControleConcreto\Dominio\NaoConformidade\ResultadoDaProvidencia;
use ControleConcreto\Dominio\NaoConformidade\TipoDeProvidencia;
use ControleConcreto\Dominio\Obra\RepositorioDeObras;
use ControleConcreto\Web\Requisicao;
use ControleConcreto\Web\Resposta;
use ControleConcreto\Web\Sessao;
use ControleConcreto\Web\Visao;
use Throwable;

/**
 * A página do lote reprovado: o histórico do tratamento, o formulário da
 * próxima providência e o encerramento — que só aparece com os desfechos
 * que as providências já sustentam.
 */
final class ControladorDeNaoConformidades
{
    public function __construct(
        private readonly RepositorioDeObras $obras,
        private readonly RepositorioDeLotes $lotes,
        private readonly RepositorioDeNaoConformidades $naoConformidades,
        private readonly TratarNaoConformidade $tratar,
        private readonly Visao $visao,
        private readonly Sessao $sessao,
    ) {
    }

    public function detalhe(Requisicao $requisicao): Resposta
    {
        $obra = $this->obras->porCodigo($requisicao->parametro('codigo'));
        $numero = (int) $requisicao->parametro('numero');
        $lote = $obra === null ? null : $this->lotes->porNumero($obra->codigo, $numero);
        $naoConformidade = $obra === null ? null : $this->naoConformidades->doLote($obra->codigo, $numero);

        if ($obra === null || $lote === null || $naoConformidade === null) {
            return Resposta::naoEncontrado($this->visao->renderizar('erro', [
                'titulo' => 'Não conformidade não encontrada',
                'detalhe' => "O lote {$numero} da obra {$requisicao->parametro('codigo')} não tem não conformidade: ou foi aceito, ou ainda não foi julgado.",
            ], 'Não encontrada'));
        }

        return Resposta::html($this->visao->renderizar('nao-conformidades.detalhe', [
            'obra' => $obra,
            'lote' => $lote,
            'naoConformidade' => $naoConformidade,
            'tipos' => TipoDeProvidencia::cases(),
            'resultados' => ResultadoDaProvidencia::cases(),
            'hoje' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'mensagem' => $this->sessao->tirarMensagem(),
            'erro' => $this->sessao->tirarErro(),
        ], sprintf('Não conformidade do lote %d — %s', $lote->numero(), $obra->nome)));
    }

    public function registrarProvidencia(Requisicao $requisicao): Resposta
    {
        return $this->agir($requisicao, function (string $codigo, int $numero, Requisicao $requisicao): string {
            $tipo = TipoDeProvidencia::tryFrom($requisicao->campo('tipo'))
                ?? throw new ExcecaoDeDominio('Escolha o tipo da providência.');

            $resultado = ResultadoDaProvidencia::tryFrom($requisicao->campo('resultado'))
                ?? throw new ExcecaoDeDominio('Informe o resultado da providência.');

            $data = $requisicao->campo('realizada_em');

            if ($data === '') {
                throw new ExcecaoDeDominio('Informe a data em que a providência foi realizada.');
            }

            $fck = $requisicao->campo('fck_obtido_mpa');

            $providencia = new Providencia(
                $tipo,
                new DateTimeImmutable($data),
                $requisicao->campo('descricao'),
                $resultado,
                $requisicao->campo('responsavel'),
                $fck === '' ? null : $requisicao->campoDecimal('fck_obtido_mpa'),
            );

            $naoConformidade = $this->tratar->registrarProvidencia($codigo, $numero, $providencia);

            $possiveis = $naoConformidade->desfechosPossiveis();

            return sprintf(
                '%s registrada como %s.%s',
                $providencia->tipo->rotulo(),
                mb_strtolower($providencia->resultado->rotulo()),
                $possiveis === [] ? '' : ' Já é possível encerrar: ' . implode(', ', array_map(
                    static fn (Desfecho $d): string => mb_strtolower($d->rotulo()),
                    $possiveis,
                )) . '.',
            );
        });
    }

    public function encerrar(Requisicao $requisicao): Resposta
    {
        return $this->agir($requisicao, function (string $codigo, int $numero, Requisicao $requisicao): string {
            $desfecho = Desfecho::tryFrom($requisicao->campo('desfecho'))
                ?? throw new ExcecaoDeDominio('Escolha o desfecho.');

            $naoConformidade = $this->tratar->encerrar($codigo, $numero, $desfecho, $requisicao->campo('parecer'));

            return sprintf('Não conformidade do lote %d encerrada: %s.', $naoConformidade->loteNumero, $desfecho->rotulo());
        });
    }

    /** @param callable(string, int, Requisicao): string $acao devolve a mensagem de sucesso */
    private function agir(Requisicao $requisicao, callable $acao): Resposta
    {
        $codigo = $requisicao->parametro('codigo');
        $numero = (int) $requisicao->parametro('numero');
        $destino = caminho('obras', $codigo, 'lotes', $numero, 'nao-conformidade');

        if (!$this->sessao->tokenValido($requisicao->campo('token'))) {
            $this->sessao->guardarErro('A sessão expirou. Tente de novo.');

            return Resposta::redirecionar($destino);
        }

        try {
            $this->sessao->guardarMensagem($acao($codigo, $numero, $requisicao));
        } catch (ExcecaoDeDominio $erro) {
            $this->sessao->guardarErro($erro->getMessage());
        } catch (Throwable) {
            $this->sessao->guardarErro('Não foi possível registrar. Confira os dados e tente de novo.');
        }

        return Resposta::redirecionar($destino);
    }
}
