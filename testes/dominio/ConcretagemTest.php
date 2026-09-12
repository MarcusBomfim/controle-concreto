<?php

declare(strict_types=1);

use ControleConcreto\Dominio\Concretagem\Carga;
use ControleConcreto\Dominio\Concretagem\Concretagem;
use ControleConcreto\Dominio\Concretagem\MotivoDeDevolucao;
use ControleConcreto\Dominio\Concretagem\SituacaoDaConcretagem;
use ControleConcreto\Dominio\ExcecaoDeDominio;

function concretagemDeTeste(): Concretagem
{
    return new Concretagem(
        'obr-2026-007',
        lajeDeTeste(),
        new DateTimeImmutable('2026-03-10'),
        'Concreteira Litoral',
        'Marcus Bomfim',
        new DateTimeImmutable('2026-03-10'),
    );
}

function hora(string $horario): DateTimeImmutable
{
    return new DateTimeImmutable("2026-03-10 {$horario}");
}

/** Carga típica: saiu 8h, chegou 8h50, abatimento dentro da faixa de 100 ± 20. */
function chegaCarga(
    Concretagem $concretagem,
    int $abatimento = 100,
    string $saida = '08:00',
    string $chegada = '08:50',
    float $volume = 8.0,
    string $notaFiscal = 'NF-1001',
): Carga {
    return $concretagem->receberCarga($notaFiscal, 'ABC-1D23', $volume, hora($saida), hora($chegada), $abatimento);
}

grupo('Concretagem: abertura');

teste('nasce em andamento e sem cargas', function (): void {
    $concretagem = concretagemDeTeste();

    igual(SituacaoDaConcretagem::EmAndamento, $concretagem->situacao());
    igual([], $concretagem->cargas());
    igualAproximado(0.0, $concretagem->volumeAceitoEmM3());
});

teste('recusa data futura', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => new Concretagem(
            'OBR-1',
            lajeDeTeste(),
            new DateTimeImmutable('2026-03-11'),
            'Usina',
            'Responsável',
            new DateTimeImmutable('2026-03-10'),
        ),
        'a data ainda não chegou',
    );
});

grupo('Concretagem: recebimento de cargas');

teste('aceita carga com abatimento na faixa e transporte no tempo', function (): void {
    $concretagem = concretagemDeTeste();

    $carga = chegaCarga($concretagem, 100, '08:00', '08:50');

    verdadeiro($carga->foiAceita(), 'aceita');
    igual(null, $carga->devolucao);
    igual(50, $carga->tempoDeTransporteEmMinutos());
});

teste('devolve carga com abatimento fora da faixa', function (): void {
    // Laje especificada em 100 ± 20: 130 está fora.
    $concretagem = concretagemDeTeste();

    $carga = chegaCarga($concretagem, 130);

    verdadeiro($carga->foiDevolvida(), 'devolvida');
    igual(MotivoDeDevolucao::AbatimentoForaDaFaixa, $carga->devolucao);
});

teste('aceita abatimento exatamente no limite', function (): void {
    $concretagem = concretagemDeTeste();

    verdadeiro(chegaCarga($concretagem, 80, notaFiscal: 'NF-1')->foiAceita(), 'limite inferior');
    verdadeiro(chegaCarga($concretagem, 120, notaFiscal: 'NF-2')->foiAceita(), 'limite superior');
});

teste('devolve carga que passou do tempo de transporte', function (): void {
    // Saiu 8h, chegou 10h45: 165 minutos, acima dos 150 da NBR 7212.
    $concretagem = concretagemDeTeste();

    $carga = chegaCarga($concretagem, 100, '08:00', '10:45');

    verdadeiro($carga->foiDevolvida(), 'devolvida');
    igual(MotivoDeDevolucao::TempoDeTransporteExcedido, $carga->devolucao);
    igual(165, $carga->tempoDeTransporteEmMinutos());
});

teste('aceita carga exatamente nos 150 minutos', function (): void {
    $concretagem = concretagemDeTeste();

    verdadeiro(chegaCarga($concretagem, 100, '08:00', '10:30')->foiAceita(), '150 min é o limite, não além');
});

teste('o tempo é julgado antes do abatimento', function (): void {
    // Carga atrasada E com abatimento errado: o motivo registrado é o tempo,
    // porque é a primeira coisa que o canteiro confere ao caminhão chegar.
    $concretagem = concretagemDeTeste();

    $carga = chegaCarga($concretagem, 130, '08:00', '11:00');

    igual(MotivoDeDevolucao::TempoDeTransporteExcedido, $carga->devolucao);
});

teste('numera as cargas em sequência, contando as devolvidas', function (): void {
    $concretagem = concretagemDeTeste();

    chegaCarga($concretagem, 100, notaFiscal: 'NF-1');
    chegaCarga($concretagem, 140, notaFiscal: 'NF-2');
    $terceira = chegaCarga($concretagem, 100, notaFiscal: 'NF-3');

    igual(3, $terceira->numero, 'a devolvida ocupa o número 2');
    igual(3, count($concretagem->cargas()));
});

teste('só o volume aceito conta como concretado', function (): void {
    $concretagem = concretagemDeTeste();

    chegaCarga($concretagem, 100, volume: 8.0, notaFiscal: 'NF-1');
    chegaCarga($concretagem, 140, volume: 8.0, notaFiscal: 'NF-2');
    chegaCarga($concretagem, 95, volume: 7.5, notaFiscal: 'NF-3');

    igualAproximado(15.5, $concretagem->volumeAceitoEmM3());
    igualAproximado(8.0, $concretagem->volumeDevolvidoEmM3());
    igual(2, count($concretagem->cargasAceitas()));
    igual(1, count($concretagem->cargasDevolvidas()));
});

teste('recusa carga que chegou em outro dia', function (): void {
    $concretagem = concretagemDeTeste();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $concretagem->receberCarga(
            'NF-9',
            null,
            8.0,
            new DateTimeImmutable('2026-03-11 08:00'),
            new DateTimeImmutable('2026-03-11 08:50'),
            100,
        ),
        'concretagem do dia certo',
    );
});

teste('recusa chegada anterior à saída da usina', function (): void {
    $concretagem = concretagemDeTeste();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => chegaCarga($concretagem, 100, '09:00', '08:50'),
        'não pode chegar antes de sair',
    );
});

teste('normaliza a placa e guarda a observação', function (): void {
    $concretagem = concretagemDeTeste();

    $carga = $concretagem->receberCarga('NF-1', ' abc1d23 ', 8.0, hora('08:00'), hora('08:50'), 100, '  Bombeado  ');

    igual('ABC1D23', $carga->placa);
    igual('Bombeado', $carga->observacao);
});

grupo('Concretagem: encerramento');

teste('conclui com ao menos uma carga aceita', function (): void {
    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100);

    $concretagem->concluir();

    verdadeiro($concretagem->estaConcluida(), 'concluída');
});

teste('não conclui sem carga aceita', function (): void {
    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 140);

    lanca(ExcecaoDeDominio::class, static fn () => $concretagem->concluir(), 'Não há carga aceita');
});

teste('concluída não recebe mais carga', function (): void {
    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100);
    $concretagem->concluir();

    lanca(ExcecaoDeDominio::class, static fn () => chegaCarga($concretagem, 100, notaFiscal: 'NF-2'), 'não recebe mais cargas');
});

teste('cancela enquanto nada entrou na forma', function (): void {
    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 140);

    $concretagem->cancelar();

    igual(SituacaoDaConcretagem::Cancelada, $concretagem->situacao());
});

teste('não cancela depois que o concreto foi lançado', function (): void {
    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100);

    lanca(ExcecaoDeDominio::class, static fn () => $concretagem->cancelar(), 'não se cancela');
});

teste('não conclui duas vezes', function (): void {
    $concretagem = concretagemDeTeste();
    chegaCarga($concretagem, 100);
    $concretagem->concluir();

    lanca(ExcecaoDeDominio::class, static fn () => $concretagem->concluir(), 'não é possível concluir');
});
