<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio;

use App\Dominio\ExcecaoDeDominio;
use App\Dominio\Lote\CalculadoraDeFckEstimado;
use App\Dominio\Lote\CondicaoDePreparo;
use App\Dominio\Lote\Psi6;
use App\Dominio\Lote\TipoDeAmostragem;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Apoio\ObjetosDeExemplo;
use Tests\Apoio\RegrasDeDominio;

/*
 * Teste de domínio puro: estende o TestCase do PHPUnit, e não o do Laravel.
 * Não sobe o container nem toca em banco — prova só a regra.
 */
final class FckEstimadoTest extends TestCase
{
    use ObjetosDeExemplo;
    use RegrasDeDominio;

    // ---------- ψ6: a tabela ----------

    #[Test]
    public function le_a_tabela_por_n_e_condicao(): void
    {
        $this->assertAproximado(0.86, Psi6::para(6, CondicaoDePreparo::A));
        $this->assertAproximado(0.81, Psi6::para(6, CondicaoDePreparo::B));
        $this->assertAproximado(0.81, Psi6::para(6, CondicaoDePreparo::C), 'B e C dividem a linha');
        $this->assertAproximado(0.91, Psi6::para(16, CondicaoDePreparo::A));
    }

    #[Test]
    public function n_intermediario_usa_o_maior_n_tabulado_abaixo_o_lado_conservador(): void
    {
        $this->assertAproximado(0.87, Psi6::para(9, CondicaoDePreparo::A), 'usa n = 8');
        $this->assertAproximado(0.88, Psi6::para(11, CondicaoDePreparo::A), 'usa n = 10');
    }

    #[Test]
    public function acima_de_16_vale_o_de_16(): void
    {
        $this->assertAproximado(0.89, Psi6::para(100, CondicaoDePreparo::C));
    }

    #[Test]
    public function recusa_n_menor_que_2(): void
    {
        $this->recusa( fn () => Psi6::para(1, CondicaoDePreparo::A));
    }

    // ---------- fck estimado: amostragem parcial ----------

    #[Test]
    public function n_6_formula_2_f1_f2_2_f3_com_piso_6_f1(): void
    {
        // Ordenado: 29,8 30,5 31,1 32,4 33,6 35,0 → m = 3
        // fórmula = 2·(29,8 + 30,5)/2 − 31,1 = 60,3 − 31,1 = 29,2
        // piso    = 0,86 × 29,8 = 25,6
        $e = CalculadoraDeFckEstimado::calcular(
            [32.4, 29.8, 31.1, 33.6, 30.5, 35.0],
            TipoDeAmostragem::Parcial,
            CondicaoDePreparo::A,
        );

        $this->assertAproximado(29.2, $e->fckEstimadoEmMPa);
        $this->assertAproximado(29.2, $e->valorDaFormula ?? 0.0);
        $this->assertAproximado(0.86, $e->psi6 ?? 0.0);
        $this->assertAproximado(25.6, $e->pisoDoPsi6 ?? 0.0);
        $this->assertFalse($e->pisoPrevaleceu(), 'a fórmula venceu');
        $this->assertSame([29.8, 30.5, 31.1, 32.4, 33.6, 35.0], $e->valoresOrdenados, 'ordenou');
        $this->assertSame(6, $e->numeroDeExemplares);
    }

    #[Test]
    public function o_piso_de_6_prevalece_quando_a_formula_cai_demais(): void
    {
        // Ordenado: 30,0 30,2 36,0 36,5 37,0 38,0 → m = 3
        // fórmula = 2·(30,0 + 30,2)/2 − 36,0 = 60,2 − 36,0 = 24,2
        // piso    = 0,86 × 30,0 = 25,8  → vale o piso
        $e = CalculadoraDeFckEstimado::calcular(
            [30.0, 30.2, 36.0, 36.5, 37.0, 38.0],
            TipoDeAmostragem::Parcial,
            CondicaoDePreparo::A,
        );

        $this->assertAproximado(25.8, $e->fckEstimadoEmMPa);
        $this->assertAproximado(24.2, $e->valorDaFormula ?? 0.0);
        $this->assertTrue($e->pisoPrevaleceu(), 'o piso venceu');
    }

    #[Test]
    public function n_impar_despreza_o_maior_valor(): void
    {
        // n = 7 → m = 3. O 40,0 nem entra na conta.
        // fórmula = 2·(28 + 29)/2 − 30 = 57 − 30 = 27,0
        // piso    = 0,87 × 28 = 24,4
        $e = CalculadoraDeFckEstimado::calcular(
            [40.0, 33.0, 28.0, 31.0, 29.0, 32.0, 30.0],
            TipoDeAmostragem::Parcial,
            CondicaoDePreparo::A,
        );

        $this->assertAproximado(27.0, $e->fckEstimadoEmMPa);
        $this->assertAproximado(0.87, $e->psi6 ?? 0.0, 'ψ6 de n = 7');
    }

    #[Test]
    public function a_condicao_de_preparo_muda_o_piso(): void
    {
        // Mesmos dados do primeiro caso, condição C: ψ6 = 0,81 → piso = 24,1.
        $e = CalculadoraDeFckEstimado::calcular(
            [32.4, 29.8, 31.1, 33.6, 30.5, 35.0],
            TipoDeAmostragem::Parcial,
            CondicaoDePreparo::C,
        );

        $this->assertAproximado(0.81, $e->psi6 ?? 0.0);
        $this->assertAproximado(24.1, $e->pisoDoPsi6 ?? 0.0);
        $this->assertAproximado(29.2, $e->fckEstimadoEmMPa, 'a fórmula continua vencendo');
    }

    #[Test]
    public function menos_de_6_exemplares_e_amostra_insuficiente(): void
    {
        $this->recusa(
            fn () => CalculadoraDeFckEstimado::calcular(
                [30.0, 31.0, 32.0, 33.0, 34.0],
                TipoDeAmostragem::Parcial,
                CondicaoDePreparo::A,
            ),
            'ao menos 6 exemplares',
        );
    }

    #[Test]
    public function n_20_na_parcial_ja_usa_o_percentil(): void
    {
        // i = ⌈0,05 × 20⌉ = 1 → f1.
        $valores = range(30.0, 49.0, 1.0);

        $e = CalculadoraDeFckEstimado::calcular($valores, TipoDeAmostragem::Parcial, CondicaoDePreparo::A);

        $this->assertAproximado(30.0, $e->fckEstimadoEmMPa);
        $this->assertSame(null, $e->psi6, 'sem piso no percentil');
    }

    // ---------- fck estimado: amostragem total ----------

    #[Test]
    public function n_20_vale_o_menor_exemplar(): void
    {
        $e = CalculadoraDeFckEstimado::calcular(
            [31.0, 28.5, 33.0, 30.0],
            TipoDeAmostragem::Total,
            CondicaoDePreparo::A,
        );

        $this->assertAproximado(28.5, $e->fckEstimadoEmMPa);
        $this->assertSame(null, $e->valorDaFormula);
    }

    #[Test]
    public function n_20_vale_o_percentil_de_5(): void
    {
        // 25 valores de 25,0 a 49,0. i = ⌈0,05 × 25⌉ = ⌈1,25⌉ = 2 → f2 = 26,0.
        $valores = range(25.0, 49.0, 1.0);

        $e = CalculadoraDeFckEstimado::calcular($valores, TipoDeAmostragem::Total, CondicaoDePreparo::A);

        $this->assertAproximado(26.0, $e->fckEstimadoEmMPa);
        $this->assertSame(25, $e->numeroDeExemplares);
    }

    #[Test]
    public function n_40_i_2(): void
    {
        // ⌈0,05 × 40⌉ = ⌈2,0⌉ = 2 → f2.
        $valores = range(20.0, 59.0, 1.0);

        $this->assertAproximado(21.0, CalculadoraDeFckEstimado::calcular($valores, TipoDeAmostragem::Total, CondicaoDePreparo::A)->fckEstimadoEmMPa);
    }

    #[Test]
    public function sem_exemplar_nao_ha_conta(): void
    {
        $this->recusa(
            fn () => CalculadoraDeFckEstimado::calcular([], TipoDeAmostragem::Total, CondicaoDePreparo::A),
            'Não há exemplar',
        );
    }

    // ---------- Estimativa: memória de cálculo ----------

    #[Test]
    public function sabe_se_atende_o_fck_de_projeto(): void
    {
        $e = CalculadoraDeFckEstimado::calcular(
            [32.4, 29.8, 31.1, 33.6, 30.5, 35.0],
            TipoDeAmostragem::Parcial,
            CondicaoDePreparo::A,
        );

        $this->assertTrue($e->atende(25.0), '29,2 atende C25');
        $this->assertFalse($e->atende(30.0), '29,2 não atende C30');
        $this->assertAproximado(29.8, $e->menorExemplar());
        $this->assertAproximado(35.0, $e->maiorExemplar());
        $this->assertAproximado(32.1, $e->media());
    }
}
