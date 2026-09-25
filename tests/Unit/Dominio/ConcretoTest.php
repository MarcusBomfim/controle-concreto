<?php

declare(strict_types=1);

namespace Tests\Unit\Dominio;

use App\Dominio\Concreto\Abatimento;
use App\Dominio\Concreto\ClasseDeResistencia;
use App\Dominio\ExcecaoDeDominio;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Tests\Apoio\ObjetosDeExemplo;
use Tests\Apoio\RegrasDeDominio;

/*
 * Teste de domínio puro: estende o TestCase do PHPUnit, e não o do Laravel.
 * Não sobe o container nem toca em banco — prova só a regra.
 */
final class ConcretoTest extends TestCase
{
    use ObjetosDeExemplo;
    use RegrasDeDominio;

    // ---------- Classe de resistência ----------

    #[Test]
    public function o_valor_do_caso_e_o_fck_em_mpa(): void
    {
        $this->assertAproximado(30.0, ClasseDeResistencia::C30->fck());
        $this->assertSame('C30', ClasseDeResistencia::C30->rotulo());
    }

    #[Test]
    public function encontra_a_classe_pelo_fck(): void
    {
        $this->assertSame(ClasseDeResistencia::C25, ClasseDeResistencia::deFck(25));
    }

    #[Test]
    public function recusa_fck_fora_das_classes_da_norma(): void
    {
        // A NBR 8953 não tem C27, e acima de C60 pula de 10 em 10: não há C65.
        $this->recusa( fn () => ClasseDeResistencia::deFck(27), 'Não existe classe');
        $this->recusa( fn () => ClasseDeResistencia::deFck(65), 'Não existe classe');
    }

    #[Test]
    public function c15_nao_e_estrutural_c20_em_diante_e(): void
    {
        $this->assertFalse(ClasseDeResistencia::C15->ehEstrutural(), 'C15');
        $this->assertTrue(ClasseDeResistencia::C20->ehEstrutural(), 'C20');
        $this->assertTrue(ClasseDeResistencia::C50->ehEstrutural(), 'C50');
    }

    #[Test]
    public function grupo_ii_e_alto_desempenho(): void
    {
        $this->assertFalse(ClasseDeResistencia::C50->ehDeAltoDesempenho(), 'C50 é grupo I');
        $this->assertTrue(ClasseDeResistencia::C55->ehDeAltoDesempenho(), 'C55 é grupo II');
    }

    // ---------- Abatimento ----------

    #[Test]
    public function tolerancia_cresce_com_o_abatimento_especificado(): void
    {
        $this->assertSame(10, (new Abatimento(80))->toleranciaEmMm(), 'até 90 mm');
        $this->assertSame(20, (new Abatimento(100))->toleranciaEmMm(), '100 a 150 mm');
        $this->assertSame(20, (new Abatimento(150))->toleranciaEmMm(), 'limite da faixa');
        $this->assertSame(30, (new Abatimento(160))->toleranciaEmMm(), '160 em diante');
    }

    #[Test]
    public function aceita_medida_dentro_da_faixa_e_recusa_fora(): void
    {
        $abatimento = new Abatimento(100);

        $this->assertTrue($abatimento->aceita(80), 'limite inferior');
        $this->assertTrue($abatimento->aceita(100), 'exato');
        $this->assertTrue($abatimento->aceita(120), 'limite superior');
        $this->assertFalse($abatimento->aceita(79), 'um abaixo');
        $this->assertFalse($abatimento->aceita(121), 'um acima');
    }

    #[Test]
    public function descreve_a_faixa_aceita(): void
    {
        $this->assertSame('100 ± 20 mm', (new Abatimento(100))->faixa());
        $this->assertSame(80, (new Abatimento(100))->minimoAceito());
        $this->assertSame(120, (new Abatimento(100))->maximoAceito());
    }

    #[Test]
    public function recusa_abatimento_fora_da_faixa_usual(): void
    {
        $this->recusa( fn () => new Abatimento(5), 'fora da faixa');
        $this->recusa( fn () => new Abatimento(300), 'fora da faixa');
    }

    #[Test]
    public function compara_pelo_valor(): void
    {
        $this->assertTrue((new Abatimento(100))->ehIgualA(new Abatimento(100)), 'iguais');
        $this->assertFalse((new Abatimento(100))->ehIgualA(new Abatimento(120)), 'diferentes');
    }
}
