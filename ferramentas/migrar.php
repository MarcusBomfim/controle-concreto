<?php

declare(strict_types=1);

/*
 * Aplica as migrations pendentes.
 *
 *   php ferramentas/migrar.php
 */

require __DIR__ . '/../src/autoload.php';

use ControleConcreto\Infraestrutura\Banco\Conexao;
use ControleConcreto\Infraestrutura\Banco\Migrador;

$caminho = Conexao::caminhoPadrao();
$migrador = Migrador::padrao(Conexao::abrir($caminho));

if ($migrador->pendentes() === []) {
    echo 'Nada a aplicar: o banco já está atualizado.', PHP_EOL;
    exit(0);
}

echo 'Banco: ', $caminho, PHP_EOL;

try {
    foreach ($migrador->aplicar() as $nome) {
        echo '  aplicada  ', $nome, PHP_EOL;
    }
} catch (Throwable $erro) {
    fwrite(STDERR, 'Erro: ' . $erro->getMessage() . PHP_EOL);
    exit(1);
}

echo PHP_EOL, 'Pronto.', PHP_EOL;
