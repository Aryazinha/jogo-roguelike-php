# Entrega C10 — elementos quebráveis e drops

A C10 atende o RF38 (elementos de cenário que, quebrados, deixam itens) e a origem ambiental
do RF27 (item que cura o jogador, o Steak).

**Estado:** C10 concluída e verificada com `composer check`.

## Componentes

| Componente | Responsabilidade |
| --- | --- |
| `Core/Config/SceneryConfigLoader` | Lê `scenery.json` |
| `Domain/Scenery/BreakableType` | Vida, raio, bloqueio e tabela de drops de um tipo |
| `Domain/Scenery/DropChance` | Uma linha da tabela de drops |
| `Domain/Scenery/TemporaryBonus` | Fúria, Pressa e Agilidade, com o `StatModifier` da A |
| `Domain/Scenery/ScenerySettings` | Densidade, tipos e bônus |
| `Domain/Scenery/Breakable` | O elemento no mapa (`Entity` + `Damageable`) |
| `Domain/Scenery/Pickup` | O item caído no chão |
| `Domain/Scenery/PickedItem` | Registro do item pego, para a Frente B |
| `Domain/Scenery/SceneryPlacer` | Espalha os elementos no início da partida |
| `Domain/System/BreakableSystem` | Dano, quebra, drop e coleta |
| `Domain/System/SceneryBlocker` | Impede atravessar Crate e Ore |

## Elementos (`scenery.json`)

| Elemento | Vida | Bloqueia | Pode deixar |
| --- | --- | --- | --- |
| Grass (tufo de grama) | 1 | não | nada 70%, Steak 20%, XP pequeno 10% |
| Crate (caixote) | 3 | sim | Steak 45%, bônus temporário 35%, item especial 20% |
| Ore (minério) | 8 | sim | item especial 60%, bônus temporário 40% |

Os três casos que o RF38 cita estão cobertos: **Steak**, **itens especiais** e **bônus
temporários**. O bônus é sorteado entre Fúria (+50% de dano), Pressa (−30% de cooldown) e
Agilidade (+30% de velocidade), todos por 15 s.

## Regras

- **Quantidade.** A densidade (8) é lida como elementos por bloco de 1000 × 1000 px. Ao pé da
  letra ("por mil unidades quadradas"), daria 128 mil elementos num mapa de 4000 × 4000 px. Com
  a leitura adotada, são 124 no mapa atual. A nota do arquivo foi atualizada.
- **Posição.** Dentro do mapa, sem encostar em outro elemento e a mais de 150 px do início do
  jogador, para ele não começar preso.
- **Quebra única.** Ao chegar a vida zero, o elemento quebra uma vez. Golpes seguintes não fazem
  nada, e ele deixa **no máximo um** item.
- **Sorteio exato.** Mesmo esquema dos baús: inteiros de 0 a 9999, tabela somando 1.
- **Bloqueio.** Depois do movimento, quem ficou dentro de um Crate ou Ore é empurrado para a
  borda dele. Grass e itens no chão não bloqueiam (decisão da pendência "pickups bloqueiam?").
- **Coleta.** O jogador pega o item ao encostar. O registro fica em `takePicked()` até a Frente B
  aplicar, porque `ItemPicked` ainda não tem payload ratificado.

## Pendências com a equipe

| Pendência | Situação |
| --- | --- |
| Dano das armas nos quebráveis | As armas da A só miram em `kind = enemy` (§7.1). O dano entra por `BreakableSystem::damage()` e `damageArea()`; falta ligar ao ataque das armas (A/C) |
| O que é o `item_especial` | Sai como ID. A Frente B decide o efeito |
| Quanto vale o `xp_pequeno` | Sai como ID. A Frente B decide o valor |
| Payload de `ItemPicked` | Até ser ratificado, os dados saem por `takePicked()` |

## Testes

| Arquivo | O que cobre |
| --- | --- |
| `tests/Unit/Core/Config/SceneryConfigLoaderTest.php` | Arquivo real, limites do sorteio e 6 arquivos inválidos |
| `tests/Unit/Domain/Scenery/SceneryEntitiesTest.php` | Elemento, item no chão e registro no mundo |
| `tests/Unit/Domain/Scenery/SceneryPlacerTest.php` | Quantidade, sem sobreposição, longe do início e seed |
| `tests/Unit/Domain/System/BreakableSystemTest.php` | Quebra única, cada tipo de drop, área e coleta |
| `tests/Unit/Domain/System/SceneryBlockerTest.php` | Bloqueio de jogador e inimigos, e quem não bloqueia |
| `tests/Integration/SceneryDropsTest.php` | Cenário real: colocar, quebrar tudo e pegar um item |
