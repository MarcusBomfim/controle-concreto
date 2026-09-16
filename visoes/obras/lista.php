<header class="cabecalho">
    <div>
        <h1 class="titulo">Obras</h1>
        <p class="cabecalho__nota">Cada obra tem suas peças, suas concretagens e seus lotes de aceitação.</p>
    </div>
    <div class="cabecalho__acoes">
        <a class="botao botao--primario" href="/obras/nova">Nova obra</a>
    </div>
</header>

<?php if ($linhas === []): ?>
    <p class="cartao cartao--vazio">
        Nenhuma obra cadastrada. Cadastre a primeira, ou rode
        <code>php ferramentas/semear.php</code> para carregar a obra de demonstração.
    </p>
<?php else: ?>
    <div class="grade-obras">
        <?php foreach ($linhas as $linha): ?>
            <?php $obra = $linha['obra']; ?>
            <article class="cartao cartao--obra">
                <span class="codigo"><?= e($obra->codigo) ?></span>
                <h2><a href="<?= e(caminho('obras', $obra->codigo)) ?>"><?= e($obra->nome) ?></a></h2>
                <p class="cartao__cliente"><?= e($obra->cliente) ?></p>
                <p class="cartao__responsavel">RT: <?= e($obra->responsavelTecnico) ?> · <?= e($obra->registroProfissional) ?></p>

                <dl class="fatos">
                    <div>
                        <dt>Elementos</dt>
                        <dd><?= e($linha['elementos']) ?></dd>
                    </div>
                    <div>
                        <dt>Concretagens</dt>
                        <dd>
                            <?= e($linha['concretagens']) ?>
                            <?php if ($linha['emAndamento'] > 0): ?>
                                <span class="etiqueta etiqueta--em_andamento"><?= e($linha['emAndamento']) ?> em andamento</span>
                            <?php endif ?>
                        </dd>
                    </div>
                    <div>
                        <dt>Lotes</dt>
                        <dd><?= e($linha['lotes']) ?></dd>
                    </div>
                </dl>
            </article>
        <?php endforeach ?>
    </div>
<?php endif ?>
