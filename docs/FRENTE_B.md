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

## B3 — experiência e nível (RF11, RF12, RF13)

- `LevelFormula::requiredXp($nivel)` devolve o XP para sair do nível: `2n + 7` até 15,
  `5n - 38` de 16 a 30 e `9n - 158` a partir de 31 (fórmula sugerida no RF13).
- `Experience` guarda nível, XP no nível atual e XP total; um único ganho pode subir vários
  níveis e o excedente é preservado.
- `ExperienceSystem` assina `EnemyKilled`, sorteia o XP na faixa do tipo com o `RandomIndex`
  injetado (mesma seed, mesma progressão) e ignora uma segunda morte do mesmo `enemyId`.
- `EnemyXpConfigLoader` lê somente `xp.min`/`xp.max` de `enemies.json`; o restante do arquivo
  pertence à Frente C.
- `LevelUp(playerId, level, totalXp, elapsedSeconds)` é publicado uma vez por nível alcançado.
  O payload é proposta da B e precisa ser confirmado com a D (`CONTRATOS.md` §3).

### Decisões técnicas da B3

| Ponto | Decisão | Motivo |
| --- | --- | --- |
| Nível inicial | 1 | `enemies.json` usa `nivelMinimoAparicao: 1` para o primeiro inimigo |
| Nível usado na fórmula | O nível atual: sair do 1 custa `2 × 1 + 7 = 9` | Leitura direta da tabela do RF13 |
| XP direto ou por gemas | Direto, no momento da morte | Gemas físicas e raio de coleta dependem de base ainda indefinida; `grant()` já permite trocar a origem do XP |
| Vários níveis de uma vez | Um `LevelUp` por nível, em ordem | Cada nível deve gerar sua própria escolha no RF14 |

## B4 — itens passivos e passivas de personagem (RF21, RF39)

- `PassiveConfigLoader` lê os seis itens de `passives.json`; `PassiveInventory::acquire($id)`
  sobe um nível por vez até `niveisMaximos` e recusa o excedente sem alterar nada.
- `ProgressionEffects` encaminha cada efeito: `vidaMaxima`, `velocidadeJogador` e `raioColeta`
  vão para o `Player`; os demais vão, por `WeaponProgressionService`, a toda arma equipada que
  possua o atributo. Gunpowder não altera a espada, que não tem `raioExplosao`.
- Os efeitos de arma ficam registrados: `syncWeapon($slot)` deve ser chamado depois de adquirir
  uma arma nova ou de uma evolução, que descarta os modificadores antigos (decisão da A8).
- `CharacterPassiveSystem` assina `LevelUp`: Steve recebe `dano +10%` nos níveis múltiplos de 5
  e Alex `cooldown -0,2 s` nos múltiplos de 4. Como há um `LevelUp` por nível, nenhum marco é
  pulado num ganho grande de XP.
- Origens: `passivo:<id>:<nivel>:<indice>` e `personagem:<passiva>:<nivel>`.

### Decisões técnicas da B4

| Ponto | Decisão | Motivo |
| --- | --- | --- |
| Limite de passivos diferentes | Nenhum | O enunciado não define; não inventar requisito |
| Efeito que tornaria a arma inválida | Ignorado só naquela arma, mantendo-se registrado | `CONTRATOS.md` §7.2 proíbe clamp e limite mínimo inventado |
| Cooldown resultante muito próximo de zero | Tratado como zero (tolerância `1e-9`) | `1,8 - 9 × 0,2` dá `2e-16` em ponto flutuante e faria a arma atacar em todo passo |

> **Para a equipe:** com o RF39 aplicado literalmente, o cooldown de 1,8 s do arco da Alex
> chega a zero no nível 36. A partir daí a passiva deixa de alcançar o arco. Vale decidir em
> conjunto se isso é aceitável ou se o balanceamento das armas deve mudar.
