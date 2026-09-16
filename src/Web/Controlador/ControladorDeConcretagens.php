<?php

declare(strict_types=1);

namespace ControleConcreto\Web\Controlador;

use DateTimeImmutable;
use ControleConcreto\Aplicacao\OperacoesDeConcretagem;
use ControleConcreto\Dominio\Concretagem\Concretagem;
use ControleConcreto\Dominio\Concretagem\RepositorioDeConcretagens;
use ControleConcreto\Dominio\Ensaio\DiametroDoCorpoDeProva;
use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;
use ControleConcreto\Dominio\Estrutura\RepositorioDeElementos;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Lote\RepositorioDeLotes;
use ControleConcreto\Dominio\Obra\Obra;
use ControleConcreto\Dominio\Obra\RepositorioDeObras;
use ControleConcreto\Web\Requisicao;
use ControleConcreto\Web\Resposta;
use ControleConcreto\Web\Sessao;
use ControleConcreto\Web\Visao;
use Throwable;

/**
 * A tela do dia de concretagem: abrir, receber cada caminhão, moldar os
 * corpos de prova, concluir.
 *
 * Os horários chegam do formulário só como hora (07:55), porque a data é a
 * da concretagem — o domínio recusa carga de outro dia de qualquer jeito.
 */
final class ControladorDeConcretagens
{
    public function __construct(
        private readonly RepositorioDeObras $obras,
        private readonly RepositorioDeElementos $elementos,
        private readonly RepositorioDeConcretagens $concretagens,
        private readonly RepositorioDeLotes $lotes,
        private readonly OperacoesDeConcretagem $operacoes,
        private readonly Visao $visao,
        private readonly Sessao $sessao,
    ) {
    }

    public function nova(Requisicao $requisicao): Resposta
    {
        $obra = $this->obras->porCodigo($requisicao->parametro('codigo'));

        if ($obra === null) {
            return $this->naoEncontrado('Obra não encontrada', "Nenhuma obra com o código {$requisicao->parametro('codigo')}.");
        }

        return Resposta::html($this->visao->renderizar('concretagens.nova', [
            'obra' => $obra,
            'elementos' => $this->elementos->daObra($obra->codigo),
            'hoje' => (new DateTimeImmutable('today'))->format('Y-m-d'),
            'erro' => $this->sessao->tirarErro(),
        ], 'Nova concretagem — ' . $obra->nome));
    }

    public function criar(Requisicao $requisicao): Resposta
    {
        $codigo = $requisicao->parametro('codigo');
        $formulario = caminho('obras', $codigo, 'concretagens', 'nova');

        if (!$this->sessao->tokenValido($requisicao->campo('token'))) {
            $this->sessao->guardarErro('A sessão expirou. Tente de novo.');

            return Resposta::redirecionar($formulario);
        }

        try {
            $data = $requisicao->campo('data');

            if ($data === '') {
                throw new ExcecaoDeDominio('Informe a data da concretagem.');
            }

            $concretagem = $this->operacoes->abrir(
                $codigo,
                $requisicao->campo('elemento'),
                new DateTimeImmutable($data),
                $requisicao->campo('fornecedor'),
                $requisicao->campo('responsavel'),
            );

            $this->sessao->guardarMensagem(sprintf(
                'Concretagem nº %d aberta para %s. Registre cada caminhão assim que chegar.',
                $concretagem->numero(),
                $concretagem->elemento->identificacao(),
            ));

            return Resposta::redirecionar(caminho('obras', $codigo, 'concretagens', $concretagem->numero()));
        } catch (ExcecaoDeDominio $erro) {
            $this->sessao->guardarErro($erro->getMessage());
        } catch (Throwable) {
            $this->sessao->guardarErro('Não foi possível abrir a concretagem. Confira os dados e tente de novo.');
        }

        return Resposta::redirecionar($formulario);
    }

    public function detalhe(Requisicao $requisicao): Resposta
    {
        [$obra, $concretagem] = $this->localizar($requisicao);

        if ($obra === null || $concretagem === null) {
            return $this->naoEncontrado(
                'Concretagem não encontrada',
                "Não existe concretagem de número {$requisicao->parametro('numero')} na obra {$requisicao->parametro('codigo')}.",
            );
        }

        return Resposta::html($this->visao->renderizar('concretagens.detalhe', [
            'obra' => $obra,
            'concretagem' => $concretagem,
            'loteNumero' => $this->lotes->loteDaConcretagem($obra->codigo, $concretagem->numero()),
            'agora' => new DateTimeImmutable('now'),
            'idades' => IdadeDeEnsaio::cases(),
            'diametros' => DiametroDoCorpoDeProva::cases(),
            'mensagem' => $this->sessao->tirarMensagem(),
            'erro' => $this->sessao->tirarErro(),
        ], sprintf('Concretagem nº %d — %s', $concretagem->numero(), $obra->nome)));
    }

    public function receberCarga(Requisicao $requisicao): Resposta
    {
        return $this->agir($requisicao, function (Obra $obra, Concretagem $concretagem, Requisicao $requisicao): string {
            $carga = $this->operacoes->receberCarga(
                $obra->codigo,
                $concretagem->numero(),
                $requisicao->campo('nota_fiscal'),
                $requisicao->campo('placa'),
                $requisicao->campoDecimal('volume_m3'),
                $this->momentoNoDia($concretagem, $requisicao->campo('saida'), 'a hora de saída da usina'),
                $this->momentoNoDia($concretagem, $requisicao->campo('chegada'), 'a hora de chegada'),
                $requisicao->campoInteiro('abatimento_mm'),
                $requisicao->campo('observacao'),
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
                'Carga %d (NF %s) aceita: %s m³, abatimento %d mm, %d min de transporte. Molde os corpos de prova.',
                $carga->numero,
                $carga->notaFiscal,
                number_format($carga->volumeEmM3, 1, ',', '.'),
                $carga->abatimentoMedidoEmMm,
                $carga->tempoDeTransporteEmMinutos(),
            );
        });
    }

    public function moldar(Requisicao $requisicao): Resposta
    {
        return $this->agir($requisicao, function (Obra $obra, Concretagem $concretagem, Requisicao $requisicao): string {
            // Caixas de seleção chegam como lista; campo() só lê texto.
            $marcadas = $requisicao->corpo['idades'] ?? [];

            $idades = array_map(
                static fn (string $dias): IdadeDeEnsaio => IdadeDeEnsaio::deDias((int) $dias),
                is_array($marcadas) ? array_filter($marcadas, 'is_string') : [],
            );

            $exemplares = $this->operacoes->moldar(
                $obra->codigo,
                $concretagem->numero(),
                $requisicao->campoInteiro('carga'),
                $this->momentoNoDia($concretagem, $requisicao->campo('hora'), 'a hora da moldagem'),
                $idades,
            );

            return sprintf(
                '%d exemplar(es) moldado(s) da carga %d: %s. Cada um tem dois corpos de prova, já na agenda do laboratório.',
                count($exemplares),
                $requisicao->campoInteiro('carga'),
                implode(', ', array_map(static fn ($e): string => $e->idade->rotulo(), $exemplares)),
            );
        });
    }

    public function concluir(Requisicao $requisicao): Resposta
    {
        return $this->agir($requisicao, function (Obra $obra, Concretagem $concretagem): string {
            $this->operacoes->concluir($obra->codigo, $concretagem->numero());

            return sprintf(
                'Concretagem nº %d concluída com %s m³. Quando os exemplares de 28 dias romperem, forme o lote.',
                $concretagem->numero(),
                number_format($concretagem->volumeAceitoEmM3(), 1, ',', '.'),
            );
        });
    }

    public function cancelar(Requisicao $requisicao): Resposta
    {
        return $this->agir($requisicao, function (Obra $obra, Concretagem $concretagem): string {
            $this->operacoes->cancelar($obra->codigo, $concretagem->numero());

            return "Concretagem nº {$concretagem->numero()} cancelada.";
        });
    }

    /**
     * O esqueleto comum das ações de POST: localizar, conferir o token, agir,
     * guardar a mensagem e voltar para a tela da concretagem.
     *
     * @param callable(Obra, Concretagem, Requisicao): string $acao devolve a mensagem de sucesso
     */
    private function agir(Requisicao $requisicao, callable $acao): Resposta
    {
        [$obra, $concretagem] = $this->localizar($requisicao);

        if ($obra === null || $concretagem === null) {
            return $this->naoEncontrado(
                'Concretagem não encontrada',
                "Não existe concretagem de número {$requisicao->parametro('numero')} na obra {$requisicao->parametro('codigo')}.",
            );
        }

        $destino = caminho('obras', $obra->codigo, 'concretagens', $concretagem->numero());

        if (!$this->sessao->tokenValido($requisicao->campo('token'))) {
            $this->sessao->guardarErro('A sessão expirou. Tente de novo.');

            return Resposta::redirecionar($destino);
        }

        try {
            $this->sessao->guardarMensagem($acao($obra, $concretagem, $requisicao));
        } catch (ExcecaoDeDominio $erro) {
            // Regra de negócio: a mensagem já está escrita para quem está no canteiro.
            $this->sessao->guardarErro($erro->getMessage());
        } catch (Throwable) {
            $this->sessao->guardarErro('Não foi possível registrar. Confira os dados e tente de novo.');
        }

        return Resposta::redirecionar($destino);
    }

    /** @return array{0: ?Obra, 1: ?Concretagem} */
    private function localizar(Requisicao $requisicao): array
    {
        $obra = $this->obras->porCodigo($requisicao->parametro('codigo'));

        if ($obra === null) {
            return [null, null];
        }

        return [$obra, $this->concretagens->porNumero($obra->codigo, (int) $requisicao->parametro('numero'))];
    }

    /** Junta a data da concretagem com uma hora vinda do formulário. */
    private function momentoNoDia(Concretagem $concretagem, string $hora, string $oQue): DateTimeImmutable
    {
        if (preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $hora) !== 1) {
            throw new ExcecaoDeDominio("Informe {$oQue} no formato HH:MM.");
        }

        return new DateTimeImmutable($concretagem->data->format('Y-m-d') . ' ' . $hora);
    }

    private function naoEncontrado(string $titulo, string $detalhe): Resposta
    {
        return Resposta::naoEncontrado($this->visao->renderizar('erro', [
            'titulo' => $titulo,
            'detalhe' => $detalhe,
        ], $titulo));
    }
}
