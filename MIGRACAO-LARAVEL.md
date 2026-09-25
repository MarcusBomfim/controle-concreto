# Migração para Laravel — em andamento

Esta é a branch `laravel`. A branch `main` continua com o sistema completo em
PHP puro, funcionando, e **não é tocada** até esta migração terminar.

O objetivo é mostrar, peça por peça, **o que um framework substitui e o que
ele não toca**. O domínio entra intacto: as 39 classes de `src/Dominio` foram
para `app/Dominio` trocando só o namespace, de `ControleConcreto\` para
`App\`. As regras da NBR 12655 — a janela de rompimento, o cálculo do fck
estimado, o tratamento da não conformidade — não mudaram uma linha.

## Estado

| Parte | O que entra | Situação |
| --- | --- | --- |
| 1 | Esqueleto, domínio portado e testes de domínio em PHPUnit | **concluída** |
| 2 | Persistência: migrations, models Eloquent e repositórios | a fazer |
| 3 | Casos de uso com transação do Laravel | a fazer |
| 4 | Interface web: rotas, controllers, Blade e Form Requests | a fazer |
| 5 | Lotes, memória de cálculo e não conformidade | a fazer |
| 6 | Acesso por papel, seeders e documentação final | a fazer |

Enquanto a migração não termina, esta branch tem **menos** funcionalidade que
a `main`: só o domínio e os testes dele. É esperado — a `main` é que está no
ar.

## Como rodar

```bash
composer install
```

```bash
cp .env.example .env && php artisan key:generate
```

```bash
php artisan test
```

São 125 testes de domínio convertidos do executor caseiro para o PHPUnit.
Eles estendem o `TestCase` do PHPUnit — não o do Laravel — porque não
precisam da aplicação: não sobem o container, não tocam em banco. Rodam em
menos de meio segundo.

## O que o framework substitui

| Na `main`, escrito à mão | Aqui |
| --- | --- |
| `Roteador` com 404 e 405 | `routes/web.php` |
| `Requisicao` / `Resposta` | `Illuminate\Http\Request` / `Response` |
| `Visao` + `e()` | Blade |
| Repositórios com PDO e SQL à mão | Eloquent, ligado às interfaces pelo Service Container |
| `Migrador` + arquivos `.sql` | `php artisan migrate` com o Schema Builder |
| `Sessao` com token anti-CSRF e `hash_equals` | Sessão e `@csrf` do Laravel |
| `Autenticador` + `Guarda` | Auth, Gates e Policies |
| `testes/Executor.php` | PHPUnit |

O que **não** muda: as entidades, as regras dentro delas, os casos de uso e as
interfaces de repositório.

## As pastas antigas

`src/`, `visoes/`, `testes/`, `banco/` e `ferramentas/` continuam aqui como
referência durante a migração — o autoload do Composer só mapeia `App\`, então
elas não são carregadas por nada. Saem na última parte.

## Sobre os valores de norma

Continua valendo o aviso da `main`: tolerâncias, tabela de ψ6, fórmulas e
limites de lote foram transcritos de memória e estão marcados no código.
Antes de qualquer uso real, cada número precisa ser conferido contra a edição
vigente das normas.
