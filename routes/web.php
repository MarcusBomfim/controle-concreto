<?php

declare(strict_types=1);

use App\Http\Controllers\ElementoController;
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
 * A raiz ainda aponta para as obras. Ela passa a ser a agenda do
 * laboratório na próxima parte, quando a agenda tiver tela.
 */

Route::redirect('/', '/obras');

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
