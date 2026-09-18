<?php

declare(strict_types=1);

use ControleConcreto\Dominio\ExcecaoDeDominio;
use ControleConcreto\Dominio\NaoConformidade\Desfecho;
use ControleConcreto\Dominio\NaoConformidade\NaoConformidade;
use ControleConcreto\Dominio\NaoConformidade\Providencia;
use ControleConcreto\Dominio\NaoConformidade\ResultadoDaProvidencia;
use ControleConcreto\Dominio\NaoConformidade\SituacaoDaNaoConformidade;
use ControleConcreto\Dominio\NaoConformidade\TipoDeProvidencia;

/** Lote C30 que deu 26,4 MPa: 3,6 MPa (12 %) abaixo. */
function naoConformidadeDeTeste(): NaoConformidade
{
    return NaoConformidade::abrir('OBR-2026-007', 1, momento('2026-04-10 10:00'), 30.0, 26.4);
}

function providencia(
    TipoDeProvidencia $tipo,
    ResultadoDaProvidencia $resultado,
    string $data = '2026-04-15',
    ?float $fck = null,
): Providencia {
    return new Providencia(
        $tipo,
        momento($data),
        'Descrição suficiente da providência realizada na peça.',
        $resultado,
        'Marcus Bomfim',
        $fck,
    );
}

grupo('Não conformidade: abertura');

teste('nasce aberta, com o tamanho do problema', function (): void {
    $nc = naoConformidadeDeTeste();

    igual(SituacaoDaNaoConformidade::Aberta, $nc->situacao());
    igualAproximado(3.6, $nc->deficitEmMPa());
    igualAproximado(12.0, $nc->deficitPercentual());
    igual([], $nc->providencias());
    igual([], $nc->desfechosPossiveis(), 'nada sustenta desfecho ainda');
});

teste('não se abre para lote que atendeu', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => NaoConformidade::abrir('OBR-2026-007', 1, momento('2026-04-10'), 30.0, 30.0),
        'está conforme',
    );
});

grupo('Não conformidade: providências');

teste('registra providência e ela sustenta o desfecho correspondente', function (): void {
    $nc = naoConformidadeDeTeste();

    $nc->registrarProvidencia(providencia(TipoDeProvidencia::RevisaoDeProjeto, ResultadoDaProvidencia::Favoravel));

    igual(1, count($nc->providencias()));
    igual([Desfecho::EstruturaAceita], $nc->desfechosPossiveis());
});

teste('providência desfavorável não sustenta nada', function (): void {
    $nc = naoConformidadeDeTeste();

    $nc->registrarProvidencia(providencia(TipoDeProvidencia::RevisaoDeProjeto, ResultadoDaProvidencia::Desfavoravel));

    igual([], $nc->desfechosPossiveis());
});

teste('recusa providência anterior à abertura', function (): void {
    $nc = naoConformidadeDeTeste();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $nc->registrarProvidencia(providencia(TipoDeProvidencia::RevisaoDeProjeto, ResultadoDaProvidencia::Favoravel, '2026-04-01')),
        'antes da não conformidade ser aberta',
    );
});

teste('descrição curta não é relato', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => new Providencia(TipoDeProvidencia::Reforco, momento('2026-04-15'), 'ok', ResultadoDaProvidencia::Favoravel, 'Marcus'),
        'ao menos 20 caracteres',
    );
});

teste('testemunho exige o fck obtido, e só ele pode informá-lo', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => providencia(TipoDeProvidencia::ExtracaoDeTestemunhos, ResultadoDaProvidencia::Favoravel),
        'Informe o fck obtido',
    );

    lanca(
        ExcecaoDeDominio::class,
        static fn () => providencia(TipoDeProvidencia::RevisaoDeProjeto, ResultadoDaProvidencia::Favoravel, fck: 28.0),
        'não mede resistência',
    );

    $testemunho = providencia(TipoDeProvidencia::ExtracaoDeTestemunhos, ResultadoDaProvidencia::Favoravel, fck: 28.3);
    igualAproximado(28.3, $testemunho->fckObtidoEmMPa ?? 0.0);
});

teste('ensaio não destrutivo só localiza: é sempre informativo', function (): void {
    lanca(
        ExcecaoDeDominio::class,
        static fn () => providencia(TipoDeProvidencia::EnsaioNaoDestrutivo, ResultadoDaProvidencia::Favoravel),
        'localiza',
    );

    $esclerometria = providencia(TipoDeProvidencia::EnsaioNaoDestrutivo, ResultadoDaProvidencia::Informativo);
    falso($esclerometria->foiFavoravel(), 'informativo não sustenta');
});

grupo('Não conformidade: encerramento');

teste('não encerra sem providência', function (): void {
    $nc = naoConformidadeDeTeste();

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $nc->encerrar(Desfecho::EstruturaAceita, 'Parecer', momento('2026-04-20')),
        'sem tratamento',
    );
});

teste('o desfecho precisa da providência favorável que o sustenta', function (): void {
    $nc = naoConformidadeDeTeste();
    $nc->registrarProvidencia(providencia(TipoDeProvidencia::RevisaoDeProjeto, ResultadoDaProvidencia::Desfavoravel));

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $nc->encerrar(Desfecho::EstruturaAceita, 'Aceito no grito', momento('2026-04-20')),
        'precisa de',
    );

    // Reforço executado sustenta "reforçada", mas não "aceita como está".
    $nc->registrarProvidencia(providencia(TipoDeProvidencia::Reforco, ResultadoDaProvidencia::Favoravel));

    igual([Desfecho::Reforcada], $nc->desfechosPossiveis());

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $nc->encerrar(Desfecho::EstruturaAceita, 'Parecer', momento('2026-04-20')),
    );
});

teste('encerra com testemunho favorável e vira registro definitivo', function (): void {
    $nc = naoConformidadeDeTeste();
    $nc->registrarProvidencia(providencia(TipoDeProvidencia::EnsaioNaoDestrutivo, ResultadoDaProvidencia::Informativo));
    $nc->registrarProvidencia(providencia(TipoDeProvidencia::ExtracaoDeTestemunhos, ResultadoDaProvidencia::Favoravel, '2026-04-18', 29.1));

    $nc->encerrar(Desfecho::EstruturaAceita, 'Os testemunhos atingiram 29,1 MPa; a revisão confirmou a segurança.', momento('2026-04-20 15:00'));

    igual(SituacaoDaNaoConformidade::Encerrada, $nc->situacao());
    igual(Desfecho::EstruturaAceita, $nc->desfecho());
    igual('2026-04-20 15:00', $nc->encerradaEm()?->format('Y-m-d H:i'));
    falso($nc->estaAberta(), 'encerrada');

    lanca(
        ExcecaoDeDominio::class,
        static fn () => $nc->registrarProvidencia(providencia(TipoDeProvidencia::Reforco, ResultadoDaProvidencia::Favoravel, '2026-04-21')),
        'já foi encerrada',
    );
});

teste('demolição encerra como demolida', function (): void {
    $nc = naoConformidadeDeTeste();
    $nc->registrarProvidencia(providencia(TipoDeProvidencia::ExtracaoDeTestemunhos, ResultadoDaProvidencia::Desfavoravel, fck: 22.0));
    $nc->registrarProvidencia(providencia(TipoDeProvidencia::Demolicao, ResultadoDaProvidencia::Favoravel, '2026-05-02'));

    $nc->encerrar(Desfecho::Demolida, 'Peça demolida e reconcretada; o concreto novo tem lote próprio.', momento('2026-05-03'));

    igual(Desfecho::Demolida, $nc->desfecho());
});
