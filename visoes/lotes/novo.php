<nav class="trilha">
    <a href="/obras">Obras</a> ›
    <a href="<?= e(caminho('obras', $obra->codigo)) ?>"><?= e($obra->codigo) ?></a> ›
    Novo lote
</nav>

<h1 class="titulo">Formar lote de aceitação</h1>
<p class="cabecalho__nota"><?= e($obra->nome) ?></p>

<?php if ($disponiveis === []): ?>
    <p class="cartao cartao--vazio">
        Nenhuma concretagem concluída fora de lote. Só concretagem concluída entra em lote,
        e cada uma entra em um lote só.
    </p>
<?php else: ?>
    <form class="formulario" method="post" action="<?= e(caminho('obras', $obra->codigo, 'lotes')) ?>">
        <input type="hidden" name="token" value="<?= e($token) ?>">

        <fieldset>
            <legend>Concretagens do lote</legend>
            <p class="dica">
                A NBR 12655 junta no mesmo lote concreto do mesmo fck e do mesmo grupo
                (compressão ou flexão), em até 3 dias de concretagem, até 50 m³ para pilares
                e paredes ou 100 m³ para o resto. A classe e o grupo do lote saem da primeira
                concretagem marcada; as outras precisam combinar.
            </p>

            <div class="rolagem">
                <table class="tabela">
                    <thead>
                        <tr>
                            <th></th>
                            <th>Nº</th>
                            <th>Data</th>
                            <th>Peça</th>
                            <th>Classe</th>
                            <th>Grupo</th>
                            <th class="numerico">Volume aceito</th>
                            <th class="numerico">Exemplares 28 d</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($disponiveis as $concretagem): ?>
                            <tr>
                                <td><input type="checkbox" name="concretagens[]" value="<?= e($concretagem->numero()) ?>"></td>
                                <td>nº <?= e($concretagem->numero()) ?></td>
                                <td><?= e(dataBr($concretagem->data)) ?></td>
                                <td><?= e($concretagem->elemento->identificacao()) ?></td>
                                <td><strong><?= e($concretagem->elemento->classe->rotulo()) ?></strong></td>
                                <td><?= e($concretagem->elemento->tipo->grupo()->rotulo()) ?></td>
                                <td class="numerico"><?= e(metrosCubicos($concretagem->volumeAceitoEmM3())) ?></td>
                                <td class="numerico"><?= e(count($concretagem->exemplaresDeAceitacao())) ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        </fieldset>

        <fieldset>
            <legend>Como o concreto foi preparado e amostrado</legend>

            <div class="campos">
                <label class="campo--largo">
                    <span>Condição de preparo</span>
                    <select name="condicao" required>
                        <?php foreach ($condicoes as $condicao): ?>
                            <option value="<?= e($condicao->value) ?>"><?= e($condicao->rotulo()) ?> — <?= e($condicao->descricao()) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>

                <label>
                    <span>Amostragem</span>
                    <select name="amostragem" required>
                        <?php foreach ($amostragens as $amostragem): ?>
                            <option value="<?= e($amostragem->value) ?>"><?= e($amostragem->rotulo()) ?></option>
                        <?php endforeach ?>
                    </select>
                </label>
            </div>

            <p class="dica">
                Amostragem total: cada caminhão virou exemplar, e o fck estimado é o menor deles.
                Parcial: só parte foi ensaiada; a norma extrapola com uma fórmula mais conservadora
                e exige ao menos 6 exemplares. A condição de preparo define o piso ψ6.
            </p>
        </fieldset>

        <div class="acoes-formulario">
            <button type="submit" class="botao botao--primario">Formar lote</button>
            <a class="botao" href="<?= e(caminho('obras', $obra->codigo)) ?>">Cancelar</a>
        </div>
    </form>
<?php endif ?>
