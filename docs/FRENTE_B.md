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

## B5 — escolhas de level up (RF14, RF15, RF19, RF21)

- `LevelUpService` assina `LevelUp` e guarda uma escolha pendente por nível. Enquanto
  `hasPendingChoice()` for verdadeiro, Application mantém o `GameLoop` pausado e a Frente D
  mostra `currentOffer()`; `choose($indice, $slotParaSubstituir)` aplica a opção uma única vez.
- `LevelUpOfferGenerator` sorteia até três `LevelUpOption`, primeiro uma de cada tipo do RF14
  (nova arma, melhoria de arma, item passivo) e depois completa com as restantes. Mesma seed e
  mesmo estado geram as mesmas opções.
- `WeaponLevels` guarda o nível de cada arma pelo ID base. Ficou na B5, e não na B6, porque as
  opções de melhoria dependem dele; a B6 acrescenta as evoluções.
- Arma nova é equipada no slot livre e recebe os efeitos de passivos já adquiridos.

### Decisões técnicas da B5

| Ponto | Decisão | Motivo |
| --- | --- | --- |
| Quantidade de opções | Até 3, uma de cada tipo quando houver | `PLANO.md` registra 3 opções para o RF14 |
| Inventário cheio (RF15) | Arma nova continua oferecida, com `requiresReplacement`; `choose` exige o slot a substituir | `PLANO_FRENTE_B.md` pede o fluxo de substituição |
| Arma substituída | Sai do registro de níveis e pode ser oferecida de novo, recomeçando do nível 1 | A substituição cria uma arma nova, como em `CombatInventory::replace` |
| Nenhuma opção elegível | `currentOffer()` devolve lista vazia e consome o nível; Application não pausa | Evita uma tela de level up sem escolha |
| Payload para a D | `LevelUpOption(kind, contentId, level, requiresReplacement)` | Proposta da B, a confirmar com a D |

## B6 — upgrades e evoluções especiais (RF19, RF37)

- `EvolutionService` verifica os requisitos de `evolutions.json` (arma equipada no nível mínimo
  e passivo no nível mínimo), pede a troca à Frente A por `WeaponProgressionService::evolve()` e
  reaplica os efeitos de passivos com `syncWeapon`. Cada arma evolui uma única vez.
- Combinações reais testadas: Stone Sword 5 + Sharpness 3 → Enchanted Stone Sword e
  TNT 5 + Gunpowder 3 → Mega TNT. Requisito ausente falha com a causa e não altera arma,
  modificadores nem cooldown.
- No level up, a melhoria de uma arma no último upgrade vira a evolução quando elegível:
  `LevelUpOption(UPGRADE_WEAPON, 'stone_sword', 5, evolutionId: 'enchanted_stone_sword')`.
  Depois de evoluída, a arma não recebe mais melhorias.
- Os 16 upgrades de `upgrades.json` (níveis 2 a 5 das quatro armas) passam pelo controle de nível.

### Decisões técnicas da B6

| Ponto | Decisão | Motivo |
| --- | --- | --- |
| Onde a evolução aparece | Como a melhoria da arma no level up; os baús (B7) usam o mesmo serviço | RF19: "durante o level up ou em baús"; RF14 continua com três tipos |
| Nível da arma evoluída | Mantém o nível alcançado e não recebe mais upgrades | `upgrades.json` só tem níveis para as armas base |
| Efeitos depois da evolução | Upgrades descartados (decisão A8); passivos e passivas reaplicados | Os passivos continuam adquiridos; os upgrades eram da arma base |

## B7 — itens, cura e bônus temporários (RF19, RF26, RF27, RF35, RF38)

A Frente C decide quando e onde baús, drops e eventos aparecem; a B aplica o efeito.

- `RewardConfigLoader` lê apenas `cura` de `chests.json` (Steak cura 5) e `bonusTemporarios`
  de `scenery.json` (Fúria, Pressa e Agilidade, 15 s cada).
- `PickupService` aplica cura, bônus ou XP; cada `pickupId` tem efeito uma única vez,
  jogador morto não coleta e, depois de aplicar, publica `ItemPicked(playerId, pickupId,
  itemId, elapsedSeconds)` para o feedback da D.
- `TemporaryBonuses` conta a duração com `Cooldown`, pelo tempo lógico; `update()` é chamado a
  cada passo fixo e, portanto, para durante a pausa. Ao expirar, `ProgressionEffects::revoke()`
  remove só as origens do bônus, inclusive de armas adquiridas durante ele.
- `ChestRewardService::grantWeaponUpgrades($bauId, $quantidade)` aplica os "N upgrades de arma"
  do RF26 uma única vez por baú.
- `ExperienceSystem::setXpMultiplier()` atende a Chuva de XP: a C liga com `2.0` (efeito
  `xpDropado +100%`) e restaura `1.0` ao fim do evento.

### Decisões técnicas da B7

| Ponto | Decisão | Motivo |
| --- | --- | --- |
| Coletar bônus já ativo | Renova a duração, sem acumular o efeito | Evita Fúria multiplicando o dano a cada caixote |
| Upgrade de baú | Evolução elegível tem prioridade; senão, melhoria sorteada com seed | RF19 cita evoluções "em baús" |
| Baú sem melhoria possível | Aplica as que houver e devolve a lista do que foi concedido | Não inventar recompensa substituta |
| XP com multiplicador | Arredondado para o inteiro mais próximo | XP é inteiro na B3 |
| `item_especial` e quantidade de `xp_pequeno` | **Pendente da Frente C**; `collectXp()` já recebe a quantidade | Nenhum arquivo define esses valores |
| `ChestOpened` | Não criado pela B; a C publica e chama `grantWeaponUpgrades` | O evento pertence à C (`CONTRATOS.md` §3) |

## B8 — estado, fim e resumo da partida (RF09, RF30)

- `RunState` assina `EnemyKilled` e `PlayerDied`. `advance($delta)` é chamado a cada passo fixo,
  então o tempo de pausa não entra na conta (RF30); a soma é compensada como em `Cooldown`.
- Cada `enemyId` é contado uma única vez. `EnemyKilled` com `isBoss = true` marca vitória, e a
  partida **continua em modo livre** até a morte (`PLANO.md` §1.3). `recordVictory()` existe para
  o caso de a C sinalizar a vitória por outro caminho.
- A morte encerra a partida uma única vez; depois disso nenhuma chamada altera o estado.
  `summary()` devolve um `RunSummary` imutável (personagem, nível, mortes, tempo, vitória, seed).

## B9 — pontuação e nome do jogador (RF32, RF33)

- `ScoreCalculator` (versão 1): `nível × 100 + inimigos × 10 + segundos inteiros × 2 + vitória × 500`,
  a fórmula registrada em `PLANO.md` §1.3.
- `PlayerName` normaliza espaços e recusa nome vazio, caractere de controle, UTF-8 inválido e
  mais de 20 caracteres. `ScoreEntry::fromSummary()` associa o nome ao resumo.

## B10 — SQLite e ranking (RF34, RNF01)

- `Domain/Score/ScoreRepositoryInterface` fica no domínio; `Infrastructure/Persistence` tem
  `SqliteConnection`, `Migrator` e `SqliteScoreRepository`. Nenhuma classe de `Domain/` usa PDO.
- `Migrator` aplica `database/migrations/*.sql` em ordem de nome, um arquivo por transação, e
  registra a versão em `schema_migrations`; rodar de novo não reaplica nada.
- `ranking($limite, $personagem)` ordena por pontuação decrescente, menor tempo, partida mais
  antiga e, como último critério, menor `id` (o `criado_em` do SQLite tem resolução de 1 s).
- O banco padrão é `database/jogo.sqlite`, já ignorado pelo `.gitignore`. Os testes usam um
  arquivo temporário, fecham e reabrem o banco e apagam o arquivo no fim.

### Decisões técnicas da B8 a B10

| Ponto | Decisão | Motivo |
| --- | --- | --- |
| Tempo na pontuação | Segundos inteiros, arredondados para baixo | `scores.tempo_segundos` é `INTEGER` |
| Fonte da vitória | `EnemyKilled.isBoss`, classificação fornecida pela C | Evita um evento novo na lista fechada |
| Tamanho máximo do nome | 20 caracteres | Cabe na tela de ranking; o enunciado não define |
| Empate no mesmo segundo | `id` crescente decide a partida mais antiga | `criado_em` não distingue registros do mesmo segundo |

## B11 — integração e regressão

`Application/Progression/ProgressionSession` monta toda a Frente B para uma partida a partir de
`config/*.json` e liga os assinantes no `EventBus`. `ProgressionSessionTest` percorre, sem
janela, o fluxo completo: inimigos derrotados → XP → níveis → escolhas → Steak e Fúria → baú →
boss (vitória) → morte → pontuação → ranking em SQLite, e confere que a mesma seed reproduz
exatamente o mesmo resultado. `B11ArchitectureTest` garante que `src/Domain` não depende de PDO,
SQLite, FFI, Raylib, `Core/Config`, `Application` ou `Infrastructure`.

### Como as outras frentes usam a B

```php
$b = ProgressionSession::start('config', 'steve', 'player', $posicao, $raio, $seed, $eventBus,
    $weaponAttackSystem, $weaponAttackSystem);
```

| Quem | Quando | Chamada |
| --- | --- | --- |
| Application | A cada passo fixo | `$b->update($dt)` (regeneração, bônus e tempo) |
| Application | Depois de cada passo | se `$b->levelUps->hasPendingChoice()`, pausar o `GameLoop` |
| D | Tela de level up | `$b->levelUps->currentOffer()` e `$b->levelUps->choose($i, $slot)`; lista vazia = não pausar |
| D | HUD | `$b->player->health()`, `$b->experience->experience()`, `$b->bonuses->remainingSeconds($id)` |
| C | Steak coletado | `$b->pickups->collectHealing($pickupId, $b->steak, $tempo)` |
| C | Bônus coletado | `$b->pickups->collectBonus($pickupId, $bonusId, $tempo)` |
| C | Baú aberto | `$b->chests->grantWeaponUpgrades($bauId, $quantidade)` |
| C | Chuva de XP | `$b->experience->setXpMultiplier(2.0)` no início e `1.0` no fim |
| A | Inimigo e jogador morrem | nada a fazer: `EnemyKilled` e `PlayerDied` já chegam pelo `EventBus` |
| D | Fim da partida | `$b->run->summary()` e `$b->finish(new PlayerName($nome), $repositorio)` |
| D | Tela de ranking | `SqliteScoreRepository::ranking(10)` depois de `Migrator::migrate()` |

### Pendências que dependem de outras frentes

| Ponto | Frente | Situação |
| --- | --- | --- |
| Payload de `LevelUp` e `LevelUpOption` | D | Proposto pela B; confirmar antes da tela de level up |
| Payload de `ItemPicked` | C/D | Proposto pela B |
| `item_especial` e quantidade de `xp_pequeno` | C | Sem definição em `scenery.json` |
| Bases de `velocidadeJogador` e `raioColeta` | A/B/C | Sem valor no enunciado; Feather, Magnet e Agilidade ficam sem efeito até lá |
| Cooldown do arco da Alex no nível 36 | Todas | Ver alerta na B4 |
| Teste da A que depende de `pcre.jit` | A | `WeaponConfigLoaderTest` falha com o JIT ligado (padrão do PHP); passa com `-d pcre.jit=0` |
