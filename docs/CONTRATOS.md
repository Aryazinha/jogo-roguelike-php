# Contratos compartilhados

As assinaturas que mais de uma frente usa. **Divergência aqui é a principal causa de
retrabalho em trabalho de grupo**, e é muito mais barata de resolver numa reunião de duas
horas do que na semana 5.

## Regras

1. Estes contratos são definidos na **reunião da semana 1**, antes de qualquer código de jogo.
2. Depois de congelados, **só mudam com a concordância das quatro frentes**, e a mudança é
   registrada aqui com data.
3. Cada frente programa contra o contrato, não contra a implementação da outra frente.
4. O que não está aqui é decisão interna de cada frente, e não precisa de acordo.

**Congelado em:** `____ / ____ / 2026`
**Presentes:** `______________________________`

---

## 1. Interfaces de apresentação

`RendererInterface`, `AudioInterface`, `InputInterface` — a fronteira entre o domínio e o
Raylib. Nenhuma classe de regra de jogo chama FFI; toda a apresentação passa por aqui.

*A definir na reunião.*

- [ ] `RendererInterface` — desenhar sprite, texto, forma, HUD; apresentar quadro
- [ ] `AudioInterface` — tocar efeito, tocar música, volume
- [ ] `InputInterface` — estado de eixos e botões

**Proposto por:** `______` · **Data:** `______`

---

## 2. Entidade base e value objects

`Entity` e os tipos que circulam entre todos os sistemas.

*A definir na reunião.*

- [ ] `Entity` — o que toda entidade tem: posição, raio de colisão, estado de vida
- [ ] `Vector2` — imutável, com as operações usadas no movimento e na mira
- [ ] `Stats` — atributos que os modificadores alteram
- [ ] `Health` — vida atual e máxima, dano e cura
- [ ] `Cooldown` — tempo restante e verificação de disponibilidade

**Proposto por:** `______` · **Data:** `______`

---

## 3. EventBus e a lista fechada de eventos

O barramento que desacopla o domínio do áudio, dos efeitos visuais e da pontuação. A lista de
eventos é **fechada**: acrescentar um evento novo é mudança de contrato.

*A definir na reunião.* Os sete eventos exigidos pelo RNF05 entram obrigatoriamente.

- [ ] `PlayerDamaged`
- [ ] `EnemyHit`
- [ ] `EnemyKilled`
- [ ] `LevelUp`
- [ ] `ChestOpened`
- [ ] `PlayerDied`
- [ ] `ItemPicked`
- [ ] `WaveStarted`

**Proposto por:** `______` · **Data:** `______`

---

## 4. Esquema dos arquivos de configuração

O formato dos JSON em `config/`. Os arquivos já existem com valores provisórios; o que falta
é acordar o **formato**, porque três frentes leem esses arquivos.

*A definir na reunião.*

- [ ] Como um modificador de atributo é representado
- [ ] Como uma referência entre arquivos é feita (arma → upgrade, inimigo → comportamento)
- [ ] O que acontece quando um campo obrigatório falta: falha na carga, com mensagem clara

**Proposto por:** `______` · **Data:** `______`

---

## 5. Repositório de pontuação e tabela `scores`

*A definir na reunião.* O esquema inicial está em
`database/migrations/001_create_scores.sql`.

- [ ] `ScoreRepositoryInterface` — salvar partida, listar ranking
- [ ] Formato do objeto de partida que entra e sai do repositório
- [ ] Critério de desempate refletido na consulta: pontuação desc, tempo asc, data asc

**Proposto por:** `______` · **Data:** `______`

---

## 6. GameState e StateStack

A máquina de estados das telas. O `LevelUpState` empilha sobre o `PlayState`, que continua
existindo, congelado, e volta intacto ao desempilhar.

*A definir na reunião.*

- [ ] `GameState` — `enter`, `update`, `render`, `exit`
- [ ] `StateStack` — empilhar, desempilhar, substituir
- [ ] Quem decide a transição: o estado ou quem o contém

**Proposto por:** `______` · **Data:** `______`

---

## 7. TargetingStrategy e StatModifier

Como uma arma escolhe alvo, e como upgrades, itens passivos e passivas de personagem alteram
números.

*A definir na reunião.*

- [ ] `TargetingStrategy` — recebe o quê, devolve o quê
- [ ] `StatModifier` — atributo, tipo (`FLAT` ou `PERCENT`), valor, origem
- [ ] Ordem de resolução: base, soma dos `FLAT`, produto dos `PERCENT`

**Proposto por:** `______` · **Data:** `______`

---

## 8. BehaviorStrategy e WorldView

O comportamento dos inimigos. A `WorldView` é **somente leitura**: a IA lê o mundo e devolve
uma direção, nunca altera nada.

*A definir na reunião.*

- [ ] `BehaviorStrategy` — recebe inimigo, visão do mundo e `dt`; devolve direção
- [ ] `WorldView` — posição do jogador e consulta de vizinhos, e mais nada

**Proposto por:** `______` · **Data:** `______`

---

## Histórico de mudanças

| Data | Contrato | O que mudou | Quem aprovou |
| --- | --- | --- | --- |
| | | | |
