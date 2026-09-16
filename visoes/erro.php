<section class="cartao cartao--vazio">
    <h1><?= e($titulo ?? 'Alguma coisa deu errado') ?></h1>
    <p><?= e($detalhe ?? '') ?></p>
    <p>
        <a class="botao" href="/">Agenda do laboratório</a>
        <a class="botao" href="/obras">Obras</a>
    </p>
</section>
