<?php

declare(strict_types=1);

namespace ControleConcreto\Infraestrutura\Repositorio;

use ControleConcreto\Dominio\Obra\Obra;
use ControleConcreto\Dominio\Obra\RepositorioDeObras;
use PDO;

final class RepositorioDeObrasEmSqlite implements RepositorioDeObras
{
    private const COLUNAS = 'codigo, nome, cliente, responsavel_tecnico, registro_profissional';

    public function __construct(private readonly PDO $conexao)
    {
    }

    public function salvar(Obra $obra): void
    {
        $comando = $this->conexao->prepare(
            'INSERT INTO obras (' . self::COLUNAS . ')
             VALUES (:codigo, :nome, :cliente, :responsavel_tecnico, :registro_profissional)
             ON CONFLICT (codigo) DO UPDATE SET
                nome                  = excluded.nome,
                cliente               = excluded.cliente,
                responsavel_tecnico   = excluded.responsavel_tecnico,
                registro_profissional = excluded.registro_profissional'
        );

        $comando->execute([
            ':codigo' => $obra->codigo,
            ':nome' => $obra->nome,
            ':cliente' => $obra->cliente,
            ':responsavel_tecnico' => $obra->responsavelTecnico,
            ':registro_profissional' => $obra->registroProfissional,
        ]);
    }

    public function porCodigo(string $codigo): ?Obra
    {
        $consulta = $this->conexao->prepare(
            'SELECT ' . self::COLUNAS . ' FROM obras WHERE codigo = :codigo'
        );
        $consulta->execute([':codigo' => strtoupper(trim($codigo))]);

        $linha = $consulta->fetch();

        return $linha === false ? null : self::montar($linha);
    }

    public function todas(): array
    {
        $consulta = $this->conexao->query('SELECT ' . self::COLUNAS . ' FROM obras ORDER BY codigo');

        return array_map(self::montar(...), $consulta === false ? [] : $consulta->fetchAll());
    }

    public function existe(string $codigo): bool
    {
        $consulta = $this->conexao->prepare('SELECT 1 FROM obras WHERE codigo = :codigo');
        $consulta->execute([':codigo' => strtoupper(trim($codigo))]);

        return $consulta->fetchColumn() !== false;
    }

    /** @param array<string, mixed> $linha */
    private static function montar(array $linha): Obra
    {
        return new Obra(
            (string) $linha['codigo'],
            (string) $linha['nome'],
            (string) $linha['cliente'],
            (string) $linha['responsavel_tecnico'],
            (string) $linha['registro_profissional'],
        );
    }
}
