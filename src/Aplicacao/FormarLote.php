<?php

declare(strict_types=1);

namespace ControleConcreto\Aplicacao;

use ControleConcreto\Dominio\Concretagem\RepositorioDeConcretagens;
use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\Estrutura\GrupoDeSolicitacao;
use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\Lote\CondicaoDePreparo;
use ControleConcreto\Dominio\Lote\Lote;
use ControleConcreto\Dominio\Lote\RepositorioDeLotes;
use ControleConcreto\Dominio\Lote\TipoDeAmostragem;
use PDO;
use PDOException;
use Throwable;

/**
 * O engenheiro forma o lote escolhendo as concretagens.
 *
 * As regras de composição moram no Lote. O que fica aqui é o que o
 * agregado não sabe: se a concretagem já está em outro lote. Essa checagem
 * é feita em PHP para a mensagem ser boa, e repetida pela chave primária
 * de lote_concretagens para que duas pessoas formando lotes ao mesmo tempo
 * não gravem a mesma concretagem duas vezes.
 */
final class FormarLote
{
    public function __construct(
        private readonly PDO $conexao,
        private readonly RepositorioDeConcretagens $concretagens,
        private readonly RepositorioDeLotes $lotes,
    ) {
    }

    /** @param int[] $numerosDeConcretagem */
    public function executar(
        string $obraCodigo,
        ClasseDeResistencia $classe,
        GrupoDeSolicitacao $grupo,
        CondicaoDePreparo $condicao,
        TipoDeAmostragem $amostragem,
        array $numerosDeConcretagem,
    ): Lote {
        if ($numerosDeConcretagem === []) {
            throw new ExcecaoDeDominio('Escolha ao menos uma concretagem para o lote.');
        }

        $lote = new Lote($obraCodigo, $classe, $grupo, $condicao, $amostragem);

        foreach (array_unique($numerosDeConcretagem) as $numero) {
            $concretagem = $this->concretagens->porNumero($obraCodigo, $numero);

            if ($concretagem === null) {
                throw new ExcecaoDeDominio("Não existe concretagem de número {$numero} na obra {$obraCodigo}.");
            }

            $loteAtual = $this->lotes->loteDaConcretagem($obraCodigo, $numero);

            if ($loteAtual !== null) {
                throw new ExcecaoDeDominio(sprintf(
                    'A concretagem %d já está no lote %d. Uma concretagem entra em um lote só.',
                    $numero,
                    $loteAtual,
                ));
            }

            $lote->adicionarConcretagem($concretagem);
        }

        $this->conexao->beginTransaction();

        try {
            $numero = $this->lotes->salvar($lote);
            $this->conexao->commit();

            $lote->definirNumero($numero);

            return $lote;
        } catch (PDOException $erro) {
            $this->conexao->rollBack();

            if (str_contains($erro->getMessage(), 'lote_concretagens.obra_codigo, lote_concretagens.concretagem_numero')) {
                throw new ExcecaoDeDominio(
                    'Uma das concretagens acabou de entrar em outro lote. Recarregue e forme o lote de novo.'
                );
            }

            throw $erro;
        } catch (Throwable $erro) {
            $this->conexao->rollBack();

            throw $erro;
        }
    }
}
