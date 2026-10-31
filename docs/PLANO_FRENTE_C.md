# Plano de trabalho — Frente C: conteúdo e dificuldade

| Campo | Definição |
| --- | --- |
| Responsável | Lara Fernandes |
| Escopo | Inimigos, IA, spawn, boss, ondas, baús, eventos e elementos quebráveis |
| Requisitos principais | RF03, RF05, RF08–RF09, RF11, RF22, RF26–RF29, RF35–RF36, RF38, RNF03 e RNF06 |
| Estado | C1 ([INIMIGOS_ARQUETIPOS.md](INIMIGOS_ARQUETIPOS.md)), C2 ([MUNDO_MAPA.md](MUNDO_MAPA.md)), C3 ([COMPORTAMENTOS_INIMIGOS.md](COMPORTAMENTOS_INIMIGOS.md)), C4 ([DANO_AO_JOGADOR.md](DANO_AO_JOGADOR.md)), C5 ([INIMIGOS_ESPECIAIS.md](INIMIGOS_ESPECIAIS.md)), C6 ([BOSS_WARDEN.md](BOSS_WARDEN.md)), C7 ([ONDAS_SPAWN.md](ONDAS_SPAWN.md)) e C8 ([BAUS.md](BAUS.md)) concluídas; C9 a C11 planejadas |

Este documento organiza o trabalho da Frente C. Ele não substitui o enunciado nem
`docs/PLANO.md`. A Frente C define o conteúdo e decide quando seus comportamentos acontecem;
a Frente A fornece movimento, colisão, consultas espaciais, dano e eventos genéricos.

---

## 1. Resultado esperado

Ao final da Frente C, o jogo deve conseguir:

1. criar o mapa lógico e registrar entidades no mundo;
2. carregar e fabricar inimigos definidos por dados;
3. gerar inimigos progressivamente até o teto acordado;
4. executar perseguição, separação e combate à distância;
5. aplicar dano dos inimigos ao jogador pela infraestrutura da A;
6. executar um boss em fases e reconhecer sua derrota;
7. controlar ondas e escalonamento de dificuldade;
8. executar os três eventos aleatórios planejados;
9. gerar e abrir baús;
10. criar elementos quebráveis e resolver suas tabelas de drops.

A Frente C não calcula XP, não aplica recompensas ao jogador e não desenha entidades. Ela
fornece metadados e eventos para a B e snapshots/estado para a D.

---

## 2. Escopo confirmado

### 2.1 Responsabilidade direta

| Requisito | Entrega da Frente C |
| --- | --- |
| RF03 | Conteúdo lógico do mapa, entidades e pontos válidos de surgimento |
| RF05 | Geração progressiva de inimigos |
| RF22-a | Pelo menos três inimigos comuns com atributos próprios |
| RF22-b | Boss com atributos e comportamento distintos |
| RF26 | Baús após o boss, raridades e recompensas descritas |
| RF28 | Escalonamento de dificuldade por tempo/nível |
| RF29 | Diretor de ondas com intensidades diferentes |
| RF35 | Horda, Chuva de XP e Tesouro |
| RF36 | Slime divisível e Skeleton à distância |
| RF38 | Elementos quebráveis e tabelas de drops |
| RNF03 | Testes obrigatórios de geração de inimigos e regras da frente |
| RNF06 | Uso eficiente da grade espacial e respeito ao teto de inimigos |

### 2.2 Responsabilidade compartilhada

| Fluxo | Frente C | Integração externa |
| --- | --- | --- |
| Movimento/colisão | Decide comportamento e fornece entidades | A move, consulta e detecta colisão |
| Ataque inimigo→jogador | Decide quando/quanto atacar | A aplica dano e publica eventos |
| Morte de inimigo | Fornece tipo, XP e consequências de conteúdo | A confirma morte; B concede XP; D apresenta |
| Boss | Executa fases e confirma derrota | B encerra/resume; D apresenta; A aplica dano |
| Baús | Cria, sorteia raridade e publica abertura | B aplica recompensa; D apresenta |
| Drops | Cria pickups e metadados | B aplica efeito; D apresenta |
| Eventos | Controla início/duração do conteúdo | B reage a XP/recompensa; D apresenta |

### 2.3 Fora do escopo

- implementação de movimento, colisão, dano e armas — Frente A;
- XP, níveis, passivos, aplicação de recompensa e pontuação — Frente B;
- sprites, câmera, HUD, áudio e entrada — Frente D;
- diretor adaptativo — fora do escopo firme enquanto `diretorAdaptativo` estiver desligado;
- balanceamento final — equipe inteira.

---

## 3. Base já disponível

- `Entity`, `Movable`, `Collidable` e `Damageable`;
- `MovementSystem`, `CollisionSystem`, `DamageSystem` e `SpatialHash`;
- `WorldView`, `WorldEntityProvider`, `EnemyTargetResolver` e snapshots imutáveis;
- eventos `EnemyHit`, `EnemyKilled`, `PlayerDamaged` e `PlayerDied`;
- RNG com seed explícita;
- benchmark A10 com 300 alvos sintéticos, que não substitui os inimigos reais;
- `config/enemies.json`, `waves.json`, `chests.json`, `events.json` e `scenery.json`.

Não copiar as entidades sintéticas de benchmark para o jogo. Elas existem apenas para medição.

---

## 4. Entregas detalhadas

### C1 — configuração, arquétipos e fábrica de inimigos

**Objetivo:** transformar `enemies.json` em conteúdo validado.

**Produzir:**

- loader com detecção de chaves duplicadas;
- `EnemyArchetype` imutável;
- catálogo por ID e categoria;
- `EnemyFactory`;
- entidade inimiga real compondo as capacidades da A;
- atributos: vida, dano, XP, nível mínimo, velocidade e raio;
- metadados `enemyTypeId` e `isBoss` para o dano/eventos;
- validação de comportamento e referências;
- diagnósticos com arquivo, ID e campo;
- testes dos registros reais e configurações inválidas.

**Aceite:** todos os inimigos do JSON são criados com dados corretos e nenhuma configuração
inválida chega à partida.

**Branch sugerida:** `codex/frente-c-c1-arquetipos-factory`.

### C2 — mundo, mapa e registro espacial

**Objetivo:** fornecer à composição real o conteúdo que o benchmark A10 simulou.

**Produzir:**

- limites e dimensões reais do mapa;
- posição inicial válida do jogador;
- provider de entidades reais para `SpatialWorldView`;
- resolução por ID para alvos de dano;
- inclusão, atualização e remoção segura de entidades;
- regiões válidas de spawn fora da área imediata do jogador;
- invalidação/`refresh()` da visão após movimento ou mutação;
- testes de IDs únicos, snapshots, remoção e limites.

**Não produzir:** desenho do mapa.

**Aceite:** A consegue consultar e atingir inimigos reais sem conhecer classes concretas da C.

**Branch sugerida:** `codex/frente-c-c2-mundo-mapa`.

### C3 — comportamentos comuns e separação

**Objetivo:** movimentar grupos de inimigos sem sobreposição excessiva.

**Produzir:**

- contrato/implementações de `BehaviorStrategy` conforme `docs/CONTRATOS.md`;
- `Seek` em direção ao jogador;
- `Separation` usando vizinhos da grade espacial;
- combinação normalizada e pesos configuráveis;
- Zombie, Spider e Husk;
- comportamento determinístico por passo fixo;
- atividade/pausa respeitadas;
- testes de direção, velocidade, vizinhança e grupos grandes.

**Aceite:** os três inimigos comuns perseguem corretamente e 50 inimigos de teste não colapsam
todos na mesma posição.

**Branch sugerida:** `codex/frente-c-c3-comportamentos-comuns`.

### C4 — dano ao jogador

**Objetivo:** completar o lado inimigo→jogador de RF08.

**Produzir:**

- detecção de contato/ataque pelos sistemas da A;
- comando de dano com origem correta;
- frequência explícita de dano por contato;
- intervalo de invulnerabilidade acordado;
- prevenção de dano duplicado no mesmo passo;
- suporte a ataques específicos sem alterar `DamageSystem`;
- testes de contato, afastamento, tangência, invulnerabilidade e morte.

**Aceite:** o jogador recebe dano apenas nas condições documentadas e `PlayerDied` é publicado
uma única vez.

**Branch sugerida:** `codex/frente-c-c4-ataques-inimigos`.

### C5 — inimigos especiais

**Objetivo:** atender RF36 com dois comportamentos distintos.

**Produzir:**

- Slime perseguindo o jogador;
- divisão em dois `slime_pequeno` após morte;
- consequência aplicada em ponto seguro, fora da iteração que publicou a morte;
- filhos sem divisão recursiva indevida;
- Skeleton com perseguir/recuar/orbitar por distância e histerese;
- projétil ou ataque lógico do Skeleton, com cooldown próprio;
- testes de divisão única, distâncias e transições.

**Aceite:** os dois especiais têm diferenças funcionais observáveis e determinísticas, não
apenas números diferentes.

**Branch sugerida:** `codex/frente-c-c5-inimigos-especiais`.

### C6 — boss Warden

**Objetivo:** atender RF22-b e sustentar a condição de vitória.

**Produzir:**

- entidade/factory de boss;
- surgimento aos 600 segundos;
- fases `perseguindo`, `telegrafando`, `investindo`, `recuperando`, `invocando` e `enfurecido`;
- direção travada antes da investida;
- invocações ao cruzar 70% e 40% de vida, uma vez por limiar;
- modificadores abaixo de 25%;
- evento/comando inequívoco de boss derrotado;
- testes de cada transição, pausa, saltos de dano e morte única.

**Aceite:** a mesma entrada/seed produz a mesma sequência de fases e a derrota do Warden é
distinguível da morte de inimigo comum.

**Branch sugerida:** `codex/frente-c-c6-boss-warden`.

### C7 — spawn e ondas

**Objetivo:** atender RF05, RF28 e RF29.

**Produzir:**

- loader/catálogo de `waves.json`;
- `WaveDirector` com faixas por tempo;
- filtro por nível mínimo de aparição;
- intervalo e quantidade por spawn;
- escalonamento de vida e dano por nível;
- escolha ponderada/determinística dos tipos;
- posições válidas e reproduzíveis;
- teto de 300 ativos e retenção quando cheio;
- evento `WaveStarted`, se ratificado;
- testes obrigatórios de geração e fronteiras das faixas.

**Aceite:** a população segue a curva configurada, não excede 300 e se repete com a mesma seed.

**Branch sugerida:** `codex/frente-c-c7-spawn-ondas`.

### C8 — baús

**Objetivo:** atender RF26 e fornecer recompensas à B.

**Produzir:**

- loader/catálogo de `chests.json`;
- `ChestFactory` e entidade de baú;
- geração ligada à derrota do boss conforme decisão de fluxo;
- raridades madeira, bronze e ouro;
- sorteio por probabilidades que totalizam 1;
- abertura única;
- evento `ChestOpened` com recompensa imutável;
- testes de limites, seed, duplicação e soma das probabilidades.

**Não produzir:** aplicação de upgrade ou desenho do baú.

**Aceite:** abrir um baú produz exatamente uma recompensa válida e rastreável para a B.

**Branch sugerida:** `codex/frente-c-c8-baus`.

### C9 — eventos aleatórios

**Objetivo:** implementar os três eventos firmes de RF35.

**Produzir:**

- loader/catálogo de `events.json`;
- `EventDirector` com intervalo, peso e duração;
- Horda alterando quantidade/intervalo de spawn;
- Chuva de XP expondo um modificador para a B;
- Tesouro alterando chance de baú;
- início/fim sem sobreposição inválida;
- seed e ordem reproduzíveis;
- testes de pesos, duração, pausa e restauração do estado.

**Aceite:** cada evento modifica apenas seus alvos e encerra sem deixar efeitos residuais.

**Branch sugerida:** `codex/frente-c-c9-eventos-aleatorios`.

### C10 — elementos quebráveis e drops

**Objetivo:** atender RF38 e a origem ambiental de RF27.

**Produzir:**

- loader/catálogo de `scenery.json`;
- entidades Grass, Crate e Ore;
- posicionamento inicial por densidade/seed;
- vida, colisão e bloqueio de movimento;
- integração com dano de armas da A;
- destruição única e evento de quebra;
- tabelas de Steak, XP pequeno, item especial, bônus e nenhum item;
- pickups com identidade e metadados para B/D;
- testes de probabilidades, morte, bloqueio e duplicação.

**Aceite:** um elemento destruído é removido em ponto seguro e gera no máximo um resultado da
tabela configurada.

**Branch sugerida:** `codex/frente-c-c10-cenario-drops`.

### C11 — vitória, integração e regressão

**Objetivo:** conectar conteúdo real a A/B/D.

**Produzir:**

- fluxo spawn → IA → movimento → contato → dano → morte;
- fluxo boss → vitória → baú/encerramento conforme decisão ratificada;
- fluxo quebrável → drop → pickup;
- payloads finais de `ChestOpened`, `ItemPicked` e `WaveStarted`;
- cenário headless com conteúdo real;
- regressão com 300 inimigos e benchmark conjunto com A;
- documentação para renderer e progressão.

**Aceite:** conteúdo real funciona sem janela, é determinístico e preserva as metas de colisão
e desempenho já demonstradas pela A.

**Branch sugerida:** `codex/frente-c-c11-integracao`.

---

## 5. Ordem recomendada

```text
C1 Arquétipos/factory
 └── C2 Mundo/mapa
      ├── C3 Comuns/separação
      │    ├── C4 Dano ao jogador
      │    └── C5 Especiais
      ├── C6 Boss
      └── C7 Spawn/ondas
C6 ── C8 Baús
C7 ── C9 Eventos
C2 ── C10 Quebráveis/drops
Todos ─────────────── C11 Integração/regressão
```

---

## 6. Matriz de testes obrigatórios

| Área | Casos mínimos |
| --- | --- |
| Configuração | todos os IDs reais, campos/referências inválidos e duplicados |
| Factory | comum, especial, boss, atributos e metadados |
| IA | seek, separation, distância, histerese, pausa e determinismo |
| Dano ao jogador | contato, invulnerabilidade, duplicação e morte |
| Slime | divisão única, filhos, ponto seguro e seed |
| Skeleton | aproximação, recuo, órbita, tiro e cooldown |
| Boss | todas as fases, limiares cruzados, invocação e morte |
| Spawn/ondas | faixas, nível mínimo, teto 300 e posições válidas |
| Baús | raridade, probabilidades, abertura única e evento |
| Eventos | peso, duração, efeitos, expiração e pausa |
| Quebráveis | dano, bloqueio, drop, remoção e duplicação |
| Carga | 300 inimigos, consultas espaciais e metas da A preservadas |

---

## 7. Decisões que precisam ser fechadas

- frequência de dano por contato e invulnerabilidade;
- dimensões/limites do mapa e distância mínima de spawn;
- payload final de `ChestOpened`, `ItemPicked`, `WaveStarted` e boss derrotado;
- ordem segura para consequências publicadas durante iteração;
- relação entre morte do boss, geração de baú e vitória imediata;
- definição concreta de `item_especial`;
- se pickups bloqueiam ou não movimento;
- parâmetros de separation;
- diretor adaptativo permanece fora até decisão explícita.

Registrar decisões compartilhadas em `docs/CONTRATOS.md`.

---

## 8. Definição de pronto

Uma entrega C está pronta somente quando:

- [ ] usa movimento, colisão e dano da A sem duplicá-los;
- [ ] não concede XP, upgrade ou cura diretamente;
- [ ] não importa Raylib, renderer ou áudio;
- [ ] usa RNG injetado e seed reproduzível;
- [ ] aplica consequências em ponto seguro;
- [ ] possui testes unitários, integração headless e casos de carga necessários;
- [ ] `composer check`, benchmark relevante e `git diff --check` passam;
- [ ] configurações e documentação foram atualizadas sem alterar valores do enunciado;
- [ ] nenhum arquivo temporário entrou no commit;
- [ ] branch foi enviada e revisada por outra frente.

---

## 9. Próxima ação

Começar pela C1. Antes de implementar C4/C8/C10, ratificar com B os metadados de XP e
recompensa; antes de C11, entregar à D snapshots suficientes para desenhar inimigos, fases do
boss, baús, pickups e elementos quebráveis.
