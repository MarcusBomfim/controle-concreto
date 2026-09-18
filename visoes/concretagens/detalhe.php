<?php
$elemento = $concretagem->elemento;
$emAndamento = $concretagem->situacao()->aceitaCarga();
$podeOperar = $usuarioAtual?->papel->podeOperar() ?? false;
$ehHoje = $concretagem->data->format('Y-m-d') === $agora->format('Y-m-d');
$aqui = caminho('obras', $obra->codigo, 'concretagens', $concretagem->numero());

$emCura = 0;
foreach ($concretagem->corposDeProva() as $cp) {
    if ($cp->situacao()->aguardaRompimento()) {
        $emCura++;
    }
}
?>
<nav class="trilha">
    <a href="/obras">Obras</a> ›
    <a href="<?= e(caminho('obras', $obra->codigo)) ?>"><?= e($obra->codigo) ?></a> ›
    Concretagem nº <?= e($concretagem->numero()) ?>
</nav>

<header class="cabecalho">
    <div>
        <h1 class="titulo">Concretagem nº <?= e($concretagem->numero()) ?> · <?= e(dataBr($concretagem->data)) ?></h1>
        <p class="cabecalho__nota">
            <strong><?= e($elemento->identificacao()) ?></strong> —
            <?= e($elemento->tipo->rotulo()) ?>, <?= e($elemento->classe->rotulo()) ?>,
            abatimento <?= e($elemento->abatimento->faixa()) ?>
        </p>
        <p class="cabecalho__nota">
            Fornecedor: <?= e($concretagem->fornecedor) ?> · Responsável: <?= e($concretagem->responsavel) ?>
        </p>
    </div>

    <div class="cabecalho__acoes">
        <span class="etiqueta etiqueta--<?= e($concretagem->situacao()->value) ?>">
            <?= e($concretagem->situacao()->rotulo()) ?>
        </span>

        <?php if ($loteNumero !== null): ?>
            <a class="botao" href="<?= e(caminho('obras', $obra->codigo, 'lotes', $loteNumero)) ?>">Lote <?= e($loteNumero) ?></a>
        <?php endif ?>

        <?php if ($podeOperar && $emAndamento && $concretagem->cargasAceitas() !== []): ?>
            <form method="post" action="<?= e($aqui) ?>/concluir"
                  onsubmit="return confirm('Concluir a concretagem nº <?= e($concretagem->numero()) ?>? Depois disso ela não recebe mais cargas nem moldagens.');">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <button type="submit" class="botao botao--primario">Concluir concretagem</button>
            </form>
        <?php endif ?>

        <?php if ($podeOperar && $emAndamento && $concretagem->cargasAceitas() === []): ?>
            <form method="post" action="<?= e($aqui) ?>/cancelar"
                  onsubmit="return confirm('Cancelar a concretagem nº <?= e($concretagem->numero()) ?>?');">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <button type="submit" class="botao botao--perigo">Cancelar concretagem</button>
            </form>
        <?php endif ?>
    </div>
</header>

<section class="painel">
    <article class="indicador">
        <span class="indicador__rotulo">Cargas</span>
        <strong class="indicador__valor"><?= e(count($concretagem->cargas())) ?></strong>
        <span class="indicador__nota"><?= e(count($concretagem->cargasDevolvidas())) ?> devolvida(s)</span>
    </article>
    <article class="indicador">
        <span class="indicador__rotulo">Volume aceito</span>
        <strong class="indicador__valor"><?= e(metrosCubicos($concretagem->volumeAceitoEmM3())) ?></strong>
        <span class="indicador__nota">de <?= e(metrosCubicos($elemento->volumePrevistoEmM3)) ?> previstos para a peça</span>
    </article>
    <article class="indicador <?= $concretagem->volumeDevolvidoEmM3() > 0 ? 'indicador--alerta' : '' ?>">
        <span class="indicador__rotulo">Volume devolvido</span>
        <strong class="indicador__valor"><?= e(metrosCubicos($concretagem->volumeDevolvidoEmM3())) ?></strong>
        <span class="indicador__nota">não entrou na forma</span>
    </article>
    <article class="indicador">
        <span class="indicador__rotulo">Corpos de prova</span>
        <strong class="indicador__valor"><?= e(count($concretagem->corposDeProva())) ?></strong>
        <span class="indicador__nota"><?= e($emCura) ?> em cura · <?= e(count($concretagem->exemplaresDeAceitacao())) ?> exemplar(es) de 28 dias</span>
    </article>
</section>

<h2 class="subtitulo">Cargas recebidas</h2>

<?php if ($concretagem->cargas() === []): ?>
    <p class="cartao cartao--vazio">Nenhum caminhão registrado ainda.</p>
<?php else: ?>
    <div class="rolagem">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Nº</th>
                    <th>Nota fiscal</th>
                    <th>Placa</th>
                    <th class="numerico">Volume</th>
                    <th>Saída da usina</th>
                    <th>Chegada</th>
                    <th class="numerico">Transporte</th>
                    <th class="numerico">Abatimento</th>
                    <th>Veredito</th>
                    <th>Observação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($concretagem->cargas() as $carga): ?>
                    <tr class="<?= $carga->foiDevolvida() ? 'linha--devolvida' : '' ?>">
                        <td><?= e($carga->numero) ?></td>
                        <td class="codigo"><?= e($carga->notaFiscal) ?></td>
                        <td class="codigo"><?= e($carga->placa ?? '—') ?></td>
                        <td class="numerico"><?= e(metrosCubicos($carga->volumeEmM3)) ?></td>
                        <td><?= e(horaBr($carga->saidaDaUsina)) ?></td>
                        <td><?= e(horaBr($carga->chegada)) ?></td>
                        <td class="numerico"><?= e($carga->tempoDeTransporteEmMinutos()) ?> min</td>
                        <td class="numerico"><?= e($carga->abatimentoMedidoEmMm) ?> mm</td>
                        <td>
                            <?php if ($carga->foiAceita()): ?>
                                <span class="etiqueta etiqueta--aceito">Aceita</span>
                            <?php else: ?>
                                <span class="etiqueta etiqueta--nao_conforme">Devolvida</span>
                                <span class="codigo"><?= e($carga->devolucao?->rotulo()) ?></span>
                            <?php endif ?>
                        </td>
                        <td class="miudos"><?= e($carga->observacao ?? '') ?></td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>

<?php if ($podeOperar && $emAndamento): ?>
    <form class="formulario" method="post" action="<?= e($aqui) ?>/cargas">
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <fieldset>
            <legend>Receber caminhão</legend>
            <p class="dica">
                Duas checagens, na ordem do canteiro: o relógio (até
                <?= e($concretagem::TEMPO_MAXIMO_DE_TRANSPORTE_EM_MINUTOS) ?> min da usina ao canteiro)
                e o cone (abatimento em <?= e($elemento->abatimento->faixa()) ?>). A primeira que falhar
                devolve o caminhão, e a devolução fica registrada com o motivo.
            </p>

            <div class="campos">
                <label>
                    <span>Nota fiscal</span>
                    <input type="text" name="nota_fiscal" maxlength="40" required>
                </label>
                <label>
                    <span>Placa</span>
                    <input type="text" name="placa" maxlength="10" placeholder="ABC-1D23">
                </label>
                <label>
                    <span>Volume (m³)</span>
                    <input type="text" inputmode="decimal" name="volume_m3" placeholder="8,0" required>
                </label>
                <label>
                    <span>Saída da usina</span>
                    <input type="time" name="saida" required>
                </label>
                <label>
                    <span>Chegada</span>
                    <input type="time" name="chegada" value="<?= e($ehHoje ? $agora->format('H:i') : '') ?>" required>
                </label>
                <label>
                    <span>Abatimento medido (mm)</span>
                    <input type="number" name="abatimento_mm" min="0" max="300" step="5"
                           value="<?= e($elemento->abatimento->especificadoEmMm) ?>" required>
                </label>
            </div>

            <div class="campos">
                <label class="campo--largo">
                    <span>Observação (opcional)</span>
                    <input type="text" name="observacao" maxlength="300" placeholder="Bomba, aditivo, temperatura…">
                </label>
            </div>
        </fieldset>

        <div class="acoes-formulario">
            <button type="submit" class="botao botao--primario">Registrar carga</button>
        </div>
    </form>

    <?php if ($concretagem->cargasAceitas() !== []): ?>
        <form class="formulario" method="post" action="<?= e($aqui) ?>/moldagens">
            <input type="hidden" name="token" value="<?= e($token) ?>">

            <fieldset>
                <legend>Moldar corpos de prova</legend>
                <p class="dica">
                    Cada idade marcada gera um exemplar: dois cilindros moldados no mesmo ato.
                    Só carga aceita gera corpo de prova, e o de 28 dias é obrigatório para concluir —
                    é ele que entra na aceitação do lote.
                </p>

                <div class="campos">
                    <label>
                        <span>Carga</span>
                        <select name="carga" required>
                            <?php foreach ($concretagem->cargasAceitas() as $carga): ?>
                                <option value="<?= e($carga->numero) ?>">
                                    Carga <?= e($carga->numero) ?> — NF <?= e($carga->notaFiscal) ?>, chegou <?= e(horaBr($carga->chegada)) ?>
                                </option>
                            <?php endforeach ?>
                        </select>
                    </label>
                    <label>
                        <span>Hora da moldagem</span>
                        <input type="time" name="hora" value="<?= e($ehHoje ? $agora->format('H:i') : '') ?>" required>
                    </label>
                </div>

                <div class="opcoes">
                    <?php foreach ($idades as $idade): ?>
                        <label class="opcao">
                            <input type="checkbox" name="idades[]" value="<?= e($idade->value) ?>"
                                <?= $idade->value === 7 || $idade->ehDeAceitacao() ? 'checked' : '' ?>>
                            <span><?= e($idade->rotulo()) ?><?= $idade->ehDeAceitacao() ? ' (aceitação)' : '' ?></span>
                        </label>
                    <?php endforeach ?>
                </div>
            </fieldset>

            <div class="acoes-formulario">
                <button type="submit" class="botao botao--primario">Moldar</button>
            </div>
        </form>
    <?php endif ?>
<?php endif ?>

<h2 class="subtitulo">Corpos de prova</h2>

<?php if ($concretagem->exemplares() === []): ?>
    <p class="cartao cartao--vazio">Nenhum corpo de prova moldado.</p>
<?php else: ?>
    <div class="rolagem">
        <table class="tabela">
            <thead>
                <tr>
                    <th>Exemplar</th>
                    <th>Corpo de prova</th>
                    <th>Rompimento previsto</th>
                    <th>Janela</th>
                    <th>Situação</th>
                    <th>Resultado</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($concretagem->exemplares() as $exemplar): ?>
                    <?php foreach ($exemplar->corposDeProva() as $indice => $cp): ?>
                        <?php
                        $curando = $cp->situacao()->aguardaRompimento();
                        $vencido = $cp->estaVencido($agora);
                        ?>
                        <tr class="<?= $vencido ? 'linha--vencida' : '' ?>">
                            <?php if ($indice === 0): ?>
                                <td rowspan="2">
                                    <strong><?= e($exemplar->identificacao()) ?></strong><br>
                                    <span class="codigo">moldado <?= e(dataHoraBr($exemplar->moldadoEm)) ?></span><br>
                                    <span class="codigo">exemplar: <?= e(mpa($exemplar->resistenciaEmMPa())) ?></span>
                                </td>
                            <?php endif ?>
                            <td><strong><?= e($cp->identificacao) ?></strong></td>
                            <td><?= e(dataHoraBr($cp->rompimentoPrevisto())) ?></td>
                            <td>
                                <?= e(dataHoraBr($cp->inicioDaJanela())) ?> a <?= e(dataHoraBr($cp->fimDaJanela())) ?>
                                <span class="codigo">(± <?= e(numeroBr($cp->idade->toleranciaEmHoras(), 1)) ?> h)</span>
                            </td>
                            <td>
                                <?php if ($vencido): ?>
                                    <span class="etiqueta etiqueta--nao_conforme">Vencido</span>
                                <?php elseif ($curando && $cp->dentroDaJanela($agora)): ?>
                                    <span class="etiqueta etiqueta--em_andamento">Na janela</span>
                                <?php else: ?>
                                    <span class="etiqueta etiqueta--<?= e($cp->situacao()->value) ?>"><?= e($cp->situacao()->rotulo()) ?></span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if ($cp->foiRompido()): ?>
                                    <strong><?= e(mpa($cp->resistenciaEmMPa())) ?></strong><br>
                                    <span class="codigo"><?= e($cp->resultado()?->descricao()) ?> · <?= e(dataHoraBr($cp->resultado()->rompidoEm)) ?></span>
                                <?php elseif ($cp->motivoDoDescarte() !== null): ?>
                                    <span class="miudos"><?= e($cp->motivoDoDescarte()) ?></span>
                                <?php else: ?>
                                    <span class="codigo">—</span>
                                <?php endif ?>
                            </td>
                            <td>
                                <?php if ($curando && $podeOperar): ?>
                                    <?php
                                    $base = $aqui . '/corpos-de-prova/' . rawurlencode($cp->identificacao);
                                    $voltar = $aqui;
                                    $podeRomper = $cp->dentroDaJanela($agora);
                                    $motivo = $vencido ? 'Passou da janela de rompimento sem ser rompido' : '';
                                    require __DIR__ . '/../partes/acoes-do-corpo-de-prova.php';
                                    ?>
                                <?php endif ?>
                            </td>
                        </tr>
                    <?php endforeach ?>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
