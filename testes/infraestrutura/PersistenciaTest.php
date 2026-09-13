<?php

declare(strict_types=1);

use ControleConcreto\Dominio\Concretagem\MotivoDeDevolucao;
use ControleConcreto\Dominio\Concretagem\SituacaoDaConcretagem;
use ControleConcreto\Dominio\Concreto\ClasseDeResistencia;
use ControleConcreto\Dominio\Ensaio\IdadeDeEnsaio;
use ControleConcreto\Dominio\Ensaio\SituacaoDoCorpoDeProva;
use ControleConcreto\Infraestrutura\Banco\Conexao;
use ControleConcreto\Infraestrutura\Banco\Migrador;

grupo('Migrations');

teste('cria as tabelas do domínio', function (): void {
    $conexao = bancoDeTeste();
    $consulta = $conexao->query("SELECT name FROM sqlite_master WHERE type = 'table' ORDER BY name");
    $tabelas = array_column($consulta === false ? [] : $consulta->fetchAll(), 'name');

    foreach (['obras', 'elementos', 'concretagens', 'cargas', 'exemplares', 'corpos_de_prova'] as $esperada) {
        verdadeiro(in_array($esperada, $tabelas, true), "tabela {$esperada}");
    }
});

teste('aplica cada migration uma única vez', function (): void {
    $conexao = Conexao::emMemoria();
    $migrador = Migrador::padrao($conexao);

    verdadeiro(count($migrador->aplicar()) >= 2, 'primeira execução');
    igual([], $migrador->aplicar(), 'segunda não repete');
});

teste('a chave estrangeira está ativa', function (): void {
    $conexao = bancoDeTeste();

    lanca(PDOException::class, static function () use ($conexao): void {
        $conexao->exec(
            "INSERT INTO elementos (obra_codigo, codigo, tipo, descricao, fck, abatimento_mm, volume_previsto_m3)
             VALUES ('OBRA-INEXISTENTE', 'X', 'laje', 'Órfão', 30, 100, 10)"
        );
    });
});

teste('o banco recusa fck fora das classes da norma', function (): void {
    $conexao = bancoDeTeste();
    $conexao->exec("INSERT INTO obras (codigo, nome, cliente, responsavel_tecnico, registro_profissional)
                    VALUES ('OBR-1', 'Obra', 'Cliente', 'Resp', 'CREA')");

    lanca(PDOException::class, static function () use ($conexao): void {
        $conexao->exec(
            "INSERT INTO elementos (obra_codigo, codigo, tipo, descricao, fck, abatimento_mm, volume_previsto_m3)
             VALUES ('OBR-1', 'X', 'laje', 'C27 não existe', 27, 100, 10)"
        );
    });
});

grupo('Repositório de obras e elementos');

teste('grava e lê a obra', function (): void {
    $app = ambienteComObra();

    $obra = $app['obras']->porCodigo('obr-2026-007');

    igual('Edifício Vista Serra', $obra?->nome);
    igual('CREA-SP 123456/D', $obra?->registroProfissional);
    verdadeiro($app['obras']->existe('OBR-2026-007'), 'existe');
});

teste('grava e lê o elemento com a especificação inteira', function (): void {
    $app = ambienteComObra();

    $laje = $app['elementos']->porCodigo('OBR-2026-007', 'l3-p4');

    igual('L3-P4', $laje?->codigo);
    igual(ClasseDeResistencia::C30, $laje?->classe);
    igual(100, $laje?->abatimento->especificadoEmMm);
    igual('4º pavimento', $laje?->pavimento);
    igualAproximado(42.0, $laje?->volumePrevistoEmM3 ?? 0.0);
});

teste('elemento sem pavimento volta com nulo', function (): void {
    $app = ambienteComObra();

    $sapata = new ControleConcreto\Dominio\Estrutura\ElementoEstrutural(
        'SAP',
        ControleConcreto\Dominio\Estrutura\TipoDeElemento::Fundacao,
        'Sapata',
        null,
        ClasseDeResistencia::C25,
        new ControleConcreto\Dominio\Concreto\Abatimento(80),
        5.0,
    );
    $app['elementos']->salvar('OBR-2026-007', $sapata);

    igual(null, $app['elementos']->porCodigo('OBR-2026-007', 'SAP')?->pavimento);
});

teste('lista os elementos da obra ordenados', function (): void {
    $app = ambienteComObra();

    $codigos = array_map(static fn ($e) => $e->codigo, $app['elementos']->daObra('OBR-2026-007'));

    igual(['L3-P4', 'P-T'], $codigos);
});

grupo('Repositório de concretagens');

teste('grava e lê a concretagem com cargas, exemplares e corpos de prova', function (): void {
    $app = ambienteComObra();

    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100, notaFiscal: 'NF-1');
    chegaCarga($concretagem, 140, notaFiscal: 'NF-2');
    moldaPadrao($concretagem, 1);

    $numero = $app['concretagens']->salvar($concretagem);
    $lida = $app['concretagens']->porNumero('OBR-2026-007', $numero);

    igual(1, $numero, 'primeira da obra');
    verdadeiro($lida !== null, 'encontrada');
    igual('L3-P4', $lida?->elemento->codigo, 'elemento veio junto');
    igual(2, count($lida?->cargas() ?? []));
    igual(MotivoDeDevolucao::AbatimentoForaDaFaixa, $lida?->carga(2)?->devolucao);
    igual(2, count($lida?->exemplares() ?? []));
    igual(4, count($lida?->corposDeProva() ?? []));
    igual('C1-28d-B', $lida?->exemplar(1, IdadeDeEnsaio::VinteEOitoDias)?->segundo->identificacao);
});

teste('preserva a janela de rompimento ao recarregar', function (): void {
    $app = ambienteComObra();

    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100);
    moldaPadrao($concretagem);
    $numero = $app['concretagens']->salvar($concretagem);

    $cp = $app['concretagens']->porNumero('OBR-2026-007', $numero)?->exemplar(1, IdadeDeEnsaio::VinteEOitoDias)?->primeiro;

    igual('2026-04-07 09:00', $cp?->rompimentoPrevisto()->format('Y-m-d H:i'));
    igual('2026-04-08 05:00', $cp?->fimDaJanela()->format('Y-m-d H:i'));
    igual(SituacaoDoCorpoDeProva::Curando, $cp?->situacao());
});

teste('numera em sequência dentro da obra', function (): void {
    $app = ambienteComObra();

    igual(1, $app['concretagens']->salvar(concretagemDeTeste()));
    igual(2, $app['concretagens']->salvar(concretagemDeTeste()));
    igual(3, $app['concretagens']->salvar(concretagemDeTeste()));
});

teste('salvar de novo atualiza a situação e acrescenta o que é novo', function (): void {
    $app = ambienteComObra();

    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100, notaFiscal: 'NF-1');
    $numero = $app['concretagens']->salvar($concretagem);
    $concretagem->definirNumero($numero);

    // Chega mais uma carga, molda, conclui e salva de novo.
    chegaCarga($concretagem, 100, notaFiscal: 'NF-2');
    moldaPadrao($concretagem, 2);
    $concretagem->concluir();
    $app['concretagens']->salvar($concretagem);

    $lida = $app['concretagens']->porNumero('OBR-2026-007', $numero);

    igual(SituacaoDaConcretagem::Concluida, $lida?->situacao());
    igual(2, count($lida?->cargas() ?? []), 'a segunda carga entrou');
    igual(2, count($lida?->exemplares() ?? []), 'os exemplares entraram');
    igual(1, (int) $app['conexao']->query('SELECT COUNT(*) FROM concretagens')->fetchColumn(), 'não duplicou');
});

teste('lista por elemento na ordem cronológica', function (): void {
    $app = ambienteComObra();

    $app['concretagens']->salvar(concretagemDeTeste());
    $app['concretagens']->salvar(new ControleConcreto\Dominio\Concretagem\Concretagem(
        'OBR-2026-007',
        pilaresDeTeste(),
        new DateTimeImmutable('2026-03-11'),
        'Usina',
        'Marcus',
        new DateTimeImmutable('2026-03-11'),
    ));

    igual(1, count($app['concretagens']->doElemento('OBR-2026-007', 'L3-P4')));
    igual(1, count($app['concretagens']->doElemento('OBR-2026-007', 'P-T')));
    igual(2, count($app['concretagens']->daObra('OBR-2026-007')));
});

teste('apagar a obra leva tudo em cascata', function (): void {
    $app = ambienteComObra();

    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100);
    moldaPadrao($concretagem);
    $app['concretagens']->salvar($concretagem);

    $app['conexao']->exec("DELETE FROM obras WHERE codigo = 'OBR-2026-007'");

    foreach (['elementos', 'concretagens', 'cargas', 'exemplares', 'corpos_de_prova'] as $tabela) {
        igual(0, (int) $app['conexao']->query("SELECT COUNT(*) FROM {$tabela}")->fetchColumn(), $tabela);
    }
});
