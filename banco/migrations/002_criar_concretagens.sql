CREATE TABLE IF NOT EXISTS concretagens (
    obra_codigo     TEXT    NOT NULL,
    numero          INTEGER NOT NULL CHECK (numero > 0),
    elemento_codigo TEXT    NOT NULL,
    data            TEXT    NOT NULL CHECK (date(data) IS NOT NULL),
    fornecedor      TEXT    NOT NULL,
    responsavel     TEXT    NOT NULL,
    situacao        TEXT    NOT NULL
        CHECK (situacao IN ('em_andamento', 'concluida', 'cancelada')),
    criado_em       TEXT    NOT NULL DEFAULT (datetime('now')),

    PRIMARY KEY (obra_codigo, numero),

    /*
     * CASCADE, e não RESTRICT. A regra desejável seria "não apague elemento
     * que já foi concretado" — mas apagar a obra cascateia para elementos,
     * e um RESTRICT aqui bloquearia a exclusão da obra inteira. Proteger o
     * elemento concretado é política, e fica na aplicação, onde dá para
     * explicar o motivo a quem tentou.
     */
    FOREIGN KEY (obra_codigo, elemento_codigo)
        REFERENCES elementos (obra_codigo, codigo) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX IF NOT EXISTS idx_concretagens_elemento
    ON concretagens (obra_codigo, elemento_codigo, data);


-- Cada caminhão, aceito ou devolvido. A devolvida fica: é a prova na
-- discussão com a usina.
CREATE TABLE IF NOT EXISTS cargas (
    obra_codigo        TEXT    NOT NULL,
    concretagem_numero INTEGER NOT NULL,
    numero             INTEGER NOT NULL CHECK (numero > 0),
    nota_fiscal        TEXT    NOT NULL,
    placa              TEXT,
    volume_m3          REAL    NOT NULL CHECK (volume_m3 > 0),
    saida_da_usina     TEXT    NOT NULL,
    chegada            TEXT    NOT NULL CHECK (chegada >= saida_da_usina),
    abatimento_mm      INTEGER NOT NULL CHECK (abatimento_mm >= 0),
    devolucao          TEXT
        CHECK (devolucao IS NULL OR devolucao IN
               ('abatimento_fora_da_faixa', 'tempo_de_transporte_excedido')),
    observacao         TEXT,

    PRIMARY KEY (obra_codigo, concretagem_numero, numero),

    FOREIGN KEY (obra_codigo, concretagem_numero)
        REFERENCES concretagens (obra_codigo, numero) ON DELETE CASCADE
);


-- Um exemplar por carga e idade: a chave primária é a própria regra.
CREATE TABLE IF NOT EXISTS exemplares (
    obra_codigo        TEXT    NOT NULL,
    concretagem_numero INTEGER NOT NULL,
    carga_numero       INTEGER NOT NULL,
    idade_dias         INTEGER NOT NULL CHECK (idade_dias IN (1, 3, 7, 28, 63, 91)),
    moldado_em         TEXT    NOT NULL,

    PRIMARY KEY (obra_codigo, concretagem_numero, carga_numero, idade_dias),

    FOREIGN KEY (obra_codigo, concretagem_numero, carga_numero)
        REFERENCES cargas (obra_codigo, concretagem_numero, numero) ON DELETE CASCADE
);


/*
 * Os cilindros. A tabela mais consultada do sistema.
 *
 * A pergunta que o laboratório faz todo dia é "o que rompe hoje?" — e ela
 * precisa responder rápido mesmo com milhares de corpos de prova em cura.
 * Por isso a janela de rompimento está gravada em colunas, e não calculada
 * na consulta: rompimento_previsto, inicio_janela e fim_janela são derivados
 * de moldado_em e idade, mas ficam aqui para o índice existir.
 *
 * É desnormalização deliberada. O custo é manter os três coerentes com a
 * moldagem — e como o corpo de prova é imutável depois de moldado, o custo
 * é zero.
 */
CREATE TABLE IF NOT EXISTS corpos_de_prova (
    obra_codigo         TEXT    NOT NULL,
    concretagem_numero  INTEGER NOT NULL,
    carga_numero        INTEGER NOT NULL,
    idade_dias          INTEGER NOT NULL,
    letra               TEXT    NOT NULL CHECK (letra IN ('A', 'B')),

    identificacao       TEXT    NOT NULL,
    moldado_em          TEXT    NOT NULL,
    rompimento_previsto TEXT    NOT NULL,
    inicio_janela       TEXT    NOT NULL,
    fim_janela          TEXT    NOT NULL,

    situacao            TEXT    NOT NULL DEFAULT 'curando'
        CHECK (situacao IN ('curando', 'rompido', 'descartado')),

    PRIMARY KEY (obra_codigo, concretagem_numero, carga_numero, idade_dias, letra),

    FOREIGN KEY (obra_codigo, concretagem_numero, carga_numero, idade_dias)
        REFERENCES exemplares (obra_codigo, concretagem_numero, carga_numero, idade_dias)
        ON DELETE CASCADE,

    CHECK (inicio_janela <= rompimento_previsto AND rompimento_previsto <= fim_janela)
);

-- "O que rompe hoje": filtra por situação e varre um intervalo de datas.
CREATE INDEX IF NOT EXISTS idx_cp_agenda
    ON corpos_de_prova (situacao, rompimento_previsto);

-- "O que venceu": tudo que ainda cura e cuja janela já fechou.
CREATE INDEX IF NOT EXISTS idx_cp_vencidos
    ON corpos_de_prova (situacao, fim_janela);
