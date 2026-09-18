<?php
$estimativa = $lote->estimativa();
$pendentes = $lote->exemplaresPendentes();
$perdidos = $lote->exemplaresPerdidos();
?>
<nav class="trilha">
    <a href="/obras">Obras</a> ›
    <a href="<?= e(caminho('obras', $obra->codigo)) ?>"><?= e($obra->codigo) ?></a> ›
    Lote nº <?= e($lote->numero()) ?>
</nav>

<header class="cabecalho">
    <div>
        <h1 class="titulo"><?= e($lote->identificacao()) ?></h1>
        <p class="cabecalho__nota"><?= e($obra->nome) ?></p>
        <p class="cabecalho__nota">
            <?= e($lote->condicao->rotulo()) ?> — <?= e($lote->condicao->descricao()) ?><br>
            <?= e($lote->amostragem->rotulo()) ?> · <?= e(metrosCubicos($lote->volumeEmM3())) ?>
            em <?= e(count($lote->concretagens())) ?> concretagem(ns)
            · limite de <?= e($lote->grupo->volumeMaximoDoLoteEmM3()) ?> m³ para <?= e(mb_strtolower($lote->grupo->rotulo())) ?>
        </p>
    </div>

    <div class="cabecalho__acoes">
        <span class="etiqueta etiqueta--<?= e($lote->situacao()->value) ?>">
            <?= e($lote->situacao()->rotulo()) ?>
        </span>

        <?php if ($lote->julgadoEm() !== null): ?>
            <span class="codigo">julgado em <?= e(dataHoraBr($lote->julgadoEm())) ?></span>
        <?php endif ?>

        <?php if ($naoConformidade !== null): ?>
            <a class="botao <?= $naoConformidade->estaAberta() ? 'botao--perigo' : '' ?>"
               href="<?= e(caminho('obras', $obra->codigo, 'lotes', $lote->numero(), 'nao-conformidade')) ?>">
                Não conformidade: <?= e($naoConformidade->estaAberta() ? 'aberta' : mb_strtolower($naoConformidade->desfecho()?->rotulo() ?? '')) ?>
            </a>
        <?php endif ?>

        <?php if ($lote->podeSerJulgado() && ($usuarioAtual?->papel->podeDecidir() ?? false)): ?>
            <form method="post" action="<?= e(caminho('obras', $obra->codigo, 'lotes', $lote->numero(), 'julgar')) ?>"
                  onsubmit="return confirm('Julgar o lote nº <?= e($lote->numero()) ?>? A conta da norma é feita com os exemplares atuais e o veredito fica registrado.');">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <button type="submit" class="botao botao--primario">Julgar lote</button>
            </form>
        <?php endif ?>
    </div>
</header>

<section class="painel">
    <article class="indicador">
        <span class="indicador__rotulo">fck de projeto</span>
        <strong class="indicador__valor"><?= e(mpa($lote->classe->fck())) ?></strong>
        <span class="indicador__nota"><?= e($lote->classe->rotulo()) ?></span>
    </article>
    <article class="indicador <?= $estimativa === null ? '' : ($lote->foiAceito() ? 'indicador--ok' : 'indicador--alerta') ?>">
        <span class="indicador__rotulo">fck estimado</span>
        <strong class="indicador__valor"><?= e(mpa($estimativa?->fckEstimadoEmMPa)) ?></strong>
        <span class="indicador__nota"><?= $estimativa === null ? 'ainda não julgado' : ($lote->foiAceito() ? 'atende ao projeto' : 'abaixo do projeto') ?></span>
    </article>
    <article class="indicador">
        <span class="indicador__rotulo">Exemplares de 28 dias</span>
        <strong class="indicador__valor"><?= e(count($lote->exemplaresComResultado())) ?> / <?= e(count($lote->exemplaresDeAceitacao())) ?></strong>
        <span class="indicador__nota">com resultado / moldados</span>
    </article>
    <article class="indicador <?= $pendentes !== [] ? 'indicador--destaque' : '' ?>">
        <span class="indicador__rotulo">Pendentes</span>
        <strong class="indicador__valor"><?= e(count($pendentes)) ?></strong>
        <span class="indicador__nota"><?= e(count($perdidos)) ?> perdido(s)</span>
    </article>
</section>

<?php if ($lote->situacao()->foiJulgado() === false && $pendentes !== []): ?>
    <p class="dica">
        O lote só é julgado com todos os exemplares de 28 dias resolvidos — rompidos ou
        descartados. Julgar com resultado pendente seria escolher quais cilindros contam.
        Aguardando: <?php foreach ($pendentes as $indice => $exemplar): ?><?= $indice > 0 ? ', ' : '' ?><?= e($exemplar->identificacao()) ?><?php endforeach ?>.
    </p>
<?php endif ?>

<h2 class="subtitulo">Concretagens do lote</h2>

<div class="rolagem">
    <table class="tabela">
        <thead>
            <tr>
                <th>Nº</th>
                <th>Data</th>
                <th>Peça</th>
                <th class="numerico">Volume aceito</th>
                <th class="numerico">Exemplares 28 d</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lote->concretagens() as $concretagem): ?>
                <tr>
                    <td>
                        <a href="<?= e(caminho('obras', $obra->codigo, 'concretagens', $concretagem->numero())) ?>">
                            nº <?= e($concretagem->numero()) ?>
                        </a>
                    </td>
                    <td><?= e(dataBr($concretagem->data)) ?></td>
                    <td><?= e($concretagem->elemento->identificacao()) ?></td>
                    <td class="numerico"><?= e(metrosCubicos($concretagem->volumeAceitoEmM3())) ?></td>
                    <td class="numerico"><?= e(count($concretagem->exemplaresDeAceitacao())) ?></td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<h2 class="subtitulo">Exemplares de 28 dias</h2>
<p class="dica">
    A resistência do exemplar é a maior entre os dois corpos de prova (NBR 5739): os dois
    vieram do mesmo concreto, e o que rompeu mais baixo teve defeito de cilindro, não de
    concreto. Exemplar com um cilindro só vale, mas fica anotado como incompleto.
</p>

<div class="rolagem">
    <table class="tabela">
        <thead>
            <tr>
                <th>Concretagem</th>
                <th>Exemplar</th>
                <th class="numerico">CP A</th>
                <th class="numerico">CP B</th>
                <th class="numerico">Exemplar</th>
                <th>Situação</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($lote->concretagens() as $concretagem): ?>
                <?php foreach ($concretagem->exemplaresDeAceitacao() as $exemplar): ?>
                    <tr>
                        <td>nº <?= e($concretagem->numero()) ?></td>
                        <td><?= e($exemplar->identificacao()) ?></td>
                        <td class="numerico"><?= e(mpa($exemplar->primeiro->resistenciaEmMPa())) ?></td>
                        <td class="numerico"><?= e(mpa($exemplar->segundo->resistenciaEmMPa())) ?></td>
                        <td class="numerico"><strong><?= e(mpa($exemplar->resistenciaEmMPa())) ?></strong></td>
                        <td>
                            <?php if ($exemplar->aguardaRompimento()): ?>
                                <span class="etiqueta etiqueta--em_andamento">Aguardando</span>
                            <?php elseif ($exemplar->estaCompleto()): ?>
                                <span class="etiqueta etiqueta--aceito">Completo</span>
                            <?php elseif ($exemplar->estaIncompleto()): ?>
                                <span class="etiqueta etiqueta--prazo">Incompleto</span>
                            <?php else: ?>
                                <span class="etiqueta etiqueta--nao_conforme">Perdido</span>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            <?php endforeach ?>
        </tbody>
    </table>
</div>

<?php if ($estimativa !== null): ?>
    <h2 class="subtitulo">Memória de cálculo</h2>

    <section class="cartao memoria">
        <p class="memoria__metodo"><?= e($estimativa->metodo) ?></p>

        <dl class="fatos">
            <div>
                <dt>Exemplares (n)</dt>
                <dd><?= e($estimativa->numeroDeExemplares) ?></dd>
            </div>
            <div>
                <dt>Valores ordenados</dt>
                <dd>
                    <?php foreach ($estimativa->valoresOrdenados as $indice => $valor): ?>
                        <span class="codigo">f<?= e($indice + 1) ?></span> <?= e(numeroBr($valor, 1)) ?><?= $indice < count($estimativa->valoresOrdenados) - 1 ? ' · ' : '' ?>
                    <?php endforeach ?>
                </dd>
            </div>
            <div>
                <dt>Menor / média / maior</dt>
                <dd><?= e(mpa($estimativa->menorExemplar())) ?> / <?= e(mpa($estimativa->media())) ?> / <?= e(mpa($estimativa->maiorExemplar())) ?></dd>
            </div>
            <div>
                <dt>Amostragem e condição</dt>
                <dd><?= e($estimativa->amostragem->rotulo()) ?> · <?= e($estimativa->condicao->rotulo()) ?> (linha <?= e($estimativa->condicao->linhaDaTabela()) ?> da tabela de ψ6)</dd>
            </div>
            <?php if ($estimativa->valorDaFormula !== null): ?>
                <div>
                    <dt>Fórmula da norma</dt>
                    <dd><?= e(mpa($estimativa->valorDaFormula)) ?></dd>
                </div>
                <div>
                    <dt>Piso ψ6 × f1</dt>
                    <dd>
                        <?= e(numeroBr($estimativa->psi6 ?? 0.0, 2)) ?> × <?= e(numeroBr($estimativa->menorExemplar(), 1)) ?>
                        = <?= e(mpa($estimativa->pisoDoPsi6)) ?>
                        <?php if ($estimativa->pisoPrevaleceu()): ?>
                            <span class="etiqueta etiqueta--prazo">o piso prevaleceu</span>
                        <?php endif ?>
                    </dd>
                </div>
            <?php endif ?>
            <div>
                <dt>fck estimado</dt>
                <dd><strong><?= e(mpa($estimativa->fckEstimadoEmMPa)) ?></strong> contra fck de projeto <?= e(mpa($lote->classe->fck())) ?></dd>
            </div>
            <div>
                <dt>Veredito</dt>
                <dd>
                    <span class="etiqueta etiqueta--<?= e($lote->situacao()->value) ?>"><?= e($lote->situacao()->rotulo()) ?></span>
                </dd>
            </div>
        </dl>

        <p class="dica">
            As fórmulas, os limiares e a tabela de ψ6 foram transcritos de memória da NBR 12655.
            Antes de uso real, confira cada linha com o texto vigente da norma.
        </p>
    </section>
<?php endif ?>
