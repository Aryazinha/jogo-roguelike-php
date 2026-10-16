# Entrega C3 — comportamentos comuns e separação

A C3 faz os inimigos comuns (Zombie, Spider e Husk) perseguirem o jogador sem se empilharem
no mesmo ponto, como descrito em `PLANO.md` §4.1. É a base de movimento que C5 (Slime e
Skeleton) e C6 (Warden) reaproveitam.

**Estado:** C3 concluída e verificada com `composer check`.

## Componentes

| Componente | Responsabilidade |
| --- | --- |
| `Domain/Ai/Behavior/BehaviorStrategy` | Contrato do §8 de `CONTRATOS.md`: lê o mundo e devolve uma direção |
| `Domain/Ai/Behavior/SeekBehavior` | Ir na direção do jogador |
| `Domain/Ai/Behavior/SeparationBehavior` | Afastar-se dos inimigos colados |
| `Domain/Ai/Behavior/WeightedBehavior` | Somar vontades com pesos e limitar a velocidade |
| `Domain/Ai/Behavior/SteeringSettings` | Pesos e raio, e montagem do comportamento `perseguir` |
| `Core/Config/SteeringConfigLoader` | Lê a seção `separacao` de `enemies.json` |
| `Domain/Ai/CrowdWorldView` | Retrato leve do mundo para a IA, montado uma vez por passo |
| `Domain/System/EnemySteeringSystem` | Decide a direção de todos e move pelo `MovementSystem` da A |

Padrão usado: **Strategy**. Cada arquétipo indica o ID do comportamento (`perseguir`,
`manter_distancia`, `boss_em_fases`) e o sistema escolhe a estratégia registrada para ele.

## Como funciona

A cada passo fixo:

1. o sistema tira um retrato do mundo (`CrowdWorldView`);
2. cada inimigo vivo pede a direção à sua estratégia, **olhando o mesmo retrato**;
3. só depois disso todos andam, pelo `MovementSystem` da Frente A, na velocidade do
   arquétipo e dentro dos limites do mapa.

Como todos decidem antes de qualquer um andar, a ordem da lista não muda o resultado.

O comportamento `perseguir` é:

```
direção = limitar_a_1( pesoPerseguir × seek + pesoSeparacao × separation )
```

- **seek**: vetor unitário até o jogador;
- **separation**: soma, para cada inimigo vivo a distância `d` dentro do raio, de
  `(1 − d/raio)` na direção oposta a ele. Vale 1 quando está colado e 0 na borda.

Valores atuais em `enemies.json`, todos provisórios: raio 40 px, `pesoPerseguir` 1,0 e
`pesoSeparacao` 1,4.

## Decisões e desvios do plano

| Ponto | O que foi feito | Por quê |
| --- | --- | --- |
| Força da separação | `1 − d/raio` em vez do `1/d` do `PLANO.md` | Com `1/d` normalizado, os empurrões quase equilibrados no meio de um grupo viravam velocidade máxima para um lado qualquer |
| Comprimento da direção | Entre 0 e 1, não só "normalizada ou zero" (§8) | Permite andar mais devagar no meio do grupo; o `MovementSystem` já aceita intensidade parcial |
| Visão usada pela IA | `CrowdWorldView` em vez da `SpatialWorldView` | Desempenho (seção abaixo); as armas continuam com a visão da A |
| Empate exato de posição | Ângulo calculado a partir dos dois IDs | Dois inimigos no mesmo ponto saem em direções opostas, sempre iguais |

## Desempenho (RNF06)

O primeiro formato fazia cada inimigo consultar a `SpatialWorldView`, que cria objetos para
todos os candidatos. Com 300 inimigos cercando o jogador, o passo de movimento levava de 85
a 250 ms. O `CrowdWorldView` guarda as posições em arrays de `float`, usa células do
tamanho do raio de separação e deixa a separação somar direto nesses arrays.

| Cenário (300 inimigos, máquina da Lara) | Antes | Depois, sem OPcache | Depois, com JIT |
| --- | --- | --- | --- |
| Cerco em volta do jogador, de 10 a 20 s | 85–250 ms | mediana 23 ms, p95 30 ms | mediana 16 ms, p95 21 ms |

Na mesma máquina, o benchmark A10 da Frente A dá p95 de 26,5 ms, contra 7,6 ms na máquina
de referência. Ou seja, esta máquina é cerca de 3,5 vezes mais lenta, e o passo de IA
deve ficar perto de 5–7 ms na referência. O orçamento conjunto (A + C) é medido de novo na
C11.

## Testes

| Arquivo | O que cobre |
| --- | --- |
| `tests/Unit/Domain/Ai/Behavior/SeekBehaviorTest.php` | Direção unitária, zero em cima do jogador |
| `tests/Unit/Domain/Ai/Behavior/SeparationBehaviorTest.php` | Força por distância, limite, grupo equilibrado, filtros e empate |
| `tests/Unit/Domain/Ai/Behavior/WeightedBehaviorTest.php` | Pesos, limite de comprimento e peso zero |
| `tests/Unit/Core/Config/SteeringConfigLoaderTest.php` | Seção real e campos inválidos |
| `tests/Unit/Domain/Ai/CrowdWorldViewTest.php` | Contrato da visão e igualdade com o caminho genérico |
| `tests/Unit/Domain/System/EnemySteeringSystemTest.php` | Velocidade, olhar, pausa, mortos e ordem da lista |
| `tests/Integration/EnemyCrowdTest.php` | 50 inimigos reais: espalham com separação e colapsam sem ela |
