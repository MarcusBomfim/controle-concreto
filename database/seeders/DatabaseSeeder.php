<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Dominio\Concreto\Abatimento;
use App\Dominio\Concreto\ClasseDeResistencia;
use App\Dominio\Estrutura\ElementoEstrutural;
use App\Dominio\Estrutura\RepositorioDeElementos;
use App\Dominio\Estrutura\TipoDeElemento;
use App\Dominio\Obra\Obra;
use App\Dominio\Obra\RepositorioDeObras;
use Illuminate\Database\Seeder;

/**
 * Dados de demonstração, carregados pelo domínio.
 *
 * Nada de INSERT direto: a obra e os elementos passam pelas mesmas regras
 * que a tela aplica. Um seeder que grava por SQL consegue criar estado que
 * o sistema jamais produziria — e aí a tela mostra algo impossível.
 *
 * As concretagens, com corpos de prova e datas relativas a hoje, voltam na
 * próxima parte, junto com a agenda do laboratório.
 */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $obras = app(RepositorioDeObras::class);
        $elementos = app(RepositorioDeElementos::class);

        $codigo = 'OBR-2026-007';

        if ($obras->existe($codigo)) {
            $this->command?->info('A obra de demonstração já existe. Nada a fazer.');

            return;
        }

        $obras->salvar(new Obra(
            $codigo,
            'Edifício residencial Vista Serra',
            'Construtora Vale Verde Ltda.',
            'Marcus Bomfim',
            'CREA-SP 5069874521/D',
        ));

        $pecas = [
            new ElementoEstrutural('SAP-B1', TipoDeElemento::Fundacao, 'Sapatas do bloco 1', null, ClasseDeResistencia::C25, new Abatimento(80), 18.0),
            new ElementoEstrutural('BLC-B1', TipoDeElemento::Fundacao, 'Blocos de coroamento do bloco 1', null, ClasseDeResistencia::C25, new Abatimento(80), 10.0),
            new ElementoEstrutural('P-TER', TipoDeElemento::Pilar, 'Pilares do térreo', 'Térreo', ClasseDeResistencia::C35, new Abatimento(120), 30.0),
            new ElementoEstrutural('VIG-B1', TipoDeElemento::Viga, 'Vigas baldrame do bloco 1', null, ClasseDeResistencia::C25, new Abatimento(80), 12.0),
            new ElementoEstrutural('L3-P4', TipoDeElemento::Laje, 'Laje L3', '4º pavimento', ClasseDeResistencia::C30, new Abatimento(100), 42.0),
        ];

        foreach ($pecas as $peca) {
            $elementos->salvar($codigo, $peca);
        }

        $this->command?->info(sprintf('Carregado: 1 obra e %d elementos.', count($pecas)));
    }
}
