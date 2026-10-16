# Entrega C1 — arquétipos e fábrica de inimigos

A C1 transforma `config/enemies.json` em conteúdo validado e cria os inimigos reais que os
sistemas da Frente A movem, colidem e atingem. Ela cobre a base de RF22 (3 comuns e o boss),
RF36 (Slime e Skeleton) e o primeiro passo de RF05 (quais tipos podem surgir em cada nível).

**Estado:** C1 concluída e verificada com `composer check`. Movimento dos inimigos (C3),
divisão do Slime e tiro do Skeleton (C5), fases do Warden (C6) e ondas (C7) ainda não existem;
esta entrega só deixa os dados prontos para elas.

## Componentes

| Componente | Responsabilidade |
| --- | --- |
| `Domain/Enemy/EnemyCategory` | Os três grupos do JSON: `comuns`, `especiais` e `boss` |
| `Domain/Enemy/EnemyArchetype` | Dados fixos de um tipo, compartilhados por todas as instâncias |
| `Domain/Enemy/SplitRule` | O que o inimigo gera ao morrer (`aoMorrer` do Slime) |
| `Domain/Enemy/RangedSettings` | Distância ideal, histerese e cooldown do tiro do Skeleton |
| `Domain/Enemy/EnemyCatalog` | Índice por ID e por categoria, referências e mínimos do enunciado |
| `Domain/Enemy/Enemy` | Entidade real: `Entity` + `Damageable`, com vida própria |
| `Domain/Enemy/EnemyFactory` | Cria inimigos pelo tipo, com IDs sequenciais por partida |
| `Core/Config/EnemyConfigLoader` | Lê o arquivo e recusa chaves duplicadas |
| `Core/Config/EnemyDefinitionMapper` | Valida campo a campo e monta o catálogo |

Nada em `Domain/Enemy` importa `Core`, Raylib ou FFI.

## Como usar

```php
$catalog = (new EnemyConfigLoader)->load('config/enemies.json');
$factory = new EnemyFactory($catalog);

$zombie = $factory->create('zombie', new Vector2(400.0, 300.0)); // ID enemy-1

// O DamageSystem da A recebe os metadados sem consultar o nome nem a vida.
$target = $zombie->asTarget();
$damage->damageEnemy($target->entity, $hit, $target->enemyTypeId, $target->isBoss, $elapsed);

// Para o diretor de ondas (C7): tipos que podem ser sorteados no nível atual.
$tipos = $catalog->spawnableAtLevel($nivelDoJogador);
```

## Regras adotadas

- **Arquétipo e instância separados.** Vida máxima, dano, XP, velocidade e raio ficam no
  arquétipo; vida atual e posição ficam em `Enemy`. Duas instâncias do mesmo tipo usam o
  mesmo objeto de arquétipo (padrão Flyweight, previsto no `PLANO.md`).
- **XP é só metadado.** `xpMin` e `xpMax` chegam à Frente B pelo `enemyTypeId` do evento
  `EnemyKilled`. A Frente C não concede XP.
- **Boss tem hora, não nível.** O Warden usa `surgeAosSegundos` (600) e não tem
  `nivelMinimoAparicao`. Ele nunca entra no sorteio comum de spawn.
- **Filhos de divisão não são sorteados.** `slime_pequeno` tem `geradoApenasPorDivisao` e só
  aparece quando um Slime morre. O catálogo recusa um filho que também se divida, o que
  geraria inimigos sem fim.
- **Mínimos do enunciado.** O catálogo recusa menos de 3 comuns (RF22), menos de 2 especiais
  (RF36, sem contar o Slime pequeno) e qualquer quantidade de boss diferente de um.
- **Procedência obrigatória.** Todo inimigo precisa de `_fonte`. Os valores do Zombie vêm do
  RF22 e estão marcados como `enunciado`; o teste confere que não mudaram.
- **IDs reproduzíveis.** A fábrica numera `enemy-1`, `enemy-2`… a partir do zero em cada
  partida. Um tipo inexistente falha sem gastar número.

## Validações do arquivo

| Situação | Mensagem (exemplo) |
| --- | --- |
| Campo que não pertence ao grupo | `enemies.json: zombie.aoMorrer é um campo de gameplay desconhecido.` |
| Número inválido | `enemies.json: spider: vida deve ser maior que zero.` |
| Divisão para tipo inexistente | `enemies.json: slime se divide em um inimigo inexistente: creeper.` |
| Mesmo ID em dois grupos | `enemies.json: ID de inimigo duplicado: zombie.` |
| Chave repetida no texto | `enemies.json: comuns.zombie contém chave/ID duplicado.` |

As fases do Warden (`fases`) só são verificadas como objeto nesta entrega. A leitura completa
de durações, limiares e modificadores entra junto com o boss, na C6.

## Testes

| Arquivo | O que cobre |
| --- | --- |
| `tests/Unit/Domain/Enemy/EnemyArchetypeTest.php` | Valores, nível mínimo, regras de boss/divisão/distância e dados inválidos |
| `tests/Unit/Domain/Enemy/EnemyCatalogTest.php` | Índices, tipos por nível, referências de divisão e mínimos |
| `tests/Unit/Domain/Enemy/EnemyFactoryTest.php` | Valores iniciais, IDs, vida independente e metadados de alvo |
| `tests/Unit/Core/Config/EnemyConfigLoaderTest.php` | Arquivo real campo a campo e 20 configurações inválidas |
| `tests/Integration/EnemyDamageTest.php` | Zombie real morrendo uma única vez pelo `DamageSystem` da A |
