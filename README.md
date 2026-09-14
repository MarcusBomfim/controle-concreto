# Controle Tecnológico de Concreto

Sistema para registrar concretagens, moldar e acompanhar corpos de prova, lançar os resultados dos ensaios de compressão e julgar a aceitação de cada lote conforme a **NBR 12655**.

## O problema

Toda concretagem estrutural exige, por norma, que se moldem corpos de prova, que eles sejam curados e rompidos aos 7 e aos 28 dias, e que o resultado prove que o concreto entregue atingiu o **fck** especificado no projeto. Se não atingiu, a peça pode precisar de reforço — ou de demolição.

Na prática, isso vive em planilha do laboratório. O resultado dos 28 dias chega semanas depois da concretagem, a laje de cima já foi executada, e ninguém cruza o número com a peça exata que ele representa. Quando uma não conformidade aparece, a pergunta "qual caminhão, qual laje, qual dia" não tem resposta rápida.

O sistema resolve isso amarrando cada corpo de prova ao caminhão que o originou, cada caminhão à peça concretada, e cada resultado ao lote de aceitação — com a conta da norma feita pelo sistema, não na mão.

## Requisitos

**PHP 8.1 ou superior** — o projeto usa enums, `readonly` e `match`.

```bash
php -v
```

A extensão `pdo_sqlite` já vem nas distribuições oficiais. Não é preciso Composer nem servidor de banco.

## Como rodar

```bash
php ferramentas/migrar.php
```

```bash
php ferramentas/semear.php
```

O banco é criado em `banco/controle-concreto.sqlite`, fora do controle de versão. A carga de demonstração é feita em PHP, pelo domínio, com datas relativas a hoje — para a agenda mostrar corpos de prova vencidos, na janela e futuros. Um SQL com datas fixas envelheceria em uma semana.

## Como rodar os testes

```bash
php testes/executar.php
```

Os testes de infraestrutura sobem um SQLite em memória e aplicam as migrations reais.

## Estrutura

```text
controle-concreto/
├── banco/
│   └── migrations/         # SQL versionado, aplicado em ordem
├── ferramentas/
│   ├── migrar.php
│   └── semear.php
├── src/
│   ├── autoload.php
│   ├── Dominio/
│   │   ├── Regras.php
│   │   ├── ExcecaoDeDominio.php
│   │   ├── Obra/           # Obra e o repositório
│   │   ├── Concreto/       # ClasseDeResistencia, Abatimento
│   │   ├── Estrutura/      # ElementoEstrutural, TipoDeElemento e o repositório
│   │   ├── Concretagem/    # Concretagem, Carga, MotivoDeDevolucao e o repositório
│   │   └── Ensaio/         # CorpoDeProva, Exemplar, IdadeDeEnsaio, ResultadoDeEnsaio
│   ├── Aplicacao/          # AgendaDoLaboratorio, RegistrarRompimento, DescartarCorpoDeProva
│   └── Infraestrutura/
│       ├── Banco/          # Conexao, Migrador
│       └── Repositorio/    # implementações em SQLite
├── testes/
│   ├── executar.php
│   ├── Executor.php        # executor de testes mínimo, sem PHPUnit
│   ├── ajuda.php           # fábricas compartilhadas entre os testes
│   ├── dominio/
│   └── infraestrutura/
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
| Rompido e descartado são finais | gatilhos em `003_resultados_de_ensaio.sql` | — |
| A resistência do exemplar é a maior dos dois corpos de prova | `Exemplar::resistenciaEmMPa` | NBR 5739 |

## O resultado do ensaio

A prensa entrega força, em kN. Resistência é força por área, em MPa — e 1 MPa é exatamente 1 N/mm², o que torna a conta direta: `kN × 1000 ÷ área em mm²`. `ResultadoDeEnsaio` existe para essa conta ser feita num lugar só. O diâmetro é enum porque errá-lo erra a resistência em 2,25 vezes.

**Fora da janela, o número não entra.** Um cilindro de 28 dias rompido no 30º dia é mais forte do que era aos 28 — o valor existe, mas não representa a idade nominal. Não é dado; é ruído com cara de dado. `romper()` recusa, e a mensagem diz o que fazer: descartar e registrar o motivo. O corpo de prova descartado não some — fica com o motivo, e o exemplar dele segue com um cilindro só.

**A resistência do exemplar é a maior.** Os dois cilindros vieram do mesmo concreto, moldados no mesmo ato; se um rompeu mais baixo, a causa está no cilindro — bolha, capeamento torto, prensa desalinhada — e não no concreto. `Exemplar::estaIncompleto()` marca quando só um dos dois sobreviveu: o resultado vale, mas com metade da redundância que a norma prevê.

**A coerência vai em gatilho.** `ALTER TABLE` não aceita `CHECK` entre colunas, então três gatilhos garantem no banco o que o domínio garante no código: não existe "rompido" sem resultado, nem "descartado" sem motivo, e nenhum dos dois volta a "curando". É a mesma regra em dois lugares, para que nenhum caminho escape.

**A resistência fica gravada.** É derivada de carga e diâmetro, mas a Etapa 6 vai varrer resistências aos milhares para a conta do lote — recalcular força/área linha a linha no SQL funciona no teste e arrasta em produção.

## O corpo de prova e o tempo

Um corpo de prova só existe para ser rompido numa idade exata. O concreto ganha resistência com o tempo — aos 7 dias tem uns 70% do que terá aos 28 — então o resultado só significa alguma coisa se a idade for a certa.

**A janela.** A NBR 5739 admite uma folga de horário por idade: 28 dias podem ser rompidos até 20 horas antes ou depois do instante exato; 7 dias, 6 horas; 24 horas, apenas meia hora. `CorpoDeProva` calcula o rompimento previsto e a janela, e responde três perguntas que a agenda do laboratório vai fazer: *ainda é cedo?*, *está na hora?*, *passou?*

**Passou é o pior caso.** Corpo de prova vencido perdeu a idade nominal, e o ensaio dele não representa mais nada. É a situação que o sistema existe para evitar — e por isso a Etapa 7 vai mostrar uma agenda, não uma lista.

**O exemplar.** A norma não olha corpo de prova isolado: olha o exemplar — dois cilindros da mesma carga, moldados no mesmo ato, para a mesma idade — e a resistência dele é a **maior** entre os dois. A lógica é que os dois vieram do mesmo concreto; se um deu menos, foi defeito de moldagem, cura ou ensaio, não do concreto. O menor é descartado como ruído.

**Sem 28 dias não se conclui.** A regra de `concluir()` ficou mais rigorosa nesta etapa: além de carga aceita, exige exemplar de 28 dias moldado. Sem ele o lote nunca poderia ser aceito, e a peça ficaria sem controle para sempre. Melhor recusar enquanto ainda dá para moldar do que descobrir na hora do laudo.

## A concretagem

Quando o caminhão chega, o canteiro faz duas coisas antes de descarregar: olha o relógio e faz o ensaio do cone. `Concretagem::receberCarga` faz as duas na mesma ordem.

**O relógio.** A nota fiscal traz a hora em que a água foi adicionada na usina. A NBR 7212 dá 150 minutos para o concreto ser descarregado — depois disso ele começou a endurecer dentro do caminhão, e nenhum aditivo na obra conserta. Passou, volta.

**O cone.** O abatimento medido é comparado com a faixa da peça. Fora da faixa, volta: concreto mais seco que o especificado não preenche a forma; mais fluido, segrega.

**A carga devolvida não some.** Ela é registrada com número, nota fiscal e motivo. Não vira volume concretado e — na Etapa 3 — não vai poder ter corpo de prova moldado. Mas fica no histórico, porque é o documento que sustenta a discussão com a usina sobre quem paga o concreto recusado.

**Cancelar tem limite.** Uma concretagem só se cancela enquanto nenhuma carga entrou na forma. Depois que o concreto foi lançado, a peça existe: o que se faz é concluir e controlar.

## Banco de dados

SQLite, pelos mesmos motivos de sempre: roda sem servidor, e o SQL é padrão o bastante para migrar depois. Chave estrangeira ligada em toda conexão (`PRAGMA foreign_keys = ON` — o SQLite a ignora por padrão), e as regras críticas repetidas em `CHECK`: o banco recusa `fck = 27` e `idade_dias = 14` tanto quanto o domínio.

### A tabela mais consultada

A pergunta que o laboratório faz todo dia de manhã é **"o que rompe hoje?"** — e ela precisa responder rápido mesmo com milhares de corpos de prova em cura. Por isso `corpos_de_prova` guarda `rompimento_previsto`, `inicio_janela` e `fim_janela` em colunas, com índice, em vez de calcular na consulta a partir de `moldado_em` e da idade.

É desnormalização deliberada. O custo é manter os três coerentes com a moldagem — e como o corpo de prova é imutável depois de moldado, o custo é zero. Dois índices atendem as duas perguntas: `(situacao, rompimento_previsto)` para a agenda do dia e `(situacao, fim_janela)` para os vencidos.

### A agenda não hidrata o agregado

`AgendaDoLaboratorio` é uma interface de leitura. A implementação faz um `SELECT` com quatro `JOIN` e devolve `ItemDaAgenda` — um modelo de leitura com tudo que quem vai romper precisa: obra, peça, fck de projeto, carga, nota fiscal, janela. Não monta `Concretagem` nenhuma só para listar cilindros.

### Cascata

`concretagens → elementos` usa `ON DELETE CASCADE`. A regra desejável seria `RESTRICT` — não apague elemento já concretado — mas apagar a obra cascateia para elementos, e o `RESTRICT` bloquearia a exclusão da obra inteira. Proteger o elemento concretado é política de aplicação. Está comentado no SQL.

### Sobre os valores transcritos da norma

Os limites de tolerância e de volume de lote foram transcritos das normas de memória e estão marcados no código com "confira com o texto vigente". Antes de qualquer uso real, cada número precisa ser conferido contra a edição atual da norma — elas são revisadas, e o sistema não substitui o texto normativo.

## Etapas

1. **Base, obra e elementos estruturais** — concluída
2. **Concretagem e cargas: a regra do abatimento** — concluída
3. **Corpos de prova e exemplares: idades e tolerâncias de rompimento** — concluída
4. **Persistência em SQLite** — concluída
5. **Resultados de ensaio** — concluída
6. Lotes e fck estimado: a conta da NBR 12655
7. Interface web: agenda do laboratório e resultados por peça
8. Não conformidade, acesso por papel e integração contínua

## Estado atual

Etapa 5 concluída. O laboratório lança o que a prensa mediu, o corpo de prova recusa resultado fora da janela de idade, o exemplar sabe sua resistência, e o banco garante por gatilho que rompido tem número e descartado tem motivo. O `semear.php` já grava quatro resultados históricos aos 7 dias.
