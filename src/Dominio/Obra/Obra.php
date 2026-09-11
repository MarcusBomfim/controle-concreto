<?php

declare(strict_types=1);

namespace ControleConcreto\Dominio\Obra;

use ControleConcreto\Dominio\Regras;

/**
 * A obra a que o controle tecnológico pertence.
 *
 * Aqui ela é enxuta: o que importa é identificar o contrato e quem responde
 * tecnicamente por ele — é o nome que vai no laudo.
 */
final class Obra
{
    public readonly string $codigo;
    public readonly string $nome;
    public readonly string $cliente;
    public readonly string $responsavelTecnico;
    public readonly string $registroProfissional;

    public function __construct(
        string $codigo,
        string $nome,
        string $cliente,
        string $responsavelTecnico,
        string $registroProfissional,
    ) {
        $this->codigo = strtoupper(Regras::textoObrigatorio($codigo, 'Código da obra', 20));
        $this->nome = Regras::textoObrigatorio($nome, 'Nome da obra', 160);
        $this->cliente = Regras::textoObrigatorio($cliente, 'Cliente', 160);
        $this->responsavelTecnico = Regras::textoObrigatorio($responsavelTecnico, 'Responsável técnico', 160);
        $this->registroProfissional = strtoupper(
            Regras::textoObrigatorio($registroProfissional, 'Registro profissional', 30)
        );
    }
}
