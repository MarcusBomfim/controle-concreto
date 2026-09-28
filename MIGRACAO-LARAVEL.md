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
| 3 | Interface web: rotas, controllers, Blade e as telas de obra | **concluída** |
| 4 | Agenda do laboratório e a tela de concretagem | **concluída** |
| 5 | Lotes, memória de cálculo e não conformidade na tela | **concluída** |
| 6 | Acesso por papel, seeders e documentação final | a fazer |

O sistema já faz o ciclo inteiro pela tela: cadastrar a obra e as peças,
abrir a concretagem, receber cada caminhão, moldar, lançar o resultado da
prensa, formar o lote, julgá-lo pela norma e tratar a não conformidade até o
desfecho. O que falta é o acesso por papel — hoje qualquer pessoa faz
qualquer coisa —, os arquivos velhos e a documentação final.

Para ver o que já existe:

```bash
php artisan migrate --seed && php artisan serve
```

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

São 228 testes: 125 de domínio, 25 de persistência e 78 de HTTP.

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

## Decisões da interface

**Duas camadas de validação, com propósitos diferentes.** O Form Request
confere formato — campo obrigatório, tamanho, valor dentro do enum — e
devolve uma lista de erros por campo para o formulário. A entidade continua
recusando o que é invariante: peça estrutural com C15, por exemplo, que
nenhuma regra de formulário saberia julgar. Não é duplicação: o Form Request
existe para a tela, e o domínio vale em qualquer caminho — seeder, comando
de console, teste.

**`prepareForValidation` para o formato brasileiro.** O formulário manda
"42,0" e o PHP quer ponto. A conversão fica no Form Request, antes das
regras, e não espalhada pelo controlador.

**`Rule::enum`.** A lista de tipos de elemento e de classes de resistência
não é repetida na validação: a regra aponta para o enum do domínio. Acrescentar
uma classe na NBR 8953 mexe num lugar só.

**Formatação virou classe, não função global.** `App\Support\Formato` tem
os `mpa()`, `metrosCubicos()` e afins que eram funções globais, e os
templates a importam com `@use`. O motivo não é estilo: função global não se
testa sem carregar o arquivo inteiro, e há um método ali —
`larguraCss()` — que existe justamente porque formatar com vírgula dentro de
um `style` do CSS já produziu um bug invisível no projeto irmão.

**O que não deu para testar.** "POST sem token é recusado" não tem teste: o
middleware de CSRF do Laravel se desliga sozinho quando detecta que está
rodando em teste. O que se prova é que o formulário carrega o campo; o resto
é responsabilidade do framework, que tem os próprios testes.

**Componente Blade no lugar do `require` com variáveis soltas.** Os
formulários de romper e descartar aparecem em duas telas. Na `main` isso era
um `require` de um arquivo que lia `$base`, `$voltar`, `$podeRomper`,
`$motivo`, `$diametros` e `$agora` do escopo de quem incluía — uma assinatura
que só existia num comentário, e esquecer uma delas dava aviso do PHP em
produção. Virou componente anônimo: `@props` é a assinatura de verdade, com
valores padrão, e o que não for declarado não entra.

**`whereNumber` na rota.** Sem a restrição, `/concretagens/abc` casaria com
`/concretagens/{numero}`, o controlador receberia `(int) 'abc'` — zero — e a
tela diria "não existe concretagem 0" em vez de devolver 404. Na `main` essa
restrição fazia parte da expressão regular escrita à mão para cada rota.

**O campo `voltar` é entrada do usuário.** O mesmo POST volta para a agenda
ou para a tela da concretagem, e quem diz de onde veio é um campo escondido
do formulário — que vem do navegador, e portanto pode vir de qualquer um.
Um destino absoluto ali seria redirecionamento aberto: a pessoa clica em
"Confirmar descarte" no nosso domínio e o navegador a larga em outro site.
Só se aceita caminho local, e `//outro.site` também é absoluto — por isso as
duas checagens. Tem teste.

**Onde a validação de formulário para, de propósito.** O Form Request da
providência confere formato: tipo dentro do enum, data não no futuro, texto
dentro do limite. Ele **não** confere o tamanho mínimo da descrição, nem o
fck obrigatório no testemunho, nem "ensaio não destrutivo só pode ser
informativo". São regras com razão de norma atrás, e a razão está escrita na
`Providencia`, junto da mensagem. Repeti-las na camada de formulário as
duplicaria sem o porquê — e "o campo descrição deve ter no mínimo 20
caracteres" é pior do que "descreva a providência com ao menos 20
caracteres: o que foi feito, onde e o que se concluiu".

**A tela oferece; a entidade decide.** O formulário de encerramento só lista
os desfechos que as providências já sustentam. Mas um POST não vem da tela,
vem do navegador: quem quiser pode mandar `desfecho=estrutura_aceita` sem
nenhum testemunho favorável. Quem recusa é a `NaoConformidade`, e tem teste
para isso. A tela é conveniência; a garantia é do domínio.

## Decisões dos dados de demonstração

**O seeder usa datas relativas a hoje.** A agenda do laboratório só faz
sentido contra o relógio: ela pergunta o que está na janela de rompimento
*agora*. Um seeder com datas fixas mostraria a tela cheia no dia em que foi
escrito e vazia no dia seguinte. As quatro concretagens são posicionadas para
que cada estado apareça: exemplar vencido, exemplar na janela neste instante,
rompimento previsto para daqui a três dias, e uma concretagem aberta
esperando caminhão.

**Também o seeder passa pelo domínio.** Cada carga é julgada pelo cone e pelo
relógio, e uma delas é devolvida de verdade — com 150 mm de abatimento numa
peça de 100 ± 20. Os dois lotes são julgados pela mesma conta da norma que a
tela chama, e a não conformidade do reprovado nasce sozinha, no julgamento.
Gravar por SQL seria mais rápido e criaria estado que o sistema jamais
produziria: carga devolvida com corpo de prova, concretagem concluída sem
exemplar de 28 dias, lote reprovado sem tratamento aberto. A tela mostraria
algo impossível.

**Uma regra do domínio apareceu ao escrever o seeder.** A primeira versão
datava a providência de cinco dias atrás, e a entidade recusou: a não
conformidade tinha sido aberta naquele instante, e providência anterior à
abertura não existe. O seeder é o primeiro cliente do domínio a reclamar de
uma sequência impossível — e reclamou certo.

**O teste da agenda usa a idade de 91 dias de propósito.** É a única parte da
aplicação que depende da hora em que o teste roda. A tolerância de 91 dias é
de 48 h, então um corpo de prova moldado há exatamente 91 dias ao meio-dia
está dentro da janela a qualquer hora. Com a idade de 7 dias — 6 h de
tolerância — o teste passaria de manhã e falharia à noite, que é o pior tipo
de teste que existe.

## As pastas antigas

`src/`, `visoes/`, `testes/`, `banco/` e `ferramentas/` continuam aqui como
referência durante a migração — o autoload do Composer só mapeia `App\`, então
elas não são carregadas por nada. Saem na última parte.

## Sobre os valores de norma

Continua valendo o aviso da `main`: tolerâncias, tabela de ψ6, fórmulas e
limites de lote foram transcritos de memória e estão marcados no código.
Antes de qualquer uso real, cada número precisa ser conferido contra a edição
vigente das normas.
