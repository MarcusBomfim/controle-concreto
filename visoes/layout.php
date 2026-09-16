<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e(($titulo ?? '') !== '' ? $titulo . ' — Controle de Concreto' : 'Controle de Concreto') ?></title>
    <link rel="stylesheet" href="/estilo.css">
</head>
<body>
    <header class="topo">
        <a class="topo__marca" href="/">
            <span class="topo__sigla">CC</span>
            <span>Controle de Concreto</span>
        </a>
        <nav class="topo__menu">
            <a href="/">Agenda do laboratório</a>
            <a href="/obras">Obras</a>
        </nav>
        <p class="topo__legenda">Recebimento, corpos de prova e aceitação pela NBR 12655</p>
    </header>

    <main class="pagina">
        <?php if (($mensagem ?? null) !== null): ?>
            <p class="aviso aviso--ok" role="status"><?= e($mensagem) ?></p>
        <?php endif ?>

        <?php if (($erro ?? null) !== null): ?>
            <p class="aviso aviso--erro" role="alert"><?= e($erro) ?></p>
        <?php endif ?>

        <?php
        /*
         * Sem escape aqui, e de propósito: $conteudo é o template interno já
         * renderizado, onde cada valor passou por e(). Escapar de novo
         * mostraria as tags como texto.
         */
        echo $conteudo;
        ?>
    </main>

    <footer class="rodape">
        <p>
            Controle tecnológico do concreto · valores de norma transcritos de memória —
            confira com o texto vigente antes de uso real · PHP <?= e(PHP_VERSION) ?>
        </p>
    </footer>
</body>
</html>
