<?php
/*
 * Os formulários de romper e descartar um corpo de prova, usados na agenda
 * e na tela da concretagem. Quem inclui define:
 *
 *   $base       caminho do corpo de prova (…/corpos-de-prova/C1-28d-A)
 *   $voltar     para onde voltar depois do POST
 *   $podeRomper se a janela está aberta agora
 *   $motivo     sugestão de motivo para o descarte (ou '')
 *   $diametros, $agora, $token vêm do controlador
 */
?>
<?php if ($podeRomper): ?>
    <form class="formulario-linha" method="post" action="<?= e($base) ?>/romper">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="voltar" value="<?= e($voltar) ?>">
        <label>
            <span>kN</span>
            <input type="text" inputmode="decimal" name="carga_kn" size="6" placeholder="245,5" required>
        </label>
        <label>
            <span>Ø</span>
            <select name="diametro_mm">
                <?php foreach ($diametros as $diametro): ?>
                    <option value="<?= e($diametro->value) ?>"><?= e($diametro->rotulo()) ?></option>
                <?php endforeach ?>
            </select>
        </label>
        <label>
            <span>Rompido em</span>
            <input type="datetime-local" name="rompido_em" value="<?= e($agora->format('Y-m-d\TH:i')) ?>" required>
        </label>
        <button type="submit" class="botao botao--primario">Registrar</button>
    </form>
<?php endif ?>

<details class="descarte">
    <summary>Descartar</summary>
    <form class="formulario-linha" method="post" action="<?= e($base) ?>/descartar">
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <input type="hidden" name="voltar" value="<?= e($voltar) ?>">
        <label class="campo--largo">
            <span>Motivo</span>
            <input type="text" name="motivo" maxlength="300" value="<?= e($motivo) ?>"
                   placeholder="Quebrou na desforma, foi perdido, passou da janela…" required>
        </label>
        <button type="submit" class="botao botao--perigo">Confirmar descarte</button>
    </form>
</details>
