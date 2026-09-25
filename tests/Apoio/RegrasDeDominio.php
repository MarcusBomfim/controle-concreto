<?php

declare(strict_types=1);

namespace Tests\Apoio;

use App\Dominio\ExcecaoDeDominio;
use Throwable;

/**
 * Afirmações sobre regras de negócio.
 *
 * O expectException() do PHPUnit cobre uma exceção por teste, e várias
 * regras deste domínio se provam com dois ou três disparos seguidos
 * ("recusa fck 27, recusa fck 65"). Este ajudante confere cada disparo no
 * lugar, com a mensagem — porque a mensagem é parte do contrato: é o que
 * aparece na tela para quem está no canteiro ou na prensa.
 */
trait RegrasDeDominio
{
    protected function recusa(callable $acao, string $trechoDaMensagem = ''): void
    {
        try {
            $acao();
        } catch (ExcecaoDeDominio $erro) {
            if ($trechoDaMensagem !== '') {
                $this->assertStringContainsString(
                    $trechoDaMensagem,
                    $erro->getMessage(),
                    'a mensagem da recusa deveria explicar o motivo',
                );
            }

            $this->addToAssertionCount(1);

            return;
        }

        $this->fail(
            'Esperava ExcecaoDeDominio'
            . ($trechoDaMensagem === '' ? '' : " com \"{$trechoDaMensagem}\"")
            . ', mas nada foi lançado.'
        );
    }

    /** Recusa de outro tipo — PDOException vinda do banco, por exemplo. */
    protected function recusaCom(string $classe, callable $acao, string $trechoDaMensagem = ''): void
    {
        try {
            $acao();
        } catch (Throwable $erro) {
            $this->assertInstanceOf($classe, $erro);

            if ($trechoDaMensagem !== '') {
                $this->assertStringContainsString($trechoDaMensagem, $erro->getMessage());
            }

            return;
        }

        $this->fail("Esperava {$classe}, mas nada foi lançado.");
    }

    /** Compara floats com tolerância, para não brigar com ponto flutuante. */
    protected function assertAproximado(float $esperado, float $obtido, string $contexto = ''): void
    {
        $this->assertEqualsWithDelta($esperado, $obtido, 0.001, $contexto);
    }
}
