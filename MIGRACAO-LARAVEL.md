# Migração para Laravel — concluída

Este projeto nasceu em **PHP puro**, sem framework: roteador, requisição e
resposta, templates, sessão com token anti-CSRF, repositórios com PDO,
migrations em SQL e um executor de testes caseiro — tudo escrito à mão. Essa
versão está no histórico do Git, e funcionava.

Este documento registra a migração para Laravel, feita em seis partes. O
objetivo era mostrar, peça por peça, **o que um framework substitui e o que
ele não toca**. O domínio entrou intacto: as 39 classes de `src/Dominio`
foram para `app/Dominio` trocando só o namespace, de `ControleConcreto\`
para `App\`. As regras da NBR 12655 — a janela de rompimento, o cálculo do
fck estimado, o tratamento da não conformidade — não mudaram uma linha.

## As seis partes

| Parte | O que entrou |
| --- | --- |
| 1 | Esqueleto, domínio portado e testes de domínio em PHPUnit |
| 2 | Persistência: migrations, models Eloquent e repositórios |
| 3 | Interface web: rotas, controllers, Blade e as telas de obra |
| 4 | Agenda do laboratório e a tela de concretagem |
| 5 | Lotes, memória de cálculo e não conformidade na tela |
| 6 | Acesso por papel, seeders e limpeza |

```bash
php artisan migrate:fresh --seed && php artisan serve
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

São 257 testes: 125 de domínio, 25 de persistência e 107 de HTTP.

Os de domínio estendem o `TestCase` do PHPUnit — não o do Laravel — porque não
precisam da aplicação: não sobem o container nem tocam em banco. Os de
persistência estendem o do Laravel e usam `RefreshDatabase`, que roda as
migrations de verdade num SQLite em memória e desfaz tudo ao fim de cada
teste.

## O que o framework substitui

| Em PHP puro, escrito à mão | Aqui |
| --- | --- |
| `Roteador` com 404 e 405 | `routes/web.php` |
| `Requisicao` / `Resposta` | `Illuminate\Http\Request` / `Response` |
| `Visao` + `e()` | Blade |
| `Montagem` instanciando tudo na mão | Service Container (`AppServiceProvider::$bindings`) |
| Repositórios com PDO e SQL à mão | Eloquent e Query Builder, atrás das mesmas interfaces |
| `Migrador` + arquivos `.sql` | `php artisan migrate` com o Schema Builder |
| `Sessao` com token anti-CSRF e `hash_equals` | Sessão e `@csrf` do Laravel |
| `Autenticador` com hash de isca | `Auth::attempt` com `Timebox` |
| `Guarda` embrulhando cada ação | Gates + `can:` na rota e `@can` no template |
| `?voltar=` validado contra redirecionamento aberto | `redirect()->intended()`, com o destino na sessão |
| `testes/Executor.php` | PHPUnit |

O que **não** muda: as entidades, as regras dentro delas, os casos de uso e as
interfaces de repositório. Nenhum arquivo de `app/Dominio` tem um `use` de
`Illuminate`.

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
formulários de romper e descartar aparecem em duas telas. Em PHP puro isso era
um `require` de um arquivo que lia `$base`, `$voltar`, `$podeRomper`,
`$motivo`, `$diametros` e `$agora` do escopo de quem incluía — uma assinatura
que só existia num comentário, e esquecer uma delas dava aviso do PHP em
produção. Virou componente anônimo: `@props` é a assinatura de verdade, com
valores padrão, e o que não for declarado não entra.

**`whereNumber` na rota.** Sem a restrição, `/concretagens/abc` casaria com
`/concretagens/{numero}`, o controlador receberia `(int) 'abc'` — zero — e a
tela diria "não existe concretagem 0" em vez de devolver 404. Em PHP puro essa
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

## Decisões do acesso

**A única classe da aplicação que o framework substituiu de verdade.** O
`Autenticador` fazia quatro coisas: buscar a conta, conferir a senha,
regravar o hash quando o custo padrão do PHP subia, e conferir contra um
hash de mentira quando o e-mail não existia — para que o tempo de resposta
não entregasse quais contas existem. O `Auth::attempt` faz as quatro. As
credenciais que não são senha viram cláusula `where`, então `ativo => true`
basta para conta desativada não ser encontrada; o `rehashPasswordIfRequired`
cobre o custo antigo; e o `Timebox` de 200 ms cobre o tempo de resposta
melhor do que o hash de isca cobria, porque envolve o caminho inteiro, não
só a conferência da senha. A classe foi apagada.

**A `Conta` é o único model que faz dois papéis**, e por um motivo: o
`SessionGuard` exige um `Authenticatable`, e não há como entregar a ele a
entidade `Usuario` sem arrastar o framework para dentro do domínio. A
divisão que sobra é limpa: o `Usuario` tem as regras de criação de conta —
e-mail válido, senha de 8 caracteres —, e a `Conta` é quem o guard conhece.
Duas sobrescritas bastam, porque a tabela é nossa: a chave é `email` e a
coluna da senha é `hash_senha`.

Detalhe que custou um erro fatal: `getAuthPasswordName()` teve que ser
sobrescrito como **método**, não como propriedade. A trait `Authenticatable`
já declara `$authPasswordName` com valor, e redeclarar propriedade de trait
com outro valor é erro de composição em PHP.

**A permissão continua no enum `Papel`.** Os Gates são adaptadores de duas
linhas: `Gate::define('operar', fn (Conta $c) => $c->papel->podeOperar())`.
O que muda em relação à `Guarda` é onde a exigência aparece — antes era uma
closure embrulhando cada ação do controlador, agora é `can:operar` na rota e
`@can('operar')` no template. Nenhum controlador tem `if ($papel === ...)`,
nem antes nem depois.

**O botão escondido não é a garantia.** A tela esconde o que o papel não
pode usar, mas o POST vem do navegador. A matriz de permissão tem teste
próprio, chamando cada rota direto com cada papel — é o `can:` que garante,
e é ele que está sendo exercitado.

**O destino pós-login saiu da URL.** Em PHP puro era `?voltar=`, que
precisava ser validado contra redirecionamento aberto a cada uso. O
middleware do Laravel guarda a URL pretendida na sessão e
`redirect()->intended()` a lê de lá: o valor nunca passa pelo navegador, e
não há o que validar.

**Uma coisa o framework não dá de graça:** desativar uma conta com sessão
aberta. O `Auth::attempt` impede o login, mas quem já estava dentro
continuaria até a sessão expirar. Foi o que sobrou de código nosso — o
middleware `ExigirContaAtiva`, que é o que a `Guarda` fazia ao reler o
usuário do banco a cada requisição.

## O que saiu do repositório

`src/`, `visoes/`, `testes/`, `banco/` e `ferramentas/` — a versão em PHP
puro inteira — foram removidos nesta parte. Estão no histórico do Git, que é
onde o passado de um projeto deve ficar.

Do esqueleto do Laravel também saíram coisas: o model `User`, a tabela
`users` e `password_reset_tokens`. A conta de acesso deste sistema é a
tabela `contas`, com e-mail como chave e papel de norma — nada a ver com a
`users` genérica —, e não há recuperação de senha por e-mail. A tabela
`sessions` ficou, porque é onde o login mora.

## Sobre os valores de norma

Continua valendo o aviso de sempre: tolerâncias, tabela de ψ6, fórmulas e
limites de lote foram transcritos de memória e estão marcados no código.
Antes de qualquer uso real, cada número precisa ser conferido contra a edição
vigente das normas.

## O que a migração ensinou

**O framework substituiu infraestrutura, não regra.** Roteador, sessão,
templates, container, migrations, testes — tudo isso era código nosso e
virou configuração. O domínio atravessou a migração com uma troca de
namespace. Foi o teste que a arquitetura em camadas existia para passar: se
as entidades soubessem que existe PDO, nada disso teria sido possível.

**A única exceção foi o `Autenticador`**, e vale entender por quê: ele não
era regra de negócio de concreto, era mecanismo de autenticação com nome de
caso de uso. Segurança de sessão é exatamente o tipo de coisa em que o
framework tem mais olhos revisando do que este projeto jamais teria — o
`Timebox` é melhor do que o hash de isca que eu tinha escrito.

**Onde o framework não chegou, sobrou pouco:** um middleware de 20 linhas
para derrubar sessão de conta desativada, um componente Blade, uma classe de
formatação. O resto das decisões da migração foi sobre *não* deixar o
framework entrar onde não devia — Eloquent como registro de tabela e não
como entidade, Query Builder no agregado que atravessa quatro tabelas, e o
Form Request parando onde a regra tem razão de norma atrás.

**Três bugs apareceram no caminho, todos por rodar o código de verdade:** o
`upsert()` com lista de colunas vazia, que o Laravel degrada para `insert` e
estoura na chave duplicada; dois testes meus com expectativa dependente do
relógio, que passariam de manhã e falhariam à noite; e uma providência
datada antes da abertura da não conformidade, recusada pela própria
entidade — o domínio reclamando do seeder, e com razão.
