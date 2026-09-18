<?php

declare(strict_types=1);

/*
 * Autoloader PSR-4 mínimo, para o projeto rodar só com o PHP instalado.
 *
 * O composer.json na raiz declara o mesmo mapeamento. Com o Composer
 * instalado, `composer install` e `vendor/autoload.php` fazem o mesmo papel.
 */

/*
 * Sem isto o PHP conta as horas em UTC, e a agenda do laboratório vive de
 * horas: às 21h de Santos o sistema acharia que já é o dia seguinte, e uma
 * janela de rompimento abriria três horas antes da hora. Fica aqui porque
 * todo ponto de entrada — servidor, ferramentas e testes — passa por este
 * arquivo.
 */
date_default_timezone_set('America/Sao_Paulo');

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

// Funções globais dos templates. Não são classes, então o autoload não as acha.
require __DIR__ . DIRECTORY_SEPARATOR . 'ajudantes.php';
