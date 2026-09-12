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

Não é preciso Composer nem banco de dados nesta etapa.

## Como rodar os testes

```bash
php testes/executar.php
```

## Estrutura

```text
controle-concreto/
├── src/
│   ├── autoload.php
│   └── Dominio/
│       ├── Regras.php
│       ├── ExcecaoDeDominio.php
│       ├── Obra/           # Obra
│       ├── Concreto/       # ClasseDeResistencia, Abatimento
│       ├── Estrutura/      # ElementoEstrutural, TipoDeElemento
│       └── Concretagem/    # Concretagem, Carga, MotivoDeDevolucao
├── testes/
│   ├── executar.php
│   ├── Executor.php        # executor de testes mínimo, sem PHPUnit
│   ├── ajuda.php           # fábricas compartilhadas entre os testes
│   └── dominio/
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

## A concretagem

Quando o caminhão chega, o canteiro faz duas coisas antes de descarregar: olha o relógio e faz o ensaio do cone. `Concretagem::receberCarga` faz as duas na mesma ordem.

**O relógio.** A nota fiscal traz a hora em que a água foi adicionada na usina. A NBR 7212 dá 150 minutos para o concreto ser descarregado — depois disso ele começou a endurecer dentro do caminhão, e nenhum aditivo na obra conserta. Passou, volta.

**O cone.** O abatimento medido é comparado com a faixa da peça. Fora da faixa, volta: concreto mais seco que o especificado não preenche a forma; mais fluido, segrega.

**A carga devolvida não some.** Ela é registrada com número, nota fiscal e motivo. Não vira volume concretado e — na Etapa 3 — não vai poder ter corpo de prova moldado. Mas fica no histórico, porque é o documento que sustenta a discussão com a usina sobre quem paga o concreto recusado.

**Cancelar tem limite.** Uma concretagem só se cancela enquanto nenhuma carga entrou na forma. Depois que o concreto foi lançado, a peça existe: o que se faz é concluir e controlar.

### Sobre os valores transcritos da norma

Os limites de tolerância e de volume de lote foram transcritos das normas de memória e estão marcados no código com "confira com o texto vigente". Antes de qualquer uso real, cada número precisa ser conferido contra a edição atual da norma — elas são revisadas, e o sistema não substitui o texto normativo.

## Etapas

1. **Base, obra e elementos estruturais** — concluída
2. **Concretagem e cargas: a regra do abatimento** — concluída
3. Corpos de prova e exemplares: idades e tolerâncias de rompimento
4. Persistência em SQLite
5. Resultados de ensaio
6. Lotes e fck estimado: a conta da NBR 12655
7. Interface web: agenda do laboratório e resultados por peça
8. Não conformidade, acesso por papel e integração contínua

## Estado atual

Etapa 2 concluída. A concretagem recebe caminhões e julga cada um na hora: tempo de transporte e abatimento contra a especificação da peça. Carga devolvida fica registrada com o motivo. Ainda sem banco — tudo em memória e coberto por testes.
