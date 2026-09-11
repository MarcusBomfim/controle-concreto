<?php

declare(strict_types=1);

/*
 * Autoloader PSR-4 mínimo, para o projeto rodar só com o PHP instalado.
 *
 * O composer.json na raiz declara o mesmo mapeamento. Com o Composer
 * instalado, `composer install` e `vendor/autoload.php` fazem o mesmo papel.
 */

spl_autoload_register(static function (string $classe): void {
    $prefixo = 'ControleConcreto\\';

    if (!str_starts_with($classe, $prefixo)) {
        return;
    }

    $relativo = substr($classe, strlen($prefixo));
    $caminho = __DIR__ . DIRECTORY_SEPARATOR
        . str_replace('\\', DIRECTORY_SEPARATOR, $relativo) . '.php';

    if (is_file($caminho)) {
        require $caminho;
    }
});
