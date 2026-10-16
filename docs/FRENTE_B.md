# Frente B — entregas e decisões técnicas

Registro do que a Frente B já implementou e das decisões que o enunciado não define. As
decisões abaixo são **técnicas, sem ratificação das quatro frentes**; antes de acoplar outra
frente a elas, levar para `CONTRATOS.md` com a concordância da equipe.

## B1 — personagens e criação do jogador (RF02, RF16, RF17, RF39)

- `Core/Config/CharacterConfigLoader` lê `config/characters.json` e valida campos, faixas,
  `_fonte` e a referência de `armaInicial` em `weapons.json`. Erros indicam arquivo,
  personagem e campo, por exemplo `characters.json: alex.vida deve ser maior que zero.`
- `Domain/Character/CharacterCatalog` rejeita IDs repetidos e passivas repetidas (RF39 exige
  passiva única por personagem).
- `PlayerFactory::create($characterId, $playerId, $position, $collisionRadius, $execute)`
  devolve um `Player` com vida cheia e a arma inicial no slot 0 do `CombatInventory`.
- O raio de colisão e o executor de ataques são recebidos de quem compõe a partida: não há
  valor no enunciado nem no JSON, e a B não inventa números.
- A passiva é apenas **definida** na B1; sua concessão por nível pertence à B4.

## B2 — regeneração e atributos do jogador (RF16, RF17)

- `HealthRegeneration` acumula o tempo lógico com soma compensada, a mesma técnica de
  `Cooldown`. Testado a 30/60/144/240 FPS, com quadros irregulares e com `GameLoop` pausado.
- `Player::regenerate($deltaSeconds)` é chamado a cada passo fixo. Pausa congela a
  regeneração porque `GameLoop` não executa passos enquanto pausado.
- `Player::applyModifiers()` e `removeModifier()` aceitam somente `vidaMaxima`,
  `velocidadeJogador` e `raioColeta`; efeitos de arma vão para `WeaponProgressionService`.
  A aplicação é atômica e cada efeito é rastreado pela sua `source`.

### Decisões técnicas da B2

| Ponto | Decisão | Motivo |
| --- | --- | --- |
| Vida atual quando `vidaMaxima` aumenta | Ganha a mesma diferença (16/16 → 18/18; 10/16 → 12/18) | O item de vida máxima tem efeito imediato |
| Vida atual quando `vidaMaxima` diminui | Limitada ao novo máximo | `Health` não aceita vida acima do máximo |
| Jogador morto e vida máxima | Continua morto | Mudança de atributo não revive |
| Relógio da regeneração com vida cheia | Parado; recomeça do zero ao tomar dano | Evita cura instantânea logo após o dano |
| Bases de `velocidadeJogador` e `raioColeta` | Opcionais em `PlayerFactory::create(..., $optionalBaseStats)` | Pendência A/B/C em `CONTRATOS.md` §9; sem base, Feather e Magnet ficam registrados mas não alteram nada (§7.2) |

`Health` é substituída quando a vida máxima muda; consumidores devem sempre chamar
`$player->health()` em vez de guardar a referência.
