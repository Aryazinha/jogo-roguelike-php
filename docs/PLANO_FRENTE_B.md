# Plano de trabalho — Frente B: progressão e dados

| Campo | Definição |
| --- | --- |
| Responsável | `[NOME B]` |
| Escopo | Personagens, experiência, níveis, aquisições, passivos, pontuação e ranking |
| Requisitos principais | RF02, RF09, RF11–RF17, RF19, RF21, RF27, RF30, RF32–RF34, RF37, RF39, RNF01 e RNF03 |
| Estado | Planejado; dados iniciais existem, mas a implementação de produção ainda deve ser realizada |

Este documento organiza o trabalho da Frente B. Ele não substitui o enunciado nem
`docs/PLANO.md`. Em caso de conflito, essas duas fontes prevalecem. As decisões de combate já
implementadas pela Frente A devem ser consumidas pelas APIs existentes, sem duplicação.

---

## 1. Resultado esperado

Ao final da Frente B, o jogo deve conseguir:

1. criar Steve ou Alex com atributos, arma e passiva corretos;
2. regenerar vida conforme o personagem escolhido;
3. conceder, acumular e exibir experiência;
4. evoluir o nível pela fórmula definida;
5. gerar escolhas válidas de level up;
6. adquirir armas, aplicar upgrades e itens passivos;
7. reconhecer e conceder evoluções especiais;
8. controlar cura, bônus temporários e estatísticas da partida;
9. calcular a pontuação final;
10. salvar e consultar o ranking em SQLite.

A Frente B decide **quando** uma progressão é concedida. A Frente A continua responsável por
executar armas, cooldowns, dano e modificadores de combate. A Frente D apresenta as escolhas;
a Frente C fornece inimigos, XP por tipo, baús e drops.

---

## 2. Escopo confirmado

### 2.1 Responsabilidade direta

| Requisito | Entrega da Frente B |
| --- | --- |
| RF02 | Catálogo, seleção lógica e criação do personagem escolhido |
| RF11–RF13 | XP, acúmulo, fórmula e evolução de nível |
| RF15 | Decisão de aquisição/substituição no inventário de duas armas |
| RF16–RF17 | Steve, Alex, regeneração, arma inicial e atributos |
| RF19 | Concessão de upgrades e controle do nível das armas |
| RF21 | Catálogo, inventário e aplicação de itens passivos |
| RF30 | Registro do tempo lógico de sobrevivência |
| RF32 | Cálculo determinístico da pontuação |
| RF33 | Nome do jogador e associação ao resultado |
| RF34 / RNF01 | Repositório e ranking persistido em SQLite |
| RF37 | Elegibilidade e concessão das duas evoluções especiais |
| RF39 | Passiva única de cada personagem |
| RNF03 | Testes de XP, nível e pontuação, além das demais regras da frente |

### 2.2 Responsabilidade compartilhada

| Fluxo | Frente B | Integração externa |
| --- | --- | --- |
| Fim da partida | Mantém resumo e calcula pontuação | A publica morte; C confirma boss; D apresenta |
| Level up | Detecta, gera opções e aplica escolha | A pausa; D apresenta e coleta a escolha |
| Armas | Decide aquisição, upgrade e evolução | A mantém inventário e executa os comandos |
| Inimigo derrotado | Concede XP e contabiliza morte | A publica `EnemyKilled`; C fornece tipo/faixa de XP |
| Baú | Aplica as recompensas concedidas | C cria/abre; D apresenta feedback |
| Item coletado | Aplica cura, passivo ou bônus | C cria o drop; D apresenta feedback |
| Ranking | Persiste e consulta resultados | D solicita nome e desenha a lista |

### 2.3 Fora do escopo

- cálculo de dano, cooldown, mira e execução de armas — Frente A;
- tipos, IA, spawn, boss, ondas, baús e tabelas de drops — Frente C;
- menus, HUD, tela de level up, entrada, áudio e renderização — Frente D;
- balanceamento final dos valores provisórios — equipe inteira.

---

## 3. Base já disponível

- `Health`, `Stats`, `StatModifier`, `StatResolver` e eventos de combate da Frente A;
- `CombatInventory` com dois slots;
- `WeaponProgressionService` para aplicar/remover modificadores e evoluir armas;
- loaders de `weapons.json`, `upgrades.json` e `evolutions.json`;
- `EnemyKilled` e `PlayerDied` no `EventBus`;
- `config/characters.json` e `config/passives.json`;
- migration `database/migrations/001_create_scores.sql`.

Esses componentes devem ser reutilizados. Não criar um segundo sistema de atributos, armas,
eventos ou cooldowns.

---

## 4. Entregas detalhadas

### B1 — personagens e criação do jogador

**Objetivo:** materializar os dois personagens obrigatórios.

**Produzir:**

- `CharacterDefinition`, catálogo, loader e validação de `characters.json`;
- entidade/composição real do jogador usando as capacidades da Frente A;
- factory ou serviço de criação por ID;
- Steve: 20 HP, regeneração 1/30 s, Stone Sword e passiva de dano;
- Alex: 16 HP, regeneração 1/15 s, Bow e passiva de cooldown;
- estado de atributos base separado dos modificadores;
- erros com arquivo, personagem e campo;
- testes dos valores, referências e campos desconhecidos.

**Não produzir:** tela de seleção ou sprites.

**Aceite:** dado um ID válido, a partida recebe um jogador com vida, regeneração, arma e
passiva corretos; IDs/configurações inválidos falham antes da partida.

**Branch sugerida:** `codex/frente-b-b1-personagens`.

### B2 — regeneração e atributos do jogador

**Objetivo:** atualizar atributos temporais sem depender do FPS.

**Produzir:**

- sistema de regeneração pelo tempo lógico;
- cura limitada à vida máxima;
- aplicação de velocidade, vida máxima e raio de coleta;
- política explícita para alterar vida máxima;
- pausa congelando regeneração e durações;
- efeitos com origem rastreável;
- testes em 30/60/144/240 FPS e quadros irregulares.

**Aceite:** Steve e Alex regeneram exatamente nos intervalos configurados, sem ultrapassar a
vida máxima e sem avançar durante pausa.

**Branch sugerida:** `codex/frente-b-b2-atributos-regeneracao`.

### B3 — experiência e nível

**Objetivo:** atender RF11, RF12 e RF13.

**Produzir:**

- estado de XP atual, total e nível;
- consumo de `EnemyKilled` sem duplicação;
- resolução do XP a partir dos metadados da C, com RNG injetado para faixas;
- fórmula de XP necessário: `2n + 7`, `5n - 38`, `9n - 158`;
- suporte a múltiplos níveis em um único ganho;
- evento `LevelUp` imutável;
- eventual pickup/gema de XP e raio de coleta, se mantidos na composição real;
- testes obrigatórios de ganho de XP e evolução de nível.

**Aceite:** a mesma sequência de mortes e seed produz a mesma progressão, incluindo as
fronteiras dos níveis 15/16 e 30/31.

**Branch sugerida:** `codex/frente-b-b3-xp-niveis`.

### B4 — itens passivos e passivas de personagem

**Objetivo:** atender RF21 e RF39 usando o pipeline de modificadores existente.

**Produzir:**

- loader e catálogo de `passives.json`;
- inventário e nível de passivos;
- limites máximos e prevenção de concessão inválida;
- Sharpness, Gunpowder, Feather, Golden Apple, Clock e Magnet;
- passiva de Steve nos marcos configurados;
- passiva de Alex a cada quatro níveis;
- origens únicas por nível/concessão;
- integração de efeitos de arma com `WeaponProgressionService`;
- aplicação de efeitos do jogador sem inventar atributos ausentes;
- testes de acumulação, máximos, marcos pulados e atomicidade.

**Aceite:** cada efeito é aplicado uma vez, na ordem definida, e pode ser rastreado pela sua
origem sem recalcular percentuais sobre resultados anteriores.

**Branch sugerida:** `codex/frente-b-b4-passivos`.

### B5 — escolhas de level up e inventário

**Objetivo:** preparar as três opções exigidas pelo RF14.

**Produzir:**

- gerador determinístico de opções elegíveis;
- opção de nova arma, melhoria e item passivo;
- filtros de nível máximo, duplicação e inventário cheio;
- comandos imutáveis para a escolha;
- aplicação atômica da escolha;
- fluxo de substituição quando os dois slots estiverem ocupados;
- solicitação de pausa/retomada para a camada Application;
- testes com poucas opções, nenhuma opção e seed repetida.

**Não produzir:** layout ou navegação da tela.

**Aceite:** a D recebe opções válidas e a escolha confirmada altera o estado uma única vez.

**Branch sugerida:** `codex/frente-b-b5-level-up`.

### B6 — upgrades e evoluções especiais

**Objetivo:** decidir e conceder RF19/RF37 sem duplicar a execução da Frente A.

**Produzir:**

- nível individual de cada arma;
- escolha do upgrade correspondente;
- comandos para `WeaponProgressionService`;
- verificação de arma/passivo/nível para Enchanted Stone Sword e Mega TNT;
- concessão única e atualização do estado de progressão;
- tratamento de arma ausente ou evolução incompatível;
- testes com os 16 upgrades e as duas combinações reais.

**Aceite:** uma concessão autorizada modifica o próximo ataque via A; uma combinação inválida
não muda inventário, modificadores ou cooldown.

**Branch sugerida:** `codex/frente-b-b6-upgrades-evolucoes`.

### B7 — itens, cura e bônus temporários

**Objetivo:** aplicar recompensas criadas pela Frente C.

**Produzir:**

- consumo único de pickups;
- Steak curando sem exceder a vida máxima;
- itens especiais conforme contrato acordado;
- bônus temporários com início, duração e expiração pelo tempo lógico;
- publicação/consumo final de `ItemPicked`;
- integração com Chuva de XP e Tesouro;
- testes de vida cheia, expiração, pausa e repetição.

**Aceite:** coletar um item produz exatamente um efeito e sua expiração restaura o estado sem
remover modificadores de outras origens.

**Branch sugerida:** `codex/frente-b-b7-itens-bonus`.

### B8 — estado, fim e resumo da partida

**Objetivo:** concentrar dados necessários a RF09, RF30 e RF32.

**Produzir:**

- estado da partida: personagem, nível, XP, mortes, tempo, seed e resultado;
- registro do tempo lógico, excluindo pausas;
- consumo de `PlayerDied`;
- comando/evento de vitória recebido da C;
- encerramento idempotente;
- `RunSummary` imutável;
- testes de vitória, derrota, pausa e chamadas repetidas.

**Aceite:** a partida produz um resumo completo e não aceita novas alterações depois de
encerrada.

**Branch sugerida:** `codex/frente-b-b8-resumo-partida`.

### B9 — pontuação e nome do jogador

**Objetivo:** atender RF32 e RF33.

**Produzir:**

- `ScoreCalculator`;
- fórmula versionada: nível × 100 + mortes × 10 + tempo × 2 + vitória × 500;
- validação e normalização do nome;
- associação do nome ao `RunSummary`;
- testes obrigatórios de pontuação, limites e determinismo.

**Aceite:** o mesmo resumo produz a mesma pontuação inteira e nomes vazios são rejeitados.

**Branch sugerida:** `codex/frente-b-b9-pontuacao`.

### B10 — SQLite e ranking

**Objetivo:** atender RF34 e RNF01.

**Produzir:**

- `ScoreRepositoryInterface` no domínio;
- conexão PDO SQLite e migrator;
- `SqliteScoreRepository`;
- aplicação ordenada e idempotente das migrations;
- salvar resultado e consultar ranking;
- desempate: pontuação desc., tempo asc., partida mais antiga;
- banco local fora do Git;
- testes com arquivo temporário e reabertura do banco.

**Aceite:** fechar e reabrir o processo preserva o ranking na ordem definida.

**Branch sugerida:** `codex/frente-b-b10-ranking-sqlite`.

### B11 — integração e regressão

**Objetivo:** conectar a B às frentes A/C/D.

**Produzir:**

- composição headless do fluxo morte → XP → nível → opção → aplicação;
- integração baú/drop → recompensa;
- integração fim → resumo → pontuação → persistência;
- contratos finais de `LevelUp`, `ChestOpened` e `ItemPicked` com C/D;
- suíte de regressão sem janela;
- documentação de APIs para D e C.

**Aceite:** fluxo completo reproduzível, suíte verde e nenhuma classe de domínio dependente de
Raylib, FFI, SQLite concreto ou tela.

**Branch sugerida:** `codex/frente-b-b11-integracao`.

---

## 5. Ordem recomendada

```text
B1 Personagens
 ├── B2 Atributos/regeneração
 └── B3 XP/níveis
      ├── B4 Passivos
      └── B5 Level up/inventário
           ├── B6 Upgrades/evoluções
           └── B7 Itens/bônus
B8 Resumo da partida
 └── B9 Pontuação
      └── B10 SQLite/ranking
Todos ──────────────── B11 Integração/regressão
```

---

## 6. Matriz de testes obrigatórios

| Área | Casos mínimos |
| --- | --- |
| Personagens | Steve/Alex, vida, arma, passiva, configuração inválida |
| Regeneração | intervalo, vida cheia, pausa, determinismo |
| XP | ganho, faixa por seed, morte duplicada, múltiplas mortes |
| Nível | fórmulas e fronteiras 15/16/30/31, múltiplos níveis |
| Passivos | máximos, origens, acúmulo, atributo incompatível |
| Level up | três tipos, elegibilidade, inventário cheio, ausência de opção |
| Evoluções | duas combinações, requisitos insuficientes, concessão única |
| Itens | cura, pickup único, bônus e expiração |
| Partida | tempo, pausa, vitória, derrota, encerramento idempotente |
| Pontuação | fórmula, vitória/derrota, inválidos e determinismo |
| Ranking | persistência, ordenação, empate, migration idempotente |

---

## 7. Decisões que precisam ser fechadas

- política de vida atual quando `vidaMaxima` aumenta;
- se XP é concedido diretamente ou coletado por gemas físicas;
- raio base de coleta e velocidade base do jogador;
- regras de substituição quando os dois slots estão ocupados;
- payload final de `LevelUp`, `ChestOpened` e `ItemPicked`;
- definição dos itens especiais;
- momento do cadastro do nome;
- relação entre morte do boss, baú e encerramento por vitória.

Registrar cada decisão em `docs/CONTRATOS.md` antes de acoplar duas frentes.

---

## 8. Definição de pronto

Uma entrega B está pronta somente quando:

- [ ] respeita o escopo e não implementa apresentação ou conteúdo da C;
- [ ] usa as APIs da Frente A em vez de duplicar regras;
- [ ] valida dados de configuração com mensagens úteis;
- [ ] possui testes unitários e de integração proporcionais ao risco;
- [ ] funciona sem janela, Raylib ou FFI;
- [ ] `composer check` e `git diff --check` passam;
- [ ] documentação e contratos afetados foram atualizados;
- [ ] nenhum `.sqlite`, segredo ou arquivo de `tmp/` entrou no commit;
- [ ] branch foi enviada e revisada por outra frente.

---

## 9. Próxima ação

Começar pela B1. Antes de codificar, confirmar com C o formato dos metadados de XP e com D o
objeto que representa a escolha de personagem. Não esperar telas para testar: todos os fluxos
da B devem possuir composição headless.
