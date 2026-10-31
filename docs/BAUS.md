# Entrega C8 — baús

A C8 atende o RF26: derrotar o boss gera um baú, e o baú tem raridade sorteada com as chances
do enunciado. A Frente C cria, sorteia e abre o baú. **Quem aplica a recompensa (os upgrades
de arma) é a Frente B.**

**Estado:** C8 concluída e verificada com `composer check`.

## Componentes

| Componente | Responsabilidade |
| --- | --- |
| `config/chests.json` | Raridades, geração e item de cura |
| `Core/Config/ChestConfigLoader` | Lê e valida o arquivo |
| `Domain/Chest/ChestRarity` | Uma raridade: chance e recompensa |
| `Domain/Chest/ChestSettings` | Sorteio de raridade e chance de baú por inimigo |
| `Domain/Chest/Chest` | O baú no mapa; abre uma única vez |
| `Domain/Chest/ChestReward` | O que saiu do baú, imutável e com o ID do baú |
| `Domain/Chest/HealingItem` | O Steak do RF27, usado pelos quebráveis (C10) |
| `Domain/System/ChestSystem` | Gera os baús nas mortes e os abre quando o jogador encosta |

## Raridades

| Baú | Chance | Recompensa | Origem |
| --- | --- | --- | --- |
| Madeira | 85% | 1 upgrade de arma | enunciado |
| Bronze | 10% | 2 upgrades de arma | enunciado |
| Ouro | 5% | 3 upgrades de arma | equipe, para fechar 100% |

O sorteio é feito com números inteiros de 0 a 9999: madeira de 0 a 8499, bronze de 8500 a
9499, ouro de 9500 a 9999. Assim as chances saem exatas, sem erro de arredondamento. Por
isso o arquivo aceita no máximo 4 casas decimais por probabilidade, e a soma precisa dar 1.

## Quando nasce um baú

- **Boss derrotado:** sempre, onde ele morreu (`bausAoDerrotarBoss`: 1).
- **Inimigo comum:** chance de 0,4% (`chancePorInimigoAbatido`). É acréscimo da equipe, marcado
  como provisório, para o evento **Tesouro** (C9) ter o que aumentar. Com o Tesouro, a chance
  vai a 1%.

A morte é publicada no meio do combate, então o baú só entra no mundo no `update()` seguinte,
em ponto seguro.

## Abertura

O baú abre quando o jogador encosta nele. `Chest::open()` devolve a recompensa na primeira
vez e `null` nas seguintes, e o baú aberto sai do mundo no `flush()`.

A recompensa fica guardada até alguém buscar com `ChestSystem::takeOpened()`, e cada uma sai
uma vez só. **Por que não um evento?** `ChestOpened` está na lista fechada do `CONTRATOS.md`,
mas o payload ainda está pendente entre B, C e D. Quando for ratificado, basta publicar o
evento com o conteúdo de `ChestReward`.

## Pendências com a equipe

- Ordem entre derrota do boss, abertura do baú e tela de vitória (RF09).
- Formato final do `ChestOpened`.
- Se o Steak também deve sair de baú. Hoje ele sai dos elementos quebráveis (C10).

## Testes

| Arquivo | O que cobre |
| --- | --- |
| `tests/Unit/Core/Config/ChestConfigLoaderTest.php` | Valores do RF26 e 8 arquivos inválidos |
| `tests/Unit/Domain/Chest/ChestSettingsTest.php` | Limites do sorteio, 20 mil sorteios perto de 85/10/5 e chance por inimigo |
| `tests/Unit/Domain/System/ChestSystemTest.php` | Baú do boss, chance comum, Tesouro, abertura única e visão do mundo |
| `tests/Integration/BossChestTest.php` | Warden real derrotado: um baú e uma recompensa |
