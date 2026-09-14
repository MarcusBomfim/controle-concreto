-- O que a prensa mediu. Colunas nulas enquanto o corpo de prova cura.
ALTER TABLE corpos_de_prova ADD COLUMN rompido_em TEXT;
ALTER TABLE corpos_de_prova ADD COLUMN carga_kn REAL;
ALTER TABLE corpos_de_prova ADD COLUMN diametro_mm INTEGER;

/*
 * A resistência é derivada de carga_kn e diametro_mm, mas fica gravada:
 * a conta do lote na Etapa 6 vai varrer resistências aos milhares, e
 * recalcular força/área linha a linha no SQL é o tipo de coisa que
 * funciona no teste e arrasta em produção.
 */
ALTER TABLE corpos_de_prova ADD COLUMN resistencia_mpa REAL;

ALTER TABLE corpos_de_prova ADD COLUMN motivo_descarte TEXT;


/*
 * ALTER TABLE não aceita CHECK entre colunas, então a coerência entre
 * situação e resultado vai em gatilho: não existe "rompido" sem número nem
 * "descartado" sem motivo. É a mesma garantia que o domínio dá, repetida
 * no banco para que nenhum caminho escape.
 */
CREATE TRIGGER IF NOT EXISTS cp_rompido_exige_resultado
BEFORE UPDATE OF situacao ON corpos_de_prova
WHEN NEW.situacao = 'rompido'
 AND (NEW.rompido_em IS NULL OR NEW.carga_kn IS NULL
      OR NEW.diametro_mm IS NULL OR NEW.resistencia_mpa IS NULL)
BEGIN
    SELECT RAISE(ABORT, 'corpo de prova rompido exige data, carga, diametro e resistencia');
END;

CREATE TRIGGER IF NOT EXISTS cp_descartado_exige_motivo
BEFORE UPDATE OF situacao ON corpos_de_prova
WHEN NEW.situacao = 'descartado' AND (NEW.motivo_descarte IS NULL OR NEW.motivo_descarte = '')
BEGIN
    SELECT RAISE(ABORT, 'corpo de prova descartado exige motivo');
END;

-- Rompido ou descartado é final: a situação não volta para curando.
CREATE TRIGGER IF NOT EXISTS cp_situacao_nao_regride
BEFORE UPDATE OF situacao ON corpos_de_prova
WHEN OLD.situacao <> 'curando' AND NEW.situacao <> OLD.situacao
BEGIN
    SELECT RAISE(ABORT, 'corpo de prova rompido ou descartado nao muda de situacao');
END;
