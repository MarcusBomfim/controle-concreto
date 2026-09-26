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
| 2 | Persistência: migrations, models Eloquent e repositórios | **concluída** |
| 3 | Interface web: rotas, controllers e Blade | a fazer |
| 4 | Agenda do laboratório e a tela de concretagem | a fazer |
| 5 | Lotes, memória de cálculo e não conformidade na tela | a fazer |
| 6 | Acesso por papel, seeders e documentação final | a fazer |

Enquanto a migração não termina, esta branch tem **menos** funcionalidade que
a `main`: o domínio, a persistência e os casos de uso funcionam, mas não há
nenhuma tela. É esperado — a `main` é que está no ar.

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

São 151 testes: 125 de domínio e 26 de persistência.

Os de domínio estendem o `TestCase` do PHPUnit — não o do Laravel — porque não
precisam da aplicação: não sobem o container nem tocam em banco. Os de
persistência estendem o do Laravel e usam `RefreshDatabase`, que roda as
migrations de verdade num SQLite em memória e desfaz tudo ao fim de cada
teste.

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

## Decisões da persistência

**Eloquent onde cabe, Query Builder onde não cabe.** `Obra`, `Elemento` e
`Conta` têm model do Eloquent: são uma linha de uma tabela, e `upsert()`
resolve a gravação. A `Concretagem` não tem: ela é um agregado que atravessa
quatro tabelas, e persistir isso com `hasMany` instanciaria milhares de
models só para descartá-los na tradução para o domínio. Ali o Query Builder
dá o mesmo SQL de antes, sem string concatenada na mão.

**Os models não são as entidades.** `App\Models\Obra` é o registro da tabela;
`App\Dominio\Obra\Obra` é quem tem as regras. Um model do Eloquent é Active
Record — sabe se gravar —, e misturar isso com as invariantes do domínio daria
uma classe que valida e persiste ao mesmo tempo. Separar custa uma tradução no
repositório e mantém o domínio sem saber que banco existe.

**O CHECK que o Schema Builder não tem.** Os `CHECK ... IN (...)` viraram
`$tabela->enum()`, que no SQLite gera exatamente o mesmo CHECK. Os que
dependem de mais de uma coluna — "lote julgado exige fck estimado e data",
"o fck obtido só existe em testemunho" — não cabem no Schema Builder, e o
SQLite não aceita adicionar CHECK depois que a tabela existe. Esses foram
para gatilhos, que o SQLite aceita criar separado. A garantia continua no
banco, que era o ponto.

**`insertOrIgnore`, não `upsert` com lista vazia.** Carga, exemplar e
providência são imutáveis: gravados uma vez, não mudam. A intenção é
"ignore se já existe", e o `upsert()` do Laravel com a lista de colunas
vazia **não** faz isso — ele vira um `insert` comum e estoura na chave
duplicada.

## As pastas antigas

`src/`, `visoes/`, `testes/`, `banco/` e `ferramentas/` continuam aqui como
referência durante a migração — o autoload do Composer só mapeia `App\`, então
elas não são carregadas por nada. Saem na última parte.

## Sobre os valores de norma

Continua valendo o aviso da `main`: tolerâncias, tabela de ψ6, fórmulas e
limites de lote foram transcritos de memória e estão marcados no código.
Antes de qualquer uso real, cada número precisa ser conferido contra a edição
vigente das normas.
