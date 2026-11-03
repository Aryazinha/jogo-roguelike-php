# Frente C — conteúdo e dificuldade

O que a Frente C entregou, como usar e o que ainda depende do grupo. O roteiro original está em
[PLANO_FRENTE_C.md](PLANO_FRENTE_C.md).

A Frente C cuida de inimigos, boss, ondas, baús, eventos e cenário. Ela **não** desenha (D), não
dá XP nem aplica recompensa (B) e não implementa armas, movimento, colisão ou dano genérico (A):
usa os sistemas da A e entrega dados para B e D.

Os números de balanceamento estão em `config/enemies.json`, `waves.json`, `chests.json`,
`events.json`, `scenery.json` e `map.json`. Valores do enunciado estão marcados como
`"_fonte": "enunciado"`; os demais, como `provisorio`.

## Visão geral

| Entrega | O que faz | Requisitos | Principais classes |
| --- | --- | --- | --- |
| C1 | Inimigos lidos de `enemies.json` | RF22, RF36 | `EnemyArchetype`, `EnemyCatalog`, `EnemyFactory`, `Enemy` |
| C2 | Mapa, início do jogador, pontos de spawn | RF03 | `MapLayout`, `SpawnPointPicker`, `GameWorld` |
| C3 | Perseguir sem empilhar | RF22, RNF06 | `SeekBehavior`, `SeparationBehavior`, `EnemySteeringSystem` |
| C4 | Dano de contato no jogador | RF08 | `EnemyAttackSystem` |
| C5 | Slime que divide, Skeleton que atira | RF36 | `SplitOnDeath`, `KeepDistanceBehavior`, `RangedAttackSystem` |
| C6 | Boss Warden com fases | RF22, RF09 | `WardenBrain`, `BossSystem` |
| C7 | Ondas e escalonamento | RF05, RF28, RF29 | `WaveSettings`, `WaveDirector` |
| C8 | Baús | RF26 | `ChestSettings`, `Chest`, `ChestSystem` |
| C9 | Eventos aleatórios | RF35 | `EventDirector` |
| C10 | Quebráveis e drops | RF38, RF27 | `Breakable`, `BreakableSystem`, `SceneryBlocker` |
| C11 | Tudo junto, sem janela | RF03, RNF06 | `ContentSession` |

## Como usar

```php
$sessao = ContentSession::fromProject($raiz, $jogador, $seed, $barramento);

// a cada passo fixo do GameLoop:
$sessao->step($dt, $tempo, $nivelDoJogador);
```

Ordem dentro de `step()`: eventos → ondas → boss → movimento dos inimigos → bloqueio pelo
cenário → tiros e flechas → dano de contato → baús e itens → ponto seguro (`flush()`).

- **A:** as armas rodam entre o movimento e o ponto seguro, com `$sessao->world` como
  `EnemyTargetResolver` e `$sessao->damage` para o dano.
- **B:** o jogador entra em `fromProject()`. A B escuta `EnemyKilled` (XP pelo `enemyTypeId`) e
  lê `chests->takeOpened()`, `breakables->takePicked()` e `events->xpMultiplier()`. Cada item de
  `takePicked()` já traz o que aplicar: `healing` (Steak) → `collectHealing()`, `bonus` →
  `collectBonus()`, `xp` (Orbe) → `collectXp()` e `weaponUpgrades` (Livro) →
  `grantWeaponUpgrades()`, usando o `pickupId` como ID.
- **D:** desenha a partir de `world->snapshots()` e consulta `boss->phase()`,
  `events->active()` e `waves->currentBandIndex()`.

### Partida jogável (`PlaySession`)

`Application/Play/PlaySession` junta as três partes num passo só, e é o que o `jogo.php` usa:

```php
$partida = PlaySession::start($raiz, 'steve', $seed, $barramento);

// a cada passo fixo:
$partida->step($dt, $entrada);   // anda, roda a C, as armas atacam, aplica itens e baús
$partida->isOver();              // vitória ou derrota
```

- As armas do jogador (A) rodam dentro do passo da C, depois que os inimigos andam e antes do
  dano de contato. Cada golpe também acerta os quebráveis no alcance da arma.
- Itens pegos e baús abertos viram efeito nos serviços da B (`collectHealing`, `collectBonus`,
  `collectXp`, `grantWeaponUpgrades`), e a Chuva de XP liga o multiplicador do XP.
- **Vitória:** derrotar o Warden. A partida segue até o jogador abrir um baú ou por 20 s, para a
  recompensa do boss (RF26) não se perder. Morrer nesse intervalo ainda conta como vitória.
- `PlaySessionSnapshotProvider` entrega para a D o que está perto do jogador, com a onda, o evento
  ativo e a vida do Warden no HUD.

## Regras importantes

**Inimigos (C1).** Os dados fixos de cada tipo ficam no arquétipo, compartilhado por todas as
instâncias (Flyweight); vida atual e posição ficam no `Enemy`. A fábrica numera os IDs em
sequência, então a mesma seed gera os mesmos IDs. O catálogo recusa menos de 3 comuns (RF22),
menos de 2 especiais (RF36) ou qualquer quantidade de boss diferente de um.

**Mundo (C2).** Mapa de 4000 × 4000 px. Os inimigos surgem a 760–960 px do jogador, fora de uma
tela de 1280 × 720. Quem é criado durante o combate (filhos do Slime, invocados, baús) espera o
**ponto seguro** no fim do passo para entrar no mundo.

**Movimento (C3).** Cada inimigo soma seek (ir até o jogador) e separation (afastar-se de quem
está colado, com força `1 − d/raio`). Todos decidem a direção olhando o mesmo retrato do mundo e
só depois andam, então a ordem da lista não muda o resultado. Com 50 inimigos amontoados, depois
de 3 s a menor distância entre eles passa de 12 px; sem separação, não passa.

**Dano no jogador (C4).** Encostar já conta, inclusive a tangência. No máximo um golpe por
passo (vale o maior dano) e 0,5 s de invulnerabilidade depois de cada golpe, em tempo de
partida, para a pausa não consumir esse tempo.

**Slime e Skeleton (C5).** O Slime morto deixa dois Slimes pequenos, que não se dividem de novo.
O Skeleton persegue acima de 240 px, foge abaixo de 160 px e anda de lado entre os dois, com
**histerese** para não ficar tremendo na fronteira. Ele atira a cada 2,2 s na direção em que o
jogador estava. A flecha é testada no trecho inteiro do passo, para não atravessar o jogador.

**Warden (C6).** Surge aos 600 s. Fases: perseguindo → telegrafando (0,8 s parado, direção
travada) → investindo (3× a velocidade) → recuperando (1 s parado), com 8 s de espera entre
investidas. Aos 70% e 40% de vida invoca 8 Zombies (uma vez por limiar). Abaixo de 25% fica
enfurecido: +30% de velocidade e −30% de espera. A derrota é o `EnemyKilled` com `isBoss = true`,
que já existe no contrato; `BossSystem::isDefeated()` informa a vitória (RF09).

**Ondas (C7).** Cinco faixas de tempo, de 2 Spiders a cada 2 s até 6 inimigos a cada 0,7 s. A
dificuldade cresce em quantidade, frequência, tipos novos e força: +4% de vida e +3% de dano
por nível do jogador. Um tipo só aparece depois que o nível o libera (o Zombie, a partir do 6).

**Teto de 300.** Ondas, divisão do Slime e invocação do boss criam só o que cabe. Uma vaga fica
reservada para o Warden, e o total nunca passa de 300.

**Baús (C8).** O boss sempre deixa um baú: madeira 85% (1 upgrade), bronze 10% (2) e ouro 5%
(3, acréscimo da equipe para fechar 100%). Inimigos comuns têm 0,4% de chance, também acréscimo
da equipe, para o Tesouro ter o que aumentar. O baú abre uma vez só, quando o jogador encosta.

**Eventos (C9).** Horda (inimigos × 3 e intervalo × 0,5, por 20 s), Chuva de XP (XP × 2, 30 s)
e Tesouro (chance de baú × 2,5, 45 s). Um por vez, a cada 60–120 s, escolhido por peso
(40/40/20). Ao terminar, tudo volta ao normal.

**Cenário (C10).** Grama (1 de vida, não bloqueia), caixote (3) e minério (8), que bloqueiam a
passagem. Cada elemento quebra uma vez e solta no máximo um item: Steak (cura 5, RF27), bônus
temporário (Fúria, Pressa ou Agilidade, 15 s), Orbe de XP (5 XP) ou Livro Encantado, o item
especial (1 upgrade de arma). Os valores ficam em `scenery.json`, no bloco `itens`. A densidade de `scenery.json` é
lida como 8 por bloco de 1000 × 1000 px, o que dá 124 elementos no mapa.

**Sorteios.** Todo sorteio usa um gerador com seed, e cada sistema tem o seu. A mesma seed repete
a partida inteira. Probabilidades de baú e de drop são sorteadas com inteiros de 0 a 9999, então
as porcentagens saem exatas.

## Desempenho (RNF06)

`tools/content-benchmark.php` mede o passo inteiro da C com 300 inimigos reais cercando o
jogador:

| Medição | Mediana | p95 |
| --- | --- | --- |
| Passo da C, sem OPcache | 6,9 ms | 9,1 ms |
| Passo da C, com OPcache/JIT | 4,5 ms | 6,7 ms |
| Benchmark A10 da Frente A, mesma máquina e hora | 3,0 ms | 5,3 ms |

Somando os p95 de A e C dá cerca de 14,4 ms, abaixo dos 16,6 ms de um quadro a 60 FPS. A primeira
versão do movimento levava até 250 ms com 300 inimigos; o ganho veio de montar um retrato leve do
grupo uma vez por passo (`CrowdWorldView`). Falta medir junto com o desenho da D.

## Pendências com o grupo

| Pendência | Com quem |
| --- | --- |
| Calibrar as ondas jogando: parado, o jogador morre em uns 13 s no começo | C, com todos testando |
| Confirmar os 0,5 s de invulnerabilidade | A |
| Formato de `ChestOpened` e `ItemPicked` (até lá, saem por `takeOpened()` e `takePicked()`) | B, D |
| Tamanho da tela, para confirmar a distância de spawn | D |
| Se o evento `WaveStarted` é necessário (hoje existe `currentBandIndex()`) | D |
| Ordem entre derrota do Warden, baú e tela de vitória | Todos |

## Testes

São 317 testes da Frente C, entre unitários e de integração, com os casos obrigatórios do RNF03
para geração de inimigos e dano. Os principais testes de aceite:

| Teste | O que prova |
| --- | --- |
| `EnemyCrowdTest` | 50 inimigos se espalham com separação e colapsam sem ela |
| `EnemyChaseAndHitTest` | Zombies chegam, batem respeitando a invulnerabilidade e matam uma vez |
| `SpecialEnemiesTest` | Skeleton e Slime reais com comportamento diferente dos comuns |
| `WardenFightTest` | Luta completa contra o Warden, repetida igual com a mesma seed |
| `WaveCurveTest` | Curva de 0 a 600 s com teto de 300 |
| `BossChestTest` | Warden derrotado deixa um baú com uma recompensa |
| `RandomEventsTest` | A Horda multiplica o ritmo de spawn só enquanto dura |
| `SceneryDropsTest` | Cenário real colocado, quebrado e coletado |
| `ContentSessionTest` | Partida de 12 minutos com todo o conteúdo e repetição pela seed |
