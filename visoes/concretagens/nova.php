<nav class="trilha">
    <a href="/obras">Obras</a> ›
    <a href="<?= e(caminho('obras', $obra->codigo)) ?>"><?= e($obra->codigo) ?></a> ›
    Nova concretagem
</nav>

<h1 class="titulo">Nova concretagem</h1>
<p class="cabecalho__nota"><?= e($obra->nome) ?></p>

<?php if ($elementos === []): ?>
    <p class="cartao cartao--vazio">
        Esta obra não tem elementos cadastrados. A concretagem é sempre de uma peça —
        <a href="<?= e(caminho('obras', $obra->codigo, 'elementos', 'novo')) ?>">cadastre a primeira</a>.
    </p>
<?php else: ?>
    <form class="formulario" method="post" action="<?= e(caminho('obras', $obra->codigo, 'concretagens')) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <fieldset>
            <legend>O que será concretado</legend>

            <div class="campos">
                <label class="campo--largo">
                    <span>Elemento</span>
                    <select name="elemento" required>
                        <?php foreach ($elementos as $elemento): ?>
                            <option value="<?= e($elemento->codigo) ?>">
                                <?= e($elemento->identificacao()) ?> —
                                <?= e($elemento->classe->rotulo()) ?>, abatimento <?= e($elemento->abatimento->faixa()) ?>
                            </option>
                        <?php endforeach ?>
                    </select>
                </label>

                <label>
                    <span>Data</span>
                    <input type="date" name="data" value="<?= e($hoje) ?>" max="<?= e($hoje) ?>" required>
                </label>
            </div>

            <p class="dica">
                A especificação da peça — classe e abatimento — é o que cada caminhão será
                julgado contra. Ela vem do cadastro do elemento, não se digita aqui.
            </p>
        </fieldset>

        <fieldset>
            <legend>Quem</legend>

            <div class="campos">
                <label class="campo--largo">
                    <span>Fornecedor do concreto</span>
                    <input type="text" name="fornecedor" maxlength="120" placeholder="Concreteira Litoral" required>
                </label>

                <label class="campo--largo">
                    <span>Responsável pela concretagem</span>
                    <input type="text" name="responsavel" maxlength="160" value="<?= e($obra->responsavelTecnico) ?>" required>
                </label>
            </div>
        </fieldset>

        <div class="acoes-formulario">
            <button type="submit" class="botao botao--primario">Abrir concretagem</button>
            <a class="botao" href="<?= e(caminho('obras', $obra->codigo)) ?>">Cancelar</a>
        </div>

        <p class="dica">
            Depois de aberta, a concretagem recebe os caminhões um a um, e os corpos de
            prova são moldados das cargas aceitas. Concluir exige exemplar de 28 dias.
        </p>
    </form>
<?php endif ?>
