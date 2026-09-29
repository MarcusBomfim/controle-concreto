<?php

declare(strict_types=1);

namespace App\Persistencia;

use App\Dominio\Usuario\RepositorioDeUsuarios;
use App\Dominio\Usuario\Usuario;
use App\Models\Conta as ContaRegistro;

/**
 * Contas de acesso.
 *
 * A tabela se chama `contas` para não colidir com a `users` que o Laravel
 * cria por padrão. A entidade continua se chamando Usuario: o nome no
 * domínio não precisa acompanhar o nome no banco.
 */
final class RepositorioDeUsuariosEmBanco implements RepositorioDeUsuarios
{
    public function salvar(Usuario $usuario): void
    {
        ContaRegistro::query()->upsert(
            [[
                'email' => $usuario->email,
                'nome' => $usuario->nome,
                'papel' => $usuario->papel->value,
                'hash_senha' => $usuario->hashDaSenha(),
                'ativo' => $usuario->estaAtivo(),
                'atualizado_em' => now(),
            ]],
            ['email'],
            ['nome', 'papel', 'hash_senha', 'ativo', 'atualizado_em'],
        );
    }

    public function porEmail(string $email): ?Usuario
    {
        $registro = ContaRegistro::query()->find(self::normalizar($email));

        return $registro === null ? null : self::montar($registro);
    }

    public function todos(): array
    {
        return ContaRegistro::query()
            ->orderBy('nome')
            ->get()
            ->map(self::montar(...))
            ->all();
    }

    public function existe(string $email): bool
    {
        return ContaRegistro::query()->whereKey(self::normalizar($email))->exists();
    }

    private static function montar(ContaRegistro $registro): Usuario
    {
        return new Usuario(
            (string) $registro->email,
            (string) $registro->nome,
            $registro->papel,
            (string) $registro->hash_senha,
            (bool) $registro->ativo,
        );
    }

    private static function normalizar(string $email): string
    {
        return mb_strtolower(trim($email));
    }
}
