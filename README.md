# Controle Tecnológico de Concreto

Sistema para registrar concretagens, moldar e acompanhar corpos de prova, lançar os resultados dos ensaios de compressão e julgar a aceitação de cada lote conforme a **NBR 12655**.

## O problema

Toda concretagem estrutural exige, por norma, que se moldem corpos de prova, que eles sejam curados e rompidos aos 7 e aos 28 dias, e que o resultado prove que o concreto entregue atingiu o **fck** especificado no projeto. Se não atingiu, a peça pode precisar de reforço — ou de demolição.

Na prática, isso vive em planilha do laboratório. O resultado dos 28 dias chega semanas depois da concretagem, a laje de cima já foi executada, e ninguém cruza o número com a peça exata que ele representa. Quando uma não conformidade aparece, a pergunta "qual caminhão, qual laje, qual dia" não tem resposta rápida.

O sistema resolve isso amarrando cada corpo de prova ao caminhão que o originou, cada caminhão à peça concretada, e cada resultado ao lote de aceitação — com a conta da norma feita pelo sistema, não na mão.

## Requisitos

**PHP 8.3 ou superior** e **Composer**. O banco é SQLite, sem servidor.

```bash
php -v
```

A extensão `pdo_sqlite` já vem nas distribuições oficiais.

## Como rodar

```bash
composer install
```

```bash
cp .env.example .env && php artisan key:generate
```

```bash
php artisan migrate --seed
```

O banco é criado em `database/database.sqlite`, fora do controle de versão. A carga de demonstração é feita pelo domínio, com datas relativas a hoje — para a agenda mostrar corpos de prova vencidos, na janela e futuros. Um SQL com datas fixas envelheceria em uma semana.

```bash
php artisan serve
```

Abra <http://localhost:8000>. A primeira tela é o login; depois dele, a agenda do laboratório.

Para recarregar tudo do zero: `php artisan migrate:fresh --seed`.

### Contas de demonstração

Criadas por `php artisan migrate --seed` e destinadas apenas a desenvolvimento:

| E-mail | Senha | Papel | Pode |
| --- | --- | --- | --- |
| engenheiro@concreto.dev | `Engenheiro@123` | Engenheiro | tudo: cadastros, concretagens, laboratório, lotes e não conformidades |
| laboratorio@concreto.dev | `Laboratorio@123` | Laboratorista | concretagens, cargas, moldagens, rompimentos e descartes |
| gestor@concreto.dev | `Gestor@123` | Gestor | somente leitura |

No banco só vai o hash: a senha em texto morre dentro de `Usuario::criar`, que chama `password_hash()` com sal aleatório. Conta que já existe não é sobrescrita.

A carga de demonstração tem datas relativas a hoje: corpos de prova vencidos, corpos de prova na janela de rompimento neste momento, outros para os próximos dias, uma concretagem em andamento e um lote reprovado com a não conformidade aberta.

## Como rodar os testes

```bash
php artisan test
```

São 257 testes: 125 de domínio, 25 de persistência e 107 de HTTP.

Os de domínio estendem o `TestCase` do PHPUnit — não o do Laravel — porque não precisam da aplicação: não sobem o container nem tocam em banco. Os de persistência e os de HTTP usam `RefreshDatabase`, que aplica as migrations reais num SQLite em memória e desfaz tudo ao fim de cada teste.

Os de HTTP sobem a aplicação inteira e atravessam roteador, middleware de permissão, Form Request, controlador e Blade: o dia de concretagem pelos formulários, o resultado lançado da agenda, o lote julgado com a conta aberta, a não conformidade tratada e encerrada, e o 403 do gestor.

## Estrutura

```text
controle-concreto/
├── app/
│   ├── Dominio/            # o coração: sem uma linha de framework
│   │   ├── Regras.php
│   │   ├── ExcecaoDeDominio.php
│   │   ├── Obra/           # Obra e a interface do repositório
│   │   ├── Concreto/       # ClasseDeResistencia, Abatimento
│   │   ├── Estrutura/      # ElementoEstrutural, TipoDeElemento, GrupoDeSolicitacao
│   │   ├── Concretagem/    # Concretagem, Carga, MotivoDeDevolucao
│   │   ├── Ensaio/         # CorpoDeProva, Exemplar, IdadeDeEnsaio, ResultadoDeEnsaio
│   │   ├── Lote/           # Lote, CalculadoraDeFckEstimado, Psi6, EstimativaDeFck
│   │   ├── NaoConformidade/ # NaoConformidade, Providencia, Desfecho
│   │   └── Usuario/        # Usuario, Papel
│   ├── Aplicacao/          # casos de uso e a agenda do laboratório
│   ├── Persistencia/       # os repositórios, em Eloquent e Query Builder
│   ├── Models/             # Obra, Elemento, Conta — registros de tabela, não entidades
│   ├── Http/
│   │   ├── Controllers/    # um por tela: acesso, agenda, obras, concretagens, lotes, NCs
│   │   ├── Requests/       # validação de formulário
│   │   └── Middleware/     # ExigirContaAtiva
│   ├── Support/Formato.php # formatação para as telas
│   └── Providers/          # o Service Container: interface → implementação, e os Gates
├── database/
│   ├── migrations/         # Schema Builder, mais os gatilhos que ele não cobre
│   └── seeders/            # carga de demonstração, feita pelo domínio
├── resources/views/        # Blade: layout, agenda, acesso/, obras/, concretagens/,
│                           # lotes/, nao-conformidades/, components/
├── routes/web.php          # as rotas e as permissões (can:operar, can:decidir)
├── public/
│   ├── index.php
│   └── estilo.css
├── tests/
│   ├── Apoio/              # traits compartilhados: objetos de exemplo, login, regras
│   ├── Unit/Dominio/       # 125 testes, sem container e sem banco
│   └── Feature/            # persistência e HTTP, com SQLite em memória
├── .github/workflows/ci.yml  # testes em PHP 8.3 e 8.4
├── MIGRACAO-LARAVEL.md     # o que o framework substituiu, peça por peça
├── composer.json
└── README.md
```

## Vocabulário

Quem não é da construção tropeça nos termos, então aqui vão os que o código usa:

| Termo | O que é |
| --- | --- |
| **fck** | resistência característica do concreto à compressão aos 28 dias, em MPa. É o número que o projeto especifica |
| **Classe** | C25, C30, C40… o fck expresso como classe da NBR 8953 |
| **Abatimento** | o "slump": quanto o tronco de cone de concreto fresco abate ao ser desmoldado, em mm. Mede a consistência |
| **Elemento estrutural** | a peça concretada: laje, pilar, viga, sapata |
| **Concretagem** | o evento de concretar uma peça num dia; recebe os caminhões |
| **Carga** | um caminhão-betoneira, com sua nota fiscal, volume e abatimento medido |
| **Corpo de prova** | cilindro de concreto moldado de uma carga, curado e rompido na prensa numa idade fixa |
| **Exemplar** | dois corpos de prova da mesma carga, para a mesma idade; vale o maior dos dois |
| **Idade** | quando o corpo de prova é rompido: 7 dias antecipa problema, 28 dias é o que vale |
| **Lote** | o conjunto de concreto julgado de uma vez, limitado por volume e por tipo de peça |

## O que o domínio já garante

| Regra | Onde | Norma |
| --- | --- | --- |
| Só existem as classes de resistência da norma — não há C27 nem C65 | `ClasseDeResistencia` | NBR 8953 |
| Elemento estrutural exige no mínimo C20; C15 só em piso e obra provisória | `ElementoEstrutural` | NBR 6118 |
| Tolerância do abatimento cresce com o valor: ±10, ±20 ou ±30 mm | `Abatimento::toleranciaEmMm` | NBR 7212 |
| Pilar e parede têm lote de no máximo 50 m³; laje e fundação, 100 m³ | `TipoDeElemento::volumeMaximoDoLoteEmM3` | NBR 12655 |
| Carga com abatimento fora da faixa é devolvida | `Concretagem::receberCarga` | NBR 7212 |
| Carga com mais de 150 min de transporte é devolvida | `Concretagem::receberCarga` | NBR 7212 |
| Carga devolvida fica registrada e não conta como volume | `Concretagem::volumeAceitoEmM3` | — |
| Concretagem só conclui com carga aceita; só cancela sem nenhuma | `Concretagem::concluir`, `cancelar` | — |
| Carga devolvida não gera corpo de prova | `Concretagem::moldar` | — |
| Corpo de prova é moldado no dia da concretagem, depois da chegada da carga | `Concretagem::moldar` | NBR 5738 |
| Um exemplar por carga e idade; cada exemplar tem dois corpos de prova | `Exemplar::moldar` | NBR 5739 |
| Cada idade tem janela de rompimento: 28 dias é ±20 h | `IdadeDeEnsaio::toleranciaEmHoras` | NBR 5739 |
| Só 28 dias é idade de aceitação; 7 dias é informação | `IdadeDeEnsaio::ehDeAceitacao` | NBR 12655 |
| Concretagem não conclui sem exemplar de 28 dias moldado | `Concretagem::concluir` | — |
| Resistência = força ÷ área, com uma casa decimal | `ResultadoDeEnsaio::resistenciaEmMPa` | NBR 5739 |
| Só existem cilindros de 100 e 150 mm | `DiametroDoCorpoDeProva` | NBR 5738 |
| Resultado fora da janela de idade é recusado | `CorpoDeProva::romper` | NBR 5739 |
| Corpo de prova descartado exige motivo | `CorpoDeProva::descartar` | — |
| Rompido e descartado são finais | gatilhos na migration `000003` | — |
| A resistência do exemplar é a maior dos dois corpos de prova | `Exemplar::resistenciaEmMPa` | NBR 5739 |
| Lote tem um fck e um grupo de solicitação só | `Lote::adicionarConcretagem` | NBR 12655 |
| Lote de no máximo 50 m³ (vertical) ou 100 m³ (horizontal), em até 3 dias | `Lote::adicionarConcretagem` | NBR 12655 |
| Uma concretagem entra em um lote só | chave primária de `lote_concretagens` | — |
| Lote só é julgado com todos os exemplares de 28 dias resolvidos | `Lote::julgar` | — |
| Amostragem parcial exige ao menos 6 exemplares | `CalculadoraDeFckEstimado` | NBR 12655 |
| fck,est pela fórmula da norma, com piso ψ6 × f1 | `CalculadoraDeFckEstimado` | NBR 12655 |
| Lote julgado não muda mais | `Lote::exigirAberto` e `CHECK` em `lotes` | — |

## O lote e a conta da norma

É o coração do sistema, e a parte em que mais preciso ser explícito sobre o que sei e o que não sei.

### Como o lote se forma

A NBR 12655 não julga concretagem: julga **lote** — o conjunto de concreto que se supõe homogêneo. Um lote tem um fck só, um grupo de solicitação só (peças comprimidas como pilar e parede não se misturam com peças fletidas como laje e viga), volume limitado (50 m³ para o grupo vertical, 100 m³ para o horizontal) e no máximo três dias de concretagem. Uma concretagem pequena não se julga sozinha; junta-se a outras até formar amostra.

A regra que foi para o banco: **uma concretagem entra em um lote só**. A chave primária de `lote_concretagens` é `(obra, concretagem)`, e não `(obra, lote, concretagem)`. Duas pessoas formando lotes ao mesmo tempo com a mesma concretagem passariam por qualquer verificação em PHP; a chave primária não deixa a segunda gravar.

### A conta

Com os exemplares de 28 dias ordenados da menor resistência para a maior (f1 ≤ f2 ≤ … ≤ fn), `CalculadoraDeFckEstimado` faz o que entendo ser o item 6.2.3 da norma:

| Amostragem | n | fck,est |
| --- | --- | --- |
| Total | n ≤ 20 | f1 — o menor exemplar |
| Total | n > 20 | f(i), com i = ⌈0,05 n⌉ |
| Parcial | n < 6 | amostra insuficiente; não julga |
| Parcial | 6 ≤ n < 20 | máx( 2·(f1 + … + f(m−1))/(m−1) − f(m) , ψ6 × f1 ), com m = ⌊n/2⌋ |
| Parcial | n ≥ 20 | f(i), com i = ⌈0,05 n⌉ |

O ψ6 vem de uma tabela por n e por condição de preparo (A para usina; B ou C para concreto dosado na obra). Para n intermediário usa-se o valor do maior n tabulado abaixo — o lado conservador.

O lote é **aceito** se fck,est ≥ fck de projeto; senão, **não conforme**. E a memória de cálculo — valores ordenados, fórmula, ψ6, piso, qual prevaleceu — vai para o banco em JSON junto com o veredito. Quando um lote é reprovado, a primeira coisa que o engenheiro faz é conferir a conta.

### O que precisa ser conferido

**As fórmulas, os limiares e a tabela de ψ6 foram transcritos de memória.** Estão marcados no código com o aviso, e os testes de `FckEstimadoTest` foram calculados à mão a partir das fórmulas *como transcritas* — eles provam que a implementação faz o que a transcrição diz, não que a transcrição está certa.

Antes de qualquer uso real, cada linha da tabela acima e cada valor de `Psi6::TABELA` precisa ser conferido contra o texto vigente da NBR 12655. É a conta que aprova ou reprova uma laje. Se algum número estiver errado, o lugar de corrigir é um só, e os testes mudam junto.

## O resultado do ensaio

A prensa entrega força, em kN. Resistência é força por área, em MPa — e 1 MPa é exatamente 1 N/mm², o que torna a conta direta: `kN × 1000 ÷ área em mm²`. `ResultadoDeEnsaio` existe para essa conta ser feita num lugar só. O diâmetro é enum porque errá-lo erra a resistência em 2,25 vezes.

**Fora da janela, o número não entra.** Um cilindro de 28 dias rompido no 30º dia é mais forte do que era aos 28 — o valor existe, mas não representa a idade nominal. Não é dado; é ruído com cara de dado. `romper()` recusa, e a mensagem diz o que fazer: descartar e registrar o motivo. O corpo de prova descartado não some — fica com o motivo, e o exemplar dele segue com um cilindro só.

**A resistência do exemplar é a maior.** Os dois cilindros vieram do mesmo concreto, moldados no mesmo ato; se um rompeu mais baixo, a causa está no cilindro — bolha, capeamento torto, prensa desalinhada — e não no concreto. `Exemplar::estaIncompleto()` marca quando só um dos dois sobreviveu: o resultado vale, mas com metade da redundância que a norma prevê.

**A coerência vai em gatilho.** `ALTER TABLE` não aceita `CHECK` entre colunas, então três gatilhos garantem no banco o que o domínio garante no código: não existe "rompido" sem resultado, nem "descartado" sem motivo, e nenhum dos dois volta a "curando". É a mesma regra em dois lugares, para que nenhum caminho escape.

**A resistência fica gravada.** É derivada de carga e diâmetro, mas a conta do lote varre resistências aos milhares — recalcular força/área linha a linha no SQL funciona no teste e arrasta em produção.

## O corpo de prova e o tempo

Um corpo de prova só existe para ser rompido numa idade exata. O concreto ganha resistência com o tempo — aos 7 dias tem uns 70% do que terá aos 28 — então o resultado só significa alguma coisa se a idade for a certa.

**A janela.** A NBR 5739 admite uma folga de horário por idade: 28 dias podem ser rompidos até 20 horas antes ou depois do instante exato; 7 dias, 6 horas; 24 horas, apenas meia hora. `CorpoDeProva` calcula o rompimento previsto e a janela, e responde três perguntas que a agenda do laboratório vai fazer: *ainda é cedo?*, *está na hora?*, *passou?*

**Passou é o pior caso.** Corpo de prova vencido perdeu a idade nominal, e o ensaio dele não representa mais nada. É a situação que o sistema existe para evitar — e por isso a tela principal é uma agenda, não uma lista.

**O exemplar.** A norma não olha corpo de prova isolado: olha o exemplar — dois cilindros da mesma carga, moldados no mesmo ato, para a mesma idade — e a resistência dele é a **maior** entre os dois. A lógica é que os dois vieram do mesmo concreto; se um deu menos, foi defeito de moldagem, cura ou ensaio, não do concreto. O menor é descartado como ruído.

**Sem 28 dias não se conclui.** A regra de `concluir()` ficou mais rigorosa nesta etapa: além de carga aceita, exige exemplar de 28 dias moldado. Sem ele o lote nunca poderia ser aceito, e a peça ficaria sem controle para sempre. Melhor recusar enquanto ainda dá para moldar do que descobrir na hora do laudo.

## A concretagem

Quando o caminhão chega, o canteiro faz duas coisas antes de descarregar: olha o relógio e faz o ensaio do cone. `Concretagem::receberCarga` faz as duas na mesma ordem.

**O relógio.** A nota fiscal traz a hora em que a água foi adicionada na usina. A NBR 7212 dá 150 minutos para o concreto ser descarregado — depois disso ele começou a endurecer dentro do caminhão, e nenhum aditivo na obra conserta. Passou, volta.

**O cone.** O abatimento medido é comparado com a faixa da peça. Fora da faixa, volta: concreto mais seco que o especificado não preenche a forma; mais fluido, segrega.

**A carga devolvida não some.** Ela é registrada com número, nota fiscal e motivo. Não vira volume concretado e não pode ter corpo de prova moldado. Mas fica no histórico, porque é o documento que sustenta a discussão com a usina sobre quem paga o concreto recusado.

**Cancelar tem limite.** Uma concretagem só se cancela enquanto nenhuma carga entrou na forma. Depois que o concreto foi lançado, a peça existe: o que se faz é concluir e controlar.

## A interface web

**A agenda é a tela principal.** Não é a lista de obras: é o que o laboratório abre de manhã. Três blocos, em ordem de urgência — o que venceu, o que está na janela agora, o que abre nos próximos sete dias. "Na janela" é calculado contra o relógio, não contra o dia: um corpo de prova de 28 dias moldado às 8h de um dia 1 pode ser rompido a partir das 12h do dia 28, e é nesse momento que ele aparece.

**O resultado se lança na própria linha.** Força em kN, diâmetro, data e hora — e o cilindro some da agenda. Quem está na prensa não vai até a obra procurar a concretagem; o caminho da planilha era esse, e é o que se quer evitar. O mesmo formulário aparece na tela da concretagem, para quem chega pela peça.

**O vencido só descarta.** A agenda não oferece o campo de resultado para corpo de prova fora da janela, e o domínio recusaria de qualquer jeito. O que ela oferece é o descarte com motivo já sugerido.

**Toda alteração é POST com token.** Cada formulário leva `@csrf`, o middleware do Laravel confere, e depois do POST vem um redirecionamento — atualizar a página não reenvia o formulário. GET nunca altera nada.

**A classe e o grupo do lote não são digitados.** Saem da primeira concretagem marcada; se as outras não combinarem, o domínio recusa com a mensagem que explica por quê. Pedir para escolher "C30" numa lista abriria espaço para o engano que a regra existe para impedir.

**O controlador não tem regra.** Lê o formulário, chama o caso de uso, guarda a mensagem, redireciona. Toda mensagem de erro que a tela mostra foi escrita no domínio, para quem está no canteiro ou na prensa — o controlador só a repassa. A única exceção é o erro inesperado, que vira uma frase genérica em vez de um stack trace.

## Quando o concreto não passa

**A não conformidade nasce com o veredito.** `JulgarLote` grava o lote e, se o fck estimado ficou abaixo do fck de projeto, abre a não conformidade na mesma transação. Não existe lote reprovado sem tratamento aberto — é a regra que impede o resultado ruim de ser esquecido numa tabela.

**As providências seguem a ordem da norma**, do mais barato ao mais caro: revisão do projeto com o fck obtido; ensaio não destrutivo para localizar a região fraca; extração de testemunhos (NBR 7680) para medir a resistência real; prova de carga para comprovar; e só no fim reforço ou demolição. Cada providência é imutável — tem data, descrição de ao menos 20 caracteres, responsável e resultado. O ensaio não destrutivo é sempre informativo: ele localiza, não decide. Só a extração de testemunhos informa um fck obtido, e é obrigada a informar.

**O desfecho precisa de prova.** Encerrar como "estrutura aceita" exige revisão de projeto, testemunho ou prova de carga com resultado favorável; "reforçada" exige o reforço executado; "demolida", a demolição. A entidade calcula quais desfechos as providências registradas já sustentam, e a tela só oferece esses. Aceitar no grito não tem caminho.

A sequência foi transcrita de memória da NBR 12655 e da seção de não conformidades da NBR 6118. Como tudo o que é norma neste projeto: confira com o texto vigente.

## Acesso e papéis

Três papéis, que espelham quem circula no controle tecnológico:

| Papel | Opera (carga, moldagem, rompimento, descarte) | Decide (cadastros, lote, julgamento, não conformidade) |
| --- | --- | --- |
| Engenheiro | sim | sim |
| Laboratorista | sim | não |
| Gestor | não | não — só consulta |

As permissões moram no enum `Papel` — `podeOperar()`, `podeDecidir()`. Dois Gates as expõem ao framework, e as rotas dizem qual exigem: `can:operar`, `can:decidir`. Nenhum controlador tem `if ($papel === ...)`. A interface esconde os botões que o papel não pode usar, mas isso é cortesia: quem enviar o POST direto recebe 403, porque a permissão é conferida no servidor, a cada requisição.

Senha só como hash bcrypt, login com uma mensagem única para e-mail e senha errados, resposta de duração fixa para e-mail existente e inexistente demorarem o mesmo, sessão regenerada no login, `@csrf` em todo formulário e destino pós-login guardado na sessão — nunca na URL. Conta desativada não entra, e desativá-la derruba a sessão já aberta. O que não há: limite de tentativas de login e recuperação de senha — próximos passos se o sistema for para produção.

## Banco de dados

SQLite, pelos mesmos motivos de sempre: roda sem servidor, e o schema é padrão o bastante para migrar depois. Chave estrangeira ligada em toda conexão — o SQLite a ignora por padrão, e o driver do Laravel liga o `PRAGMA` sozinho. As regras críticas ficam repetidas no banco: `$tabela->enum()` gera o `CHECK` que recusa `fck = 27` e `idade_dias = 14` tanto quanto o domínio, e as que dependem de mais de uma coluna — "lote julgado exige fck estimado", "o fck obtido só existe em testemunho" — viraram gatilhos, porque o SQLite não aceita acrescentar `CHECK` depois que a tabela existe.

### A tabela mais consultada

A pergunta que o laboratório faz todo dia de manhã é **"o que rompe hoje?"** — e ela precisa responder rápido mesmo com milhares de corpos de prova em cura. Por isso `corpos_de_prova` guarda `rompimento_previsto`, `inicio_janela` e `fim_janela` em colunas, com índice, em vez de calcular na consulta a partir de `moldado_em` e da idade.

É desnormalização deliberada. O custo é manter os três coerentes com a moldagem — e como o corpo de prova é imutável depois de moldado, o custo é zero. Dois índices atendem as duas perguntas: `(situacao, rompimento_previsto)` para a agenda do dia e `(situacao, fim_janela)` para os vencidos.

### A agenda não hidrata o agregado

`AgendaDoLaboratorio` é uma interface de leitura. A implementação usa o Query Builder com quatro `JOIN` e devolve `ItemDaAgenda` — um modelo de leitura com tudo que quem vai romper precisa: obra, peça, fck de projeto, carga, nota fiscal, janela. Não monta `Concretagem` nenhuma, nem instancia model do Eloquent, só para listar cilindros.

### Cascata

`concretagens → elementos` usa `cascadeOnDelete()`. A regra desejável seria `RESTRICT` — não apague elemento já concretado — mas apagar a obra cascateia para elementos, e o `RESTRICT` bloquearia a exclusão da obra inteira. Proteger o elemento concretado é política de aplicação. Está comentado na migration.

### Sobre os valores transcritos da norma

Os limites de tolerância e de volume de lote foram transcritos das normas de memória e estão marcados no código com "confira com o texto vigente". Antes de qualquer uso real, cada número precisa ser conferido contra a edição atual da norma — elas são revisadas, e o sistema não substitui o texto normativo.

## Como o sistema foi construído

O projeto nasceu em **PHP puro**, sem framework: roteador, camada de requisição e resposta, templates, sessão com token anti-CSRF, repositórios com PDO, migrations em SQL e um executor de testes caseiro — tudo escrito à mão, em oito etapas.

Depois foi **migrado para Laravel**, em seis partes, mantendo o domínio intacto: as 39 classes de `app/Dominio` são as mesmas, trocando só o namespace. As regras da NBR 12655 — a janela de rompimento, o cálculo do fck estimado, o tratamento da não conformidade — não mudaram uma linha.

1. Esqueleto, domínio portado e testes de domínio em PHPUnit
2. Persistência: migrations, models Eloquent e repositórios
3. Interface web: rotas, controllers, Blade e as telas de obra
4. Agenda do laboratório e a tela de concretagem
5. Lotes, memória de cálculo e não conformidade na tela
6. Acesso por papel, seeders e documentação final

O que o framework substituiu, peça por peça — e, mais interessante, **o que ele não tocou** — está em [MIGRACAO-LARAVEL.md](MIGRACAO-LARAVEL.md). A versão em PHP puro continua no histórico do Git.

## Estado atual

O ciclo inteiro do controle tecnológico está coberto: a peça é cadastrada com a especificação do projeto; cada caminhão é julgado na chegada pelo relógio e pelo cone; os corpos de prova entram na agenda com a janela de rompimento da norma; o resultado é lançado da própria agenda e recusado fora da janela; as concretagens se juntam em lotes dentro dos limites da NBR 12655; o lote é julgado com a memória de cálculo aberta; e o lote reprovado abre uma não conformidade que só se encerra com providência favorável. Três papéis, 257 testes que vão da entidade isolada ao fluxo HTTP completo, e integração contínua em PHP 8.3 e 8.4.

O que continua verdade desde a primeira etapa: as fórmulas, tolerâncias, limites de lote e a tabela de ψ6 foram transcritos de memória e estão marcados no código. Antes de qualquer uso real, cada número precisa ser conferido contra a edição vigente das normas.
