<?php

declare(strict_types=1);

use App\Http\Controllers\AcessoController;
use App\Http\Controllers\AgendaController;
use App\Http\Controllers\ConcretagemController;
use App\Http\Controllers\CorpoDeProvaController;
use App\Http\Controllers\ElementoController;
use App\Http\Controllers\LoteController;
use App\Http\Controllers\NaoConformidadeController;
use App\Http\Controllers\ObraController;
use Illuminate\Support\Facades\Route;

/*
 * As rotas da aplicação.
 *
 * Na versão em PHP puro isto era a classe Roteador: compilar "{codigo}"
 * numa expressão regular, distinguir 404 de 405, decodificar o parâmetro
 * da URL. Aqui é uma lista — o roteador do Laravel faz o resto, incluindo
 * o 405 com o cabeçalho Allow e o nome de rota que os templates usam em
 * route('obras.show', ...) em vez de montar o caminho na mão.
 *
 * A permissão também mora aqui. Na `main` cada ação era embrulhada à mão
 * pela classe Guarda; aqui é `can:operar` e `can:decidir`, que apontam
 * para os Gates definidos no AppServiceProvider — e que, por sua vez,
 * apontam para o enum Papel do domínio. A regra continua num lugar só.
 *
 *   consultar  todo mundo logado: a agenda, as listas, as telas de detalhe
 *   operar     engenheiro e laboratorista: canteiro e prensa
 *   decidir    só o engenheiro: cadastros, lote e não conformidade
 */

Route::middleware('guest')->group(function (): void {
    Route::get('/entrar', [AcessoController::class, 'formulario'])->name('acesso.formulario');
    Route::post('/entrar', [AcessoController::class, 'entrar'])->name('acesso.entrar');
});

Route::middleware(['auth', 'conta.ativa'])->group(function (): void {
    Route::post('/sair', [AcessoController::class, 'sair'])->name('acesso.sair');

    /*
     * A raiz é a agenda do laboratório: é a tela que se abre de manhã para
     * saber o que a prensa tem que romper hoje.
     */
    Route::get('/', [AgendaController::class, 'index'])->name('agenda');

    Route::get('/obras', [ObraController::class, 'index'])->name('obras.index');

    /*
     * O parâmetro não atravessa barra por padrão, então /obras/nova não é
     * confundido com /obras/{codigo} — mas a ordem importa: a rota literal
     * precisa vir antes da rota com parâmetro.
     */
    Route::get('/obras/nova', [ObraController::class, 'create'])
        ->middleware('can:decidir')->name('obras.create');
    Route::post('/obras', [ObraController::class, 'store'])
        ->middleware('can:decidir')->name('obras.store');

    Route::get('/obras/{obra}', [ObraController::class, 'show'])->name('obras.show');

    Route::get('/obras/{obra}/elementos/novo', [ElementoController::class, 'create'])
        ->middleware('can:decidir')->name('elementos.create');
    Route::post('/obras/{obra}/elementos', [ElementoController::class, 'store'])
        ->middleware('can:decidir')->name('elementos.store');

    // ---------- concretagem: canteiro e prensa ----------

    Route::get('/obras/{obra}/concretagens/nova', [ConcretagemController::class, 'create'])
        ->middleware('can:operar')->name('concretagens.create');
    Route::post('/obras/{obra}/concretagens', [ConcretagemController::class, 'store'])
        ->middleware('can:operar')->name('concretagens.store');

    /*
     * `whereNumber` prende o parâmetro a dígitos. Sem isso, /concretagens/nova
     * também casaria aqui e o controlador receberia (int) 'nova' — zero — em vez
     * de 404. Na versão em PHP puro essa restrição era parte da expressão regular
     * escrita à mão para cada rota.
     */
    Route::prefix('/obras/{obra}/concretagens/{numero}')
        ->whereNumber('numero')
        ->group(function (): void {
            Route::get('/', [ConcretagemController::class, 'show'])->name('concretagens.show');

            Route::middleware('can:operar')->group(function (): void {
                Route::post('/cargas', [ConcretagemController::class, 'receberCarga'])
                    ->name('concretagens.cargas');
                Route::post('/moldagens', [ConcretagemController::class, 'moldar'])
                    ->name('concretagens.moldagens');
                Route::post('/concluir', [ConcretagemController::class, 'concluir'])
                    ->name('concretagens.concluir');
                Route::post('/cancelar', [ConcretagemController::class, 'cancelar'])
                    ->name('concretagens.cancelar');

                Route::post('/corpos-de-prova/{identificacao}/romper', [CorpoDeProvaController::class, 'romper'])
                    ->name('corpos-de-prova.romper');
                Route::post('/corpos-de-prova/{identificacao}/descartar', [CorpoDeProvaController::class, 'descartar'])
                    ->name('corpos-de-prova.descartar');
            });
        });

    // ---------- lote e não conformidade: decisão sobre a estrutura ----------

    Route::get('/obras/{obra}/lotes/novo', [LoteController::class, 'create'])
        ->middleware('can:decidir')->name('lotes.create');
    Route::post('/obras/{obra}/lotes', [LoteController::class, 'store'])
        ->middleware('can:decidir')->name('lotes.store');

    /*
     * A não conformidade não tem rota própria: ela é sempre a do lote. Não
     * existe não conformidade sem lote reprovado, e o número dela é o do lote —
     * a URL diz isso.
     */
    Route::prefix('/obras/{obra}/lotes/{numero}')
        ->whereNumber('numero')
        ->group(function (): void {
            Route::get('/', [LoteController::class, 'show'])->name('lotes.show');
            Route::get('/nao-conformidade', [NaoConformidadeController::class, 'show'])
                ->name('nao-conformidades.show');

            Route::middleware('can:decidir')->group(function (): void {
                Route::post('/julgar', [LoteController::class, 'julgar'])->name('lotes.julgar');

                Route::post('/nao-conformidade/providencias', [NaoConformidadeController::class, 'registrarProvidencia'])
                    ->name('nao-conformidades.providencias');
                Route::post('/nao-conformidade/encerrar', [NaoConformidadeController::class, 'encerrar'])
                    ->name('nao-conformidades.encerrar');
            });
        });
});
