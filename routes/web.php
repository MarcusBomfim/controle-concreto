<?php

declare(strict_types=1);

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
 * A raiz é a agenda do laboratório: é a tela que se abre de manhã para
 * saber o que a prensa tem que romper hoje.
 */

Route::get('/', [AgendaController::class, 'index'])->name('agenda');

Route::get('/obras', [ObraController::class, 'index'])->name('obras.index');
Route::get('/obras/nova', [ObraController::class, 'create'])->name('obras.create');
Route::post('/obras', [ObraController::class, 'store'])->name('obras.store');

/*
 * O parâmetro não atravessa barra por padrão, então /obras/nova não é
 * confundido com /obras/{codigo} — mas a ordem importa: a rota literal
 * precisa vir antes da rota com parâmetro.
 */
Route::get('/obras/{obra}', [ObraController::class, 'show'])->name('obras.show');

Route::get('/obras/{obra}/elementos/novo', [ElementoController::class, 'create'])->name('elementos.create');
Route::post('/obras/{obra}/elementos', [ElementoController::class, 'store'])->name('elementos.store');

Route::get('/obras/{obra}/concretagens/nova', [ConcretagemController::class, 'create'])->name('concretagens.create');
Route::post('/obras/{obra}/concretagens', [ConcretagemController::class, 'store'])->name('concretagens.store');

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
        Route::post('/cargas', [ConcretagemController::class, 'receberCarga'])->name('concretagens.cargas');
        Route::post('/moldagens', [ConcretagemController::class, 'moldar'])->name('concretagens.moldagens');
        Route::post('/concluir', [ConcretagemController::class, 'concluir'])->name('concretagens.concluir');
        Route::post('/cancelar', [ConcretagemController::class, 'cancelar'])->name('concretagens.cancelar');

        Route::post('/corpos-de-prova/{identificacao}/romper', [CorpoDeProvaController::class, 'romper'])
            ->name('corpos-de-prova.romper');
        Route::post('/corpos-de-prova/{identificacao}/descartar', [CorpoDeProvaController::class, 'descartar'])
            ->name('corpos-de-prova.descartar');
    });

Route::get('/obras/{obra}/lotes/novo', [LoteController::class, 'create'])->name('lotes.create');
Route::post('/obras/{obra}/lotes', [LoteController::class, 'store'])->name('lotes.store');

/*
 * A não conformidade não tem rota própria: ela é sempre a do lote. Não
 * existe não conformidade sem lote reprovado, e o número dela é o do lote —
 * a URL diz isso.
 */
Route::prefix('/obras/{obra}/lotes/{numero}')
    ->whereNumber('numero')
    ->group(function (): void {
        Route::get('/', [LoteController::class, 'show'])->name('lotes.show');
        Route::post('/julgar', [LoteController::class, 'julgar'])->name('lotes.julgar');

        Route::get('/nao-conformidade', [NaoConformidadeController::class, 'show'])
            ->name('nao-conformidades.show');
        Route::post('/nao-conformidade/providencias', [NaoConformidadeController::class, 'registrarProvidencia'])
            ->name('nao-conformidades.providencias');
        Route::post('/nao-conformidade/encerrar', [NaoConformidadeController::class, 'encerrar'])
            ->name('nao-conformidades.encerrar');
    });
