<?php

declare(strict_types=1);

namespace App\Providers;

use App\Aplicacao\AgendaDoLaboratorio;
use App\Dominio\Concretagem\RepositorioDeConcretagens;
use App\Dominio\Estrutura\RepositorioDeElementos;
use App\Dominio\Lote\RepositorioDeLotes;
use App\Dominio\NaoConformidade\RepositorioDeNaoConformidades;
use App\Dominio\Obra\RepositorioDeObras;
use App\Dominio\Usuario\RepositorioDeUsuarios;
use App\Persistencia\AgendaDoLaboratorioEmBanco;
use App\Persistencia\RepositorioDeConcretagensEmBanco;
use App\Persistencia\RepositorioDeElementosEmBanco;
use App\Persistencia\RepositorioDeLotesEmBanco;
use App\Persistencia\RepositorioDeNaoConformidadesEmBanco;
use App\Persistencia\RepositorioDeObrasEmBanco;
use App\Persistencia\RepositorioDeUsuariosEmBanco;
use App\Models\Conta;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Cada interface do domínio apontando para a implementação em banco.
     *
     * É o lugar onde a inversão de dependência acontece de fato. O domínio
     * e os casos de uso pedem `RepositorioDeLotes`; nenhum deles sabe que
     * existe SQLite do outro lado. Trocar por PostgreSQL, ou por um duplo
     * em memória num teste, mexe só nesta tabela.
     *
     * Na versão em PHP puro isto era a classe Montagem, que instanciava
     * tudo na mão e passava por construtor. O Service Container faz o mesmo
     * trabalho resolvendo as dependências sozinho: quando alguém pede
     * FormarLote, ele monta os dois repositórios antes.
     *
     * @var array<class-string, class-string>
     */
    public array $bindings = [
        RepositorioDeObras::class => RepositorioDeObrasEmBanco::class,
        RepositorioDeElementos::class => RepositorioDeElementosEmBanco::class,
        RepositorioDeConcretagens::class => RepositorioDeConcretagensEmBanco::class,
        RepositorioDeLotes::class => RepositorioDeLotesEmBanco::class,
        RepositorioDeNaoConformidades::class => RepositorioDeNaoConformidadesEmBanco::class,
        RepositorioDeUsuarios::class => RepositorioDeUsuariosEmBanco::class,
        AgendaDoLaboratorio::class => AgendaDoLaboratorioEmBanco::class,
    ];

    public function register(): void
    {
        //
    }

    /**
     * As duas permissões do sistema, como Gates.
     *
     * A regra em si continua no enum `Papel` do domínio: quem sabe que o
     * gestor não opera e que só o engenheiro decide é ele. O Gate é o
     * adaptador — é o que permite escrever `can:decidir` numa rota e
     * `@can('operar')` num template, em vez de espalhar
     * `if ($usuario->papel === ...)` pelos controladores.
     *
     * Na versão em PHP puro isto era a classe Guarda, que embrulhava cada
     * ação do controlador numa closure de permissão montada à mão.
     */
    public function boot(): void
    {
        Gate::define('operar', static fn (Conta $conta): bool => $conta->papel->podeOperar());
        Gate::define('decidir', static fn (Conta $conta): bool => $conta->papel->podeDecidir());
    }
}
