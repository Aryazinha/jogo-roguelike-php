# Plano de trabalho — Frente A: núcleo e combate

| Campo | Definição |
| --- | --- |
| Responsável | Thales |
| Escopo | Loop, entrada abstrata, movimentação, colisão, dano, armas e desempenho |
| Duração de referência | 8 semanas do projeto |
| Estado | Base do domínio e A1–A8 concluídas; A9 e A10 não iniciadas |

Este plano transforma o escopo da Frente A em entregas verificáveis. Ele não altera o
enunciado nem o plano geral: apenas organiza o trabalho de uma frente. Em caso de conflito,
`docs/enunciado/` e `docs/PLANO.md` continuam sendo as fontes de verdade.

---

## 1. Resultado esperado

Ao final, a Frente A deve fornecer o motor lógico que permite ao jogo:

1. atualizar o mundo com passo de tempo fixo;
2. receber uma entrada abstrata e movimentar o jogador;
3. localizar entidades próximas e detectar colisões;
4. aplicar dano e identificar mortes;
5. executar automaticamente quatro armas, cada uma com sua forma de mira;
6. respeitar cooldowns, alcance, área e projéteis;
7. funcionar sem Raylib para testes e simulações;
8. manter desempenho estável com muitos inimigos.

A Frente A não desenha a tela, não toca áudio, não calcula XP, não salva ranking e não define
o conteúdo dos inimigos. Ela publica resultados e eventos para que as outras frentes façam
essas tarefas.

---

## 2. Escopo confirmado pelos requisitos

### 2.1 Responsabilidade direta

| Requisito | Entrega da Frente A |
| --- | --- |
| RF03 | Base do loop e inicialização dos sistemas de mundo; integração compartilhada |
| RF04 | Movimentação por entrada abstrata de teclado ou mouse |
| RF06 | Ataque automático conforme o cooldown |
| RF07 | Quatro estratégias de direcionamento de armas |
| RF08 | Colisões, dano arma→inimigo, dano inimigo→jogador e morte em HP zero |
| RF18 | Armas com comportamento, dano e cooldown próprios |
| RF20 | Uma instância de cooldown por arma |
| RNF02 | Domínio modular, sem Raylib, FFI ou I/O |
| RNF03 | Testes obrigatórios de dano e cooldown; testes dos demais sistemas da frente |
| RNF06 | Passo fixo, grade espacial, pool de objetos e metas de desempenho |

### 2.2 Responsabilidade compartilhada

| Requisito | Frente A entrega | Outra frente entrega |
| --- | --- | --- |
| RF09 | Detecta morte e publica resultado de combate | C informa morte do boss; B/D encerram e apresentam a partida |
| RF11 | Publica `EnemyKilled` | B converte a morte em XP |
| RF14 | Permite pausar e retomar a atualização | B/D controlam escolha e tela de level up |
| RF15 | Mantém armas equipadas e respeita o limite acordado | B controla aquisição e seleção |
| RF16–17 | Executa arma, vida e efeitos recebidos pelo jogador | B define personagens, regeneração e passivas |
| RF19 | Aplica modificadores aos atributos das armas | B decide e concede upgrades |
| RF30 | Fornece tempo confiável do loop | B registra o tempo na partida |
| RF32 | Publica mortes e estado final necessários à pontuação | B calcula e persiste a pontuação |
| RF37 | Executa o comportamento da arma evoluída | B verifica combinações e concede a evolução |
| RF38 | Colisão e dano contra elementos quebráveis | C define elementos e tabelas de drops |
| RNF05 | Publica eventos de combate | D consome para áudio e efeitos; B consome para estatísticas |

### 2.3 Fora do escopo principal

- menus, HUD, sprites, áudio e efeitos visuais — Frente D;
- `RaylibBinding`, `RaylibRenderer`, `RaylibAudio` e entrada concreta — Frente D;
- XP, níveis, personagens, passivas, upgrades concedidos, pontuação e ranking — Frente B;
- tipos e comportamento de inimigos, boss, ondas, baús e eventos aleatórios — Frente C;
- banco SQLite — Frente B;
- balanceamento final — equipe inteira.

Se uma entrega da Frente A precisar dessas funções, deve depender de um contrato ou objeto
de teste, e não implementar a responsabilidade da outra frente.

---

## 3. Estado atual

### Concluído e integrado ao `main`

- [x] `Vector2` imutável;
- [x] `Health` com dano, cura e limites de vida;
- [x] `Cooldown` com disponibilidade e passagem de tempo;
- [x] `Entity` com identidade, posição, raio e atividade;
- [x] testes unitários dessas classes;
- [x] configuração de PHPUnit, PHPStan e Pint;
- [x] documentação inicial em `docs/FRONTE_A.md`;
- [x] A1 ratificada em 09/10/2026 pelas quatro frentes, conforme confirmação humana;
- [x] A2: loop fixo, acumulador, limite de atraso, pausa e relógio injetável;
- [x] testes de independência do FPS e documentação em `docs/LOOP_FIXO.md`;
- [x] A3: entrada abstrata, `Movable`, olhar explícito e movimento com limites configuráveis;
- [x] testes de movimento e integração com pausa e 30/60/144/240 FPS e quadros irregulares;
- [x] documentação de composição e responsabilidades em `docs/MOVIMENTACAO.md`;
- [x] A4: `Collidable`, colisão circular, grade de 64 px, consultas e métricas;
- [x] cenário de 300 entidades com médias abaixo de 40 candidatos por entidade;
- [x] geometria, integração e decisões técnicas em `docs/COLISAO_GRADE_ESPACIAL.md`;
- [x] A5: `Damageable`, dano imutável, dano efetivo, desativação e morte única por ID;
- [x] barramento síncrono por partida e quatro eventos de combate ratificados;
- [x] testes de dano, eventos, isolamento, falhas de listeners e integração com A4/A2;
- [x] APIs, limites e responsabilidades em `docs/DANO_MORTE_EVENTOS.md`;
- [x] A6: definições imutáveis, carga validada das quatro armas e inventário padrão de dois slots;
- [x] cooldown próprio, tentativa automática aceita/recusada e substituição sem estado compartilhado;
- [x] testes de pausa/retomada e sequência de ataques em 30/60/120/144/240 FPS;
- [x] APIs e decisões da A6 em `docs/ARMAS_INVENTARIO_COMBATE.md`;
- [x] A7: quatro miras, visão espacial imutável e seed externa obrigatória;
- [x] espada, arco, TNT e tocha com colisão, dano A5 e deduplicação por ataque;
- [x] testes de estados/eventos por passo em 30/60/144/240 FPS, quadros irregulares e pausa;
- [x] decisões técnicas e integração por portas em `docs/MIRAS_ARMAS.md`;
- [x] A8: atributos resolvidos da base com FLAT/PERCENT e efeitos removíveis por origem;
- [x] comandos externos de upgrade/evolução, cooldown proporcional e snapshots preservados;
- [x] loaders dos upgrades e evoluções reais e execução das duas armas evoluídas pela A7;
- [x] atomicidade, determinismo e limites em `docs/MODIFICADORES_EVOLUCOES.md`;
- [x] `composer check` verde na base e nas entregas integradas.

### Contratos confirmados na A1

A aprovação em 09/10/2026 foi informada por humano e registrada em
[CONTRATOS.md](CONTRATOS.md): Thales (A), [NOME B] (B), [NOME C] (C), [NOME D] (D).
Os nomes completos B/C/D continuam pendentes; os marcadores foram mantidos literalmente.

- [x] assinaturas e unidades definidas da A1 congeladas;
- [x] `InputInterface` acordada com a Frente D;
- [x] `TargetingStrategy` e `StatModifier` acordados com a Frente B;
- [x] eventos de combate e responsabilidades do `EventBus` definidos;
- [x] `WorldView` acordada com a Frente C;
- [x] formato e regras definidas de validação de `config/weapons.json` aprovados.

**Aceite da A1 concluído; A2 liberada.** A aprovação não resolve os pontos explicitamente
indefinidos em `CONTRATOS.md` §9. As APIs documentadas ainda precisam ser implementadas nas
respectivas entregas.

### Decisões de implementação ainda necessárias

Estes pontos não estão definidos pelo enunciado e precisam ser registrados como decisões da
equipe, sem tratá-los como requisitos novos:

- teclado ou mouse como controle inicial e como determinar a direção do olhar;
- velocidade base do jogador e limites do mapa;
- frequência do dano por contato e eventual intervalo de invulnerabilidade;
- integração da C com as portas de leitura e resolução de alvos da A7;
- seed da partida fornecida pela composição (A7 exige seed explícita e reproduzível);
- integração da pausa nas telas B/D e relógio da partida (A6 comprova cooldowns congelados quando A2 não executa passos);
- integração da B com comandos da A8 em ponto seguro, sem alterar vida/coleta de jogador;
- comportamento quando o pool de projéteis estiver cheio.

---

## 4. Definição de pronto

Uma tarefa da Frente A só está pronta quando:

- [ ] atende ao requisito e aos critérios de aceite deste plano;
- [ ] mantém o domínio sem FFI, Raylib ou I/O;
- [ ] inclui testes no mesmo commit da regra;
- [ ] nomes de classes e métodos estão em inglês;
- [ ] documentação e comentários estão em português;
- [ ] valores com `_fonte: "enunciado"` não foram alterados;
- [ ] `composer check` passa;
- [ ] `git diff --check` passa;
- [ ] contratos ou documentação afetados foram atualizados;
- [ ] a integração com outras frentes usa interfaces, não implementações concretas;
- [ ] a branch foi enviada e integrada sem misturar outra entrega.

---

## 5. Cronograma sugerido

As estimativas abaixo são de planejamento, não requisitos. Replanejar se alguma dependência
de outra frente atrasar, sem retirar requisito obrigatório.

| Semana | Entrega principal | Requisitos | Resultado verificável |
| --- | --- | --- | --- |
| 1 | Base do domínio e contratos | RNF02, RF20 | Objetos fundamentais testados e contratos congelados |
| 2 | Loop fixo, entrada e movimento | RF03, RF04, RNF06 | Jogador se move de forma independente do FPS |
| 3 | Colisão e grade espacial | RF08, RF38, RNF06 | Consultas de vizinhança e colisões corretas |
| 4 | Dano, morte e eventos | RF08, RF09, RF11, RNF05 | Combate publica eventos sem conhecer XP, HUD ou áudio |
| 5 | Núcleo de armas e inventário | RF06, RF15, RF18, RF20 | Armas equipadas atacam automaticamente com cooldown próprio |
| 6 | Quatro miras e quatro armas | RF07, RF18 | Espada, arco, TNT e tocha funcionam sem renderização |
| 7 | Upgrades, evoluções, pool e simulação | RF19, RF37, RNF03, RNF06 | Modificadores aplicados e simulação sem janela executável |
| 8 | Integração, desempenho e regressão | Todos acima | Sistemas integrados, metas medidas e suíte final verde |

Com a base e A1–A8 concluídas, as demais entregas permanecem separadas.
A execução da A8 termina após sua integração; A9 exige uma próxima tarefa.

---

## 6. Entregas detalhadas

### Entrega A1 — contratos da Frente A

**Objetivo:** impedir incompatibilidades entre as quatro frentes.

**Produzir ou ratificar:**

- assinatura e semântica de `Entity`, `Vector2`, `Health`, `Cooldown` e `Stats`;
- interfaces pequenas `Movable`, `Collidable` e `Damageable`, se aprovadas;
- `InputInterface` e o objeto de estado de entrada;
- `TargetingStrategy`;
- formato de `StatModifier` e ordem base → `FLAT` → `PERCENT`;
- eventos `PlayerDamaged`, `EnemyHit`, `EnemyKilled` e `PlayerDied`;
- dados mínimos de leitura da `WorldView`;
- esquema obrigatório de `config/weapons.json`.

**Dependências:** reunião com B, C e D.

**Critério de aceite:** `docs/CONTRATOS.md` preenchido com data, participantes e assinaturas.

**Estado:** concluída; ratificação humana das quatro frentes registrada em 09/10/2026.

**Branch sugerida:** `frente-a/contratos-do-dominio`.

### Entrega A2 — loop de tempo fixo

**Objetivo:** criar uma atualização determinística e independente da velocidade da máquina.

**Produzir:**

- `GameLoop` ou serviço equivalente;
- passo fixo de `1/60 s`;
- acumulador de tempo;
- limite de atualizações acumuladas para evitar espiral de atraso;
- pausa e retomada;
- separação entre `update` e `render`;
- relógio injetável para testes;
- ordem documentada de atualização dos sistemas.

**Não inclui:** janela, desenho ou leitura real do Raylib.

**Critério de aceite:** a mesma sequência de entradas produz o mesmo estado com taxas de
quadros diferentes.

**Implementação:** `src/Core/GameLoop.php`, `ClockInterface` e `MonotonicClock`, com testes
de 30/60/144/240 FPS e quadros irregulares. API, decisões técnicas e ordem de atualização
documentadas em [LOOP_FIXO.md](LOOP_FIXO.md). A composição com a A3 está em
[MOVIMENTACAO.md](MOVIMENTACAO.md).

**Branch sugerida:** `frente-a/game-loop-fixo`.

### Entrega A3 — jogador e movimentação

**Objetivo:** atender ao RF04 usando apenas entrada abstrata.

**Produzir:**

- entidade ou componente de movimento do jogador;
- estado de entrada com eixos horizontal e vertical;
- direção atual do olhar;
- `MovementSystem`;
- normalização do movimento diagonal;
- deslocamento por velocidade × `dt`;
- limites do mapa;
- entrada programada para testes.

**Dependências:** `InputInterface` da A1; atributos do jogador fornecidos pela Frente B.

**Critério de aceite:** movimento diagonal não é mais rápido e o resultado independe do FPS.

**Estado:** concluída; integração com o índice da A4 documentada em
[COLISAO_GRADE_ESPACIAL.md](COLISAO_GRADE_ESPACIAL.md).

**Implementação:** `MovementSystem` usa `Movable`, `InputState` e `Facing`, com velocidade
e atividade fornecidas por passo e `MapBounds` fornecido pelo chamador. `Entity` implementa
`Movable` sem mudar seus métodos; `InputInterface` e `ScriptedInput` seguem a A1. Não foi
necessário criar `Player` concreto, atributos, conteúdo ou entrada real de B/C/D.

**Verificação:** percurso e olhar idênticos após 120 passos em 30/60/144/240 FPS e quadros
irregulares; diagonal, limites, valores inválidos, inatividade e pausa testados. Suíte completa
com 118 testes e 3.526 asserções; Pint, PHPStan nível 9 e `git diff --check` aprovados.
API, unidades, decisões técnicas e integração documentadas em [MOVIMENTACAO.md](MOVIMENTACAO.md).
Velocidade e tamanho reais do mapa e política de olhar continuam pendentes da equipe.

**Branch sugerida:** `frente-a/movimentacao-jogador`.

### Entrega A4 — colisão e grade espacial

**Objetivo:** detectar colisões sem busca todos-contra-todos.

**Produzir:**

- `CollisionSystem` para círculos;
- testes de separação, sobreposição e tangência;
- ignorar entidades inativas;
- `SpatialHash` com células de 64 px;
- inserção, reconstrução e consulta das nove células vizinhas;
- consulta por raio para ataques em área e IA;
- métricas de quantidade de candidatos por consulta.

**Integrações:** inimigos da C, elementos quebráveis da C e pickups da B/C.

**Critério de aceite:** consultas devolvem vizinhos corretos e ficam abaixo da meta de 40
verificações por entidade no cenário de referência.

**Estado:** concluída; seus resultados abaixo registram a verificação da A4.

**Implementação:** `Collidable` materializada com as assinaturas da A1, `Entity` compatível,
`CollisionSystem` para círculos e `SpatialHash` com células de 64 px. A grade registra o
retângulo envolvente em múltiplas células, filtra inativos, rejeita IDs duplicados e devolve
resultados sem repetição em ordem por ID, com métricas por consulta. A consulta por raio
confirma interseção circular; o contrato por centros de `WorldView` permanece preservado.

**Convenção geométrica:** tangência conta como interseção, sem ratificar consequências
de gameplay ou implementar dano, eventos, resposta física ou conteúdo de B/C/D.

**Verificação:** 300 entidades falsas numa malha reproduzível de 20 × 15, com médias de
29,24 candidatos nas nove células e 13,1867 na consulta circular, máximos de 35 e 15.
Testes adicionais comparam a grade a um oráculo independente e integram movimento seguido
de reconstrução no passo fixo. Suíte completa: 181 testes e 6.161 asserções, Pint e PHPStan
nível 9 sem erros; `git diff --check` passou. APIs, limitações numéricas e orçamento técnico
configurável documentados em [COLISAO_GRADE_ESPACIAL.md](COLISAO_GRADE_ESPACIAL.md).

**Branch sugerida:** `frente-a/colisao-grade-espacial`.

### Entrega A5 — dano, morte e eventos

**Objetivo:** atender ao RF08 sem acoplar combate a progressão ou apresentação.

**Produzir:**

- `DamageSystem`;
- representação da origem e quantidade de dano;
- arma/projétil → inimigo;
- inimigo/ataque → jogador;
- desativação e morte em HP zero;
- garantia de que uma morte é processada uma vez;
- publicação de `EnemyHit`, `EnemyKilled`, `PlayerDamaged` e `PlayerDied`;
- testes unitários obrigatórios de dano.

**Integrações:** B escuta mortes para XP/pontuação; D escuta eventos para feedback; C fornece
os inimigos.

**Critério de aceite:** dano e morte funcionam sem listeners e cada evento contém dados
suficientes para as outras frentes.

**Estado:** concluída. `DamageSystem` usa `Entity&Damageable` sem alterar a capacidade
ratificada; `Damage` e os quatro eventos são finais readonly. Dano efetivo, overkill,
desativação antes da morte, morte única por ID e publicação sem listeners foram comprovados.
`EventBus` respeita classe exata, ordem e propagação de erros, com instâncias por partida.
127 novos cenários; suíte com 308 testes e 6.559 asserções aprovada, Pint e PHPStan nível 9
sem erros; `git diff --check` passou. [DANO_MORTE_EVENTOS.md](DANO_MORTE_EVENTOS.md) documenta
APIs e limitações. Na A5, nenhuma funcionalidade de B/C/D ou da A6 foi implementada.

**Branch sugerida:** `frente-a/dano-e-eventos`.

### Entrega A6 — núcleo de armas e inventário de combate

**Objetivo:** atender a RF06, RF15, RF18 e RF20.

**Produzir:**

- `WeaponDefinition` imutável;
- estado de execução de `Weapon`;
- mapeamento validado dos atributos necessários de `weapons.json` para `WeaponDefinition`;
- cooldown próprio por arma;
- atualização e disparo automáticos;
- coleção de armas equipadas;
- limite inicial de duas armas;
- adição, consulta e substituição de arma;
- atributos de dano, cooldown, alcance, área, projéteis e velocidade;
- testes obrigatórios de cooldowns independentes.

**Integração:** B concede armas e upgrades; A mantém e executa o estado de combate.

**Critério de aceite:** duas armas podem atualizar simultaneamente sem compartilhar cooldown.

**Estado:** concluída. Definições finais readonly e catálogo imutável; carga em `Core/Config`
valida os quatro registros sem alterar o JSON. Cada arma cria seu próprio cooldown, e uma
porta mínima controla a aceitação da tentativa sem definir ausência de alvo. Inventário
padrão de dois slots com regras explícitas para consulta, duplicação e substituição.
234 novos cenários; suíte com 542 testes e 7.590 asserções, Pint e PHPStan nível 9 aprovados.
Integração com FFI desabilitada: 21 testes e 2.686 asserções. Pausa e sequência de ataques
iguais em cinco taxas de quadros comprovadas. Documentação e limites em
[ARMAS_INVENTARIO_COMBATE.md](ARMAS_INVENTARIO_COMBATE.md). A7 está descrita abaixo.

**Branch sugerida:** `frente-a/armas-e-cooldowns`.

### Entrega A7 — miras e quatro armas

**Objetivo:** atender ao mínimo de quatro armas do RF07.

**Produzir:**

| Arma | Mira | Execução de combate |
| --- | --- | --- |
| Stone Sword | direção do olhar | arco/meia-lua com alcance e ângulo |
| Bow and Arrow | inimigo mais próximo | projétil com direção, alcance e velocidade |
| TNT | inimigo aleatório | carga com atraso e explosão em área |
| Torch | área ao redor | pulso radial sem alvo individual |

Também produzir:

- uma implementação de `TargetingStrategy` para cada comportamento;
- seleção apenas de alvos vivos e válidos;
- comportamento definido quando não houver alvo;
- prevenção de dano duplicado pelo mesmo ataque quando aplicável;
- testes unitários de mira e execução das quatro armas.

**Dependências:** A4, A5 e A6; consulta de inimigos acordada com a Frente C.

**Critério de aceite:** cada arma executa automaticamente seu comportamento com os valores de
configuração e sem abrir janela.

**Estado:** concluída. Contratos ratificados materializados; quatro estratégias, RNG com seed
externa, visão por centros e porta separada de alvos mutáveis. Espada e pulso usam geometria
inclusiva; arco usa colisão de segmento e círculo e primeiro impacto; TNT usa snapshot e atraso
lógico. Integração com A6/A5 preservada, cooldowns individuais e danos únicos por ataque.
93 novos cenários; 635 testes e 8.061 asserções, Pint e PHPStan nível 9 aprovados, incluindo
suíte completa com FFI desabilitado. Estados e eventos iguais em 30/60/144/240 FPS e quadros
irregulares, com pausa comprovada. [MIRAS_ARMAS.md](MIRAS_ARMAS.md) documenta decisões técnicas
sem nova ratificação humana. A8 está descrita abaixo; A9 e A10 não iniciadas.

**Branch sugerida:** `frente-a/miras-e-armas`.

### Entrega A8 — modificadores e evoluções

**Objetivo:** permitir que a progressão da Frente B altere o combate sem duplicar regras.

**Produzir:**

- aplicação do valor final de `Stats` e `StatModifier` às armas;
- suporte a dano, cooldown, alcance, projéteis, área e velocidade;
- atualização segura do cooldown quando seu valor for modificado;
- substituição de uma arma pela versão evoluída;
- execução da Enchanted Stone Sword e Mega TNT;
- testes de integração com dados de `upgrades.json` e `evolutions.json`.

**Não produzir:** sorteio de opções, regras de elegibilidade ou tela de level up; isso pertence
à Frente B/D.

**Critério de aceite:** um comando da B consegue aplicar upgrade/evolução e o próximo ataque
usa os valores resultantes.

**Estado:** concluída. `Stats`/`StatModifier`/`ModifierType` materializados; resolução
determinística da base, definição efetiva e remoção por source. Comandos por slot são atômicos,
preservam proporção do cooldown e rejeitam frações/valores inválidos. Evoluções mantêm executor,
descartam efeitos antigos e conservam snapshots de ataques existentes. Loaders validam os
16 upgrades e duas evoluções sem editar JSONs ou decidir elegibilidade. Suíte completa:
796 testes, 8.722 asserções, Pint e PHPStan nível 9 aprovados. Estados/eventos iguais em
30/60/144/240 FPS e quadros irregulares. Decisões técnicas e APIs em
[MODIFICADORES_EVOLUCOES.md](MODIFICADORES_EVOLUCOES.md). A9 e A10 não iniciadas.

**Branch sugerida:** `frente-a/modificadores-de-armas`.

### Entrega A9 — pool de objetos e simulação sem janela

**Objetivo:** sustentar RNF03 e RNF06.

**Produzir:**

- `ObjectPool` genérico ou especializado;
- pool de projéteis com teto de 200 ativos;
- aquisição, liberação e reinicialização segura;
- comportamento explícito quando o pool estiver cheio;
- integração do loop com entrada e renderização nulas;
- cenário automatizado de combate sem Raylib;
- métricas básicas: entidades, projéteis, colisões e tempo de atualização.

**Integração:** B pode reutilizar o pool para gemas; D pode reutilizá-lo para números de dano.

**Critério de aceite:** objetos são reutilizados sem manter estado antigo e a simulação é
reproduzível.

**Branch sugerida:** `frente-a/object-pool-e-simulacao`.

### Entrega A10 — integração e desempenho

**Objetivo:** demonstrar o RNF06 e eliminar regressões antes da entrega.

**Produzir:**

- integração do loop com Player, inimigos, armas, projéteis e eventos;
- cenário de carga com 300 inimigos e até 200 projéteis;
- medição do tempo de atualização do domínio sem janela;
- validação do orçamento de 16,6 ms por quadro e, com a Frente D, de pelo menos 50 FPS no
  cenário-alvo;
- validação de menos de 40 verificações de colisão por entidade por quadro no cenário-alvo;
- correções de gargalos comprovados por medição;
- suíte de regressão final;
- atualização da documentação técnica.

**Critério de aceite:** metas registradas com máquina, cenário, semente e resultado; toda a
suíte passa.

**Branch sugerida:** `frente-a/integracao-e-desempenho`.

---

## 7. Dependências entre entregas

```text
A1 Contratos
 ├── A2 Loop fixo
 ├── A3 Movimento
 ├── A4 Colisão/grade
 │    └── A5 Dano/eventos
 │         └── A6 Armas/cooldowns
 │              └── A7 Miras/quatro armas
 │                   └── A8 Modificadores/evoluções
 └──────────────────────── A9 Pool/simulação
                            └── A10 Integração/desempenho
```

A2 e A3 podem avançar em paralelo depois de A1. A4 pode começar com entidades de teste sem
esperar os inimigos reais da Frente C. As integrações finais dependem das interfaces, não da
conclusão interna das outras frentes.

---

## 8. Matriz de integração

| Frente | A precisa receber | A deve fornecer |
| --- | --- | --- |
| B — progressão | atributos do jogador, comandos de equipar/melhorar/evoluir | eventos de morte, inventário de combate, aplicação de modificadores |
| C — conteúdo | entidades inimigas, raios, comportamento e elementos quebráveis | movimento/colisão disponíveis, consultas espaciais, dano e eventos |
| D — apresentação | implementação de entrada, estados de pausa, renderer | estado do mundo, direção do olhar e eventos de feedback |

Problemas de integração devem ser resolvidos alterando primeiro o contrato compartilhado e
só depois as implementações.

---

## 9. Plano de testes

| Área | Casos mínimos |
| --- | --- |
| Loop | passo fixo, acúmulo, pausa, limite de atraso e determinismo |
| Movimento | eixos, diagonal, zero, `dt`, limites e pausa |
| Colisão | separado, tangente, sobreposto, inativo, raio e vizinhança |
| Dano | parcial, fatal, zero, morte única e eventos |
| Cooldown | pronto, bloqueado, liberação, reset e independência entre armas |
| Mira | direção, mais próximo, aleatório determinístico, área e ausência de alvo |
| Armas | arco, projétil, explosão, pulso, alcance e dano único |
| Inventário | limite, consulta, adição, substituição e atualização simultânea |
| Modificadores | `FLAT`, `PERCENT`, ordem, cooldown e evolução |
| Pool | aquisição, liberação, reutilização, limpeza e esgotamento |
| Desempenho | 300 inimigos, 200 projéteis e quantidade de candidatos de colisão |

Os testes de dano e cooldown são obrigatórios pelo RNF03. Os demais são necessários para
provar os critérios de aceite desta frente.

---

## 10. Riscos e prevenção

| Risco | Sinal | Prevenção |
| --- | --- | --- |
| Contratos mudarem tarde | frentes criam tipos semelhantes | congelar A1 antes das integrações |
| Acoplamento ao Raylib | `FFI::` ou classes gráficas no domínio | usar interfaces e testes sem janela |
| Colisão lenta | comparações crescem quadraticamente | construir grade espacial antes da integração em massa |
| Cooldown depender do FPS | resultados variam entre máquinas | usar passo fixo e `dt` validado |
| Armas duplicarem regras | cada arma implementa seu próprio ciclo completo | separar mira, definição, execução e cooldown |
| Dano duplicado | mesmo projétil acerta várias vezes indevidamente | registrar consumo/alvos atingidos e testar |
| Integração tardia | cada frente funciona apenas isolada | integrar por contrato a cada semana |
| Escopo da Frente A crescer | A começa a implementar XP, IA ou HUD | consultar a matriz de responsabilidade deste plano |

---

## 11. Checklist semanal

No início da semana:

- [ ] escolher uma entrega e confirmar dependências;
- [ ] atualizar `main` e criar uma branch própria;
- [ ] escrever critérios de aceite e testes esperados;
- [ ] confirmar contratos afetados com as outras frentes.

Antes de integrar:

- [ ] rodar `composer check`;
- [ ] conferir `git diff --check`;
- [ ] executar a simulação relevante;
- [ ] atualizar documentação;
- [ ] registrar requisito coberto no PR;
- [ ] confirmar que nenhum arquivo temporário entrou no commit.

---

## 12. Próxima ação

1. Encerrar a execução após integrar e sincronizar a A8; não iniciar A9 automaticamente.
2. Preservar as pendências explícitas de `docs/CONTRATOS.md` §9 nas entregas futuras.
3. Substituir os marcadores B/C/D no registro da equipe quando os nomes forem informados.

Não começar armas antes de movimento, colisão e dano estarem estáveis: armas dependem dos
três sistemas e implementá-las antes aumenta o retrabalho.

A1 foi aprovada por humanos. A2 até A8 foram implementadas nas suas execuções autorizadas.
Esta execução está limitada à A8; não iniciar A9 ou A10 após sua integração.
