<?php

declare(strict_types=1);

namespace App\Aplicacao;

use App\Dominio\Concretagem\RepositorioDeConcretagens;
use App\Dominio\Concreto\ClasseDeResistencia;
use App\Dominio\Estrutura\GrupoDeSolicitacao;
use App\Dominio\ExcecaoDeDominio;
use App\Dominio\Lote\CondicaoDePreparo;
use App\Dominio\Lote\Lote;
use App\Dominio\Lote\RepositorioDeLotes;
use App\Dominio\Lote\TipoDeAmostragem;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * O engenheiro forma o lote escolhendo as concretagens.
 *
 * As regras de composição moram no Lote. O que fica aqui é o que o agregado
 * não sabe: se a concretagem já está em outro lote. Essa checagem é feita em
 * PHP para a mensagem ser boa, e repetida pela chave primária de
 * lote_concretagens para que duas pessoas formando lotes ao mesmo tempo não
 * gravem a mesma concretagem duas vezes.
 *
 * Comparado à versão em PHP puro: o `beginTransaction` / `commit` /
 * `rollBack` escrito à mão virou `DB::transaction`, que desfaz sozinho
 * quando a closure lança. A `PDOException` virou `QueryException`, que é
 * como o Laravel embrulha o erro do driver.
 */
final class FormarLote
{
    public function __construct(
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

        try {
            $numero = DB::transaction(fn (): int => $this->lotes->salvar($lote));
        } catch (QueryException $erro) {
            // A corrida que a verificação em PHP não pega: as duas leram
            // antes de qualquer uma escrever, e a chave primária barrou a
            // segunda. Traduzir aqui é o que transforma erro de banco em
            // instrução para quem está na tela.
            if (str_contains($erro->getMessage(), 'lote_concretagens.obra_codigo, lote_concretagens.concretagem_numero')) {
                throw new ExcecaoDeDominio(
                    'Uma das concretagens acabou de entrar em outro lote. Recarregue e forme o lote de novo.'
                );
            }

            throw $erro;
        }

        $lote->definirNumero($numero);

        return $lote;
    }
}
