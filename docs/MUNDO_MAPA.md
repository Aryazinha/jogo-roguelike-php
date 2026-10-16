# Entrega C2 — mundo, mapa e registro espacial

A C2 fornece o mundo real que o benchmark A10 simulava com entidades sintéticas: limites do
mapa, posição inicial do jogador, pontos de spawn e o registro dos inimigos que a Frente A
consulta e atinge. Cobre a parte de conteúdo do RF03 e prepara o RF05.

**Estado:** C2 concluída e verificada com `composer check`. Não desenha nada: o mapa é só
lógico, e a Frente D decide como ele aparece na tela.

## Componentes

| Componente | Responsabilidade |
| --- | --- |
| `config/map.json` | Tamanho do mapa, margem da borda e anel de spawn, todos provisórios |
| `Core/Config/MapConfigLoader` | Lê e valida `map.json` |
| `Domain/World/MapLayout` | Medidas do mapa, `bounds()` para o `MovementSystem` e início do jogador |
| `Domain/World/SpawnPointPicker` | Sorteia ponto de surgimento fora da tela e dentro do mapa |
| `Domain/World/GameWorld` | Registro de jogador e inimigos; provider e resolver dos contratos da A |

## Como usar

```php
$layout = (new MapConfigLoader)->load('config/map.json');
$world = new GameWorld($jogador, $layout);           // jogador vai para o centro
$view = $world->createView();                        // SpatialWorldView da A sobre o mundo

$spawn = new SpawnPointPicker($layout, new SeededRandomIndex($seed));
$world->addEnemy($factory->create('spider', $spawn->pick($jogador->position())));

// Durante o passo: mover, depois $view->refresh(); armas e dano usam $world->resolve($id).
// No fim do passo, ponto seguro:
$retirados = $world->flush();
```

## Decisões

| Ponto pendente no plano | Decisão provisória | Quem confirma |
| --- | --- | --- |
| Dimensões do mapa | 4000 × 4000 px, margem de 32 px | Equipe |
| Distância mínima de spawn | 760 a 960 px do jogador, acima da meia diagonal de 1280 × 720 (≈ 734 px) | Frente D, pelo tamanho da tela |
| Ordem segura para consequências | Inimigos criados durante o combate vão para `queueEnemy()` e entram em `flush()` | Frente A |

Outras regras:

- **O jogador não é da C.** `GameWorld` recebe qualquer `Entity&Damageable`. O personagem real
  (Steve ou Alex) vem da Frente B.
- **IDs únicos no mundo.** Um ID repetido, inclusive o do jogador, é recusado na hora, e não
  só quando a grade espacial é reconstruída.
- **Morto some da consulta antes do `flush()`.** O `DamageSystem` desativa o inimigo na
  morte e a `SpatialWorldView` ignora inativos. O `flush()` só libera a memória.
- **O teto conta a fila.** `aliveEnemyCount()` soma os inimigos agendados, para as ondas da
  C7 não passarem de 300 com divisões do Slime pendentes.
- **Canto como último recurso.** Se o jogador estiver encostado na borda e várias tentativas
  caírem fora do mapa, o spawn vai para o canto mais distante do jogador.

## Testes

| Arquivo | O que cobre |
| --- | --- |
| `tests/Unit/Domain/World/MapLayoutTest.php` | Limites com margem, centro, `contains` e medidas inválidas |
| `tests/Unit/Core/Config/MapConfigLoaderTest.php` | Arquivo real e campos inválidos |
| `tests/Unit/Domain/World/SpawnPointPickerTest.php` | 500 sorteios no anel, seed, quatro quadrantes e canto |
| `tests/Unit/Domain/World/GameWorldTest.php` | Registro, IDs, fila, `flush()` e contagem de vivos |
| `tests/Integration/GameWorldSpatialTest.php` | Visão espacial, movimento e dano da A sobre o mundo real |
