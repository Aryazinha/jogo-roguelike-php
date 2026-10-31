# Entrega C5 — inimigos especiais

A C5 atende o RF36 com dois inimigos que se **comportam** diferente dos comuns, e não só têm
números diferentes:

- **Slime**: persegue como os comuns e, ao morrer, deixa dois Slimes pequenos;
- **Skeleton**: não encosta no jogador. Mantém distância, anda em volta e atira flechas.

**Estado:** C5 concluída e verificada com `composer check`.

## Componentes

| Componente | Responsabilidade |
| --- | --- |
| `Domain/Enemy/SplitOnDeath` | Escuta `EnemyKilled` e agenda os filhos do Slime |
| `Domain/Ai/Behavior/KeepDistanceBehavior` | Persegue, orbita ou foge conforme a distância, com histerese |
| `Domain/Ai/Behavior/RangedMode` | Os três modos: `perseguindo`, `orbitando`, `fugindo` |
| `Domain/Enemy/EnemyProjectile` | A flecha: voa em linha reta até acertar ou acabar o alcance |
| `Domain/System/RangedAttackSystem` | Cooldown de tiro por Skeleton e disparo dentro da faixa |
| `Domain/System/EnemyProjectileSystem` | Move as flechas e aplica o dano pelo `EnemyAttackSystem` |

O `GameWorld` passou a guardar projéteis (`kind = projectile`, sem vida), e o
`EnemySteeringSystem` aceita estratégia por ID do tipo, como `skeleton`, antes do ID do
comportamento.

## Slime

- Os filhos vêm de `aoMorrer` em `enemies.json`: dois `slime_pequeno`.
- **Ponto seguro.** A morte é publicada no meio do `DamageSystem`, então os filhos vão para
  `queueEnemy()` e só entram no `flush()`. Ninguém altera a lista de inimigos enquanto o
  combate a percorre.
- Nascem em ângulos iguais em volta do ponto da morte, a meio raio do Slime, e dentro do mapa.
- **Sem divisão infinita.** O `EnemyCatalog` (C1) recusa um filho que também se divida, e o
  `DamageSystem` publica a morte uma vez só, então golpes extras não geram mais filhos.
- **Teto de 300.** Com o mundo cheio, nascem só os filhos que cabem (`GameWorld::freeEnemySlots()`).
  O Slime morto já liberou a vaga dele, então pelo menos um filho sempre nasce.

## Skeleton

Faixa de tiro com os valores de `enemies.json`: distância ideal 200 px, margem 40 px.

| Distância até o jogador | Modo | Movimento |
| --- | --- | --- |
| acima de 240 px | perseguindo | vai até o jogador |
| entre 160 e 240 px | orbitando | anda de lado, metade gira para cada lado |
| abaixo de 160 px | fugindo | se afasta |

**Histerese.** A troca não acontece exatamente nas bordas:

- perseguindo → orbitando só ao chegar na **ideal** (200 px);
- orbitando → perseguindo só ao passar de **240 px**;
- fugindo → orbitando só ao recuar de novo até **200 px**.

Sem isso, um Skeleton parado na fronteira trocaria de modo a cada passo e tremeria na tela.

**Tiro.** Cada Skeleton tem um `Cooldown` próprio (2,2 s) e só atira com o jogador a até
240 px. A flecha sai da borda do Skeleton na direção em que o jogador **estava**, a 260 px/s,
e voa até 1,5 × 240 = 360 px. Ela não persegue, então dá para desviar.

**Acerto.** O teste é feito sobre o trecho inteiro percorrido no passo, com o
`segmentCircleFirstHit` da A: uma flecha rápida não atravessa o jogador entre dois passos. O
dano passa pelo `EnemyAttackSystem::strike()` (C4), então respeita a mesma invulnerabilidade
do contato. A flecha some ao acertar mesmo que o jogador esteja protegido.

## Testes

| Arquivo | O que cobre |
| --- | --- |
| `tests/Unit/Domain/Enemy/SplitOnDeathTest.php` | Filhos só depois do `flush`, posições, borda, sem nova divisão, golpes extras |
| `tests/Unit/Domain/Ai/Behavior/KeepDistanceBehaviorTest.php` | Os três modos, cada transição com histerese e lado da órbita |
| `tests/Unit/Domain/Enemy/EnemyProjectileTest.php` | Voo, alcance e inativo parado |
| `tests/Unit/Domain/System/RangedAttackSystemTest.php` | Disparo, faixa, cooldown de 2,2 s e quem pode atirar |
| `tests/Unit/Domain/System/EnemyProjectileSystemTest.php` | Acerto, flecha rápida, erro e invulnerabilidade |
| `tests/Integration/SpecialEnemiesTest.php` | Skeleton e Slime reais de `enemies.json` |
