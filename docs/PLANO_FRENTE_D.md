# Plano de trabalho — Frente D: apresentação

| Campo | Definição |
| --- | --- |
| Responsável | `[NOME D]` |
| Escopo | Raylib/FFI, entrada real, estados, menus, HUD, áudio, assets e feedback |
| Requisitos principais | RF01–RF04, RF09, RF14, RF33–RF34, RNF02, RNF05 e validação visual de RNF06 |
| Estado | Planejado; contratos de entrada existem, mas a aplicação gráfica ainda deve ser implementada |

Este documento organiza o trabalho da Frente D. Ele não substitui o enunciado nem
`docs/PLANO.md`. A apresentação deve consumir snapshots, comandos e eventos das outras
frentes. Nenhuma classe de tela, renderer ou áudio pode decidir regras de gameplay.

Não é necessário criar protótipo vertical nem protótipo HTML/CSS. A interface final continua
sendo uma única janela Raylib.

---

## 1. Resultado esperado

Ao final da Frente D, o usuário deve conseguir:

1. abrir o jogo e navegar pelo menu;
2. ler instruções e consultar o ranking;
3. escolher Steve ou Alex;
4. iniciar, pausar, retomar e encerrar uma partida;
5. controlar movimento/olhar por teclado ou mouse;
6. enxergar jogador, mapa, inimigos, ataques, itens e HUD;
7. escolher uma opção na tela de level up;
8. receber feedback visual e sonoro dos sete eventos obrigatórios;
9. ver vitória/derrota, informar nome e salvar a pontuação;
10. executar a carga real com pelo menos 50 FPS na máquina documentada.

A Frente D apresenta e traduz entrada. Ela não calcula dano, XP, level up, spawn, recompensa
ou pontuação.

---

## 2. Escopo confirmado

### 2.1 Responsabilidade direta

| Requisito | Entrega da Frente D |
| --- | --- |
| RF01 | Menu com Iniciar, Ranking, Instruções e Sair |
| RF02 | Tela de seleção dos dois personagens |
| RF04 | Entrada real por teclado ou mouse |
| RF14 | Tela/overlay de level up e interação com a pausa |
| RF33 | Campo e validação de interação para nome do jogador |
| RF34 | Apresentação do ranking |
| RNF02 | Adapters de apresentação isolados do domínio |
| RNF05 | Feedback visual e sonoro dos sete eventos obrigatórios |
| RNF06 | Medição visual conjunta de FPS e tempo até o menu |

### 2.2 Responsabilidade compartilhada

| Fluxo | Frente D | Integração externa |
| --- | --- | --- |
| Inicialização | Estado/tela e bootstrap gráfico | B cria personagem; C cria mundo; A fornece loop |
| Jogabilidade | Entrada, câmera, desenho e HUD | A/B/C produzem estado lógico |
| Level up | Apresenta opções e devolve escolha | B gera/aplica; A pausa/retoma |
| Fim | Mostra resumo, recebe nome e apresenta resultado | B calcula/salva; A/C informam derrota/vitória |
| Ranking | Desenha e navega pelos resultados | B consulta o repositório |
| Eventos | Produz feedback visual/sonoro | A/B/C publicam eventos tipados |

### 2.3 Fora do escopo

- movimento, colisão, dano, armas, pool lógico e desempenho headless — Frente A;
- personagens, XP, upgrades, passivos, pontuação e persistência — Frente B;
- inimigos, IA, spawn, boss, baús e drops — Frente C;
- qualquer regra de gameplay dentro de renderer, input ou áudio;
- menus web/HTML como interface do jogo.

---

## 3. Base já disponível

- `GameLoop` separa update e render e fornece interpolação;
- `InputInterface` e `InputState` já estão definidos;
- `NeutralInput` e `ScriptedInput` permitem testes sem hardware;
- eventos `PlayerDamaged`, `EnemyHit`, `EnemyKilled` e `PlayerDied` existem;
- snapshots de mundo, projéteis e estado lógico podem ser expostos por Application;
- benchmark A10 aprovou a parte headless e deixou a meta visual de 50 FPS pendente;
- `assets/CREDITOS.md` define as regras de licenciamento.

Não importar classes sintéticas de benchmark para o jogo gráfico.

---

## 4. Entregas detalhadas

### D1 — binding FFI e ciclo da janela

**Objetivo:** isolar completamente o Raylib.

**Produzir:**

- `RaylibBinding` como único local autorizado a chamar `FFI::`;
- declarações C mínimas e compatíveis com a versão documentada;
- criação/fechamento da janela;
- início/fim de quadro;
- tempo, teclado, mouse, desenho, texturas, fontes e áudio necessários;
- erros claros quando biblioteca ou símbolo não estiver disponível;
- descarregamento de todos os recursos;
- smoke test/manual de inicialização e teste arquitetural de isolamento.

**Aceite:** uma janela vazia abre e fecha sem vazamento, e nenhuma classe de domínio importa FFI.

**Branch sugerida:** `codex/frente-d-d1-raylib-binding`.

### D2 — entrada real

**Objetivo:** implementar RF04 sobre o contrato existente.

**Produzir:**

- `RaylibInput implements InputInterface`;
- leitura de teclado e/ou mouse;
- vetor de movimento;
- direção do olhar quando fornecida;
- pedido de pausa;
- mapeamento documentado dos controles;
- neutralização de eixos opostos;
- nenhum comando manual de ataque;
- testes do mapeamento por adapter/fake, sem janela real.

**Aceite:** a mesma combinação de teclas produz o `InputState` esperado, e a A continua sendo a
única responsável por mover o jogador.

**Branch sugerida:** `codex/frente-d-d2-input-real`.

### D3 — renderer, recursos e câmera

**Objetivo:** desenhar o mundo sem alterar seu estado.

**Produzir:**

- `RendererInterface`, `RaylibRenderer` e `NullRenderer`;
- carregador/cache e descarregamento de texturas/fontes;
- câmera centrada no jogador e limites acordados;
- interpolação visual usando o alpha do `GameLoop`;
- camadas: mapa, cenário, pickups, entidades, ataques, efeitos e HUD;
- conversão explícita entre coordenadas de mundo e tela;
- snapshots de apresentação imutáveis;
- testes do cálculo de câmera/camadas e modo nulo.

**Aceite:** renderizar um quadro não modifica posição, vida, cooldown, RNG ou qualquer regra.

**Branch sugerida:** `codex/frente-d-d3-renderer-camera`.

### D4 — áudio

**Objetivo:** fornecer música e efeitos sem acoplar domínio ao Raylib.

**Produzir:**

- `AudioInterface`, `RaylibAudio` e `NullAudio`;
- carregamento/descarregamento de WAV/OGG;
- música do menu e da partida;
- efeitos sonoros dos eventos obrigatórios;
- volume e mute, se acordados;
- ausência segura de dispositivo/asset;
- testes com fake/null e assinaturas do `EventBus`.

**Aceite:** eventos podem tocar sons, mas falha de apresentação não altera o resultado lógico.

**Branch sugerida:** `codex/frente-d-d4-audio`.

### D5 — máquina de estados

**Objetivo:** organizar menu, jogo, pausa e overlays.

**Produzir:**

- contrato `GameState`;
- `StateStack` com push/pop/replace;
- `enter`, `update`, `render` e `exit`;
- transições aplicadas em ponto seguro;
- congelamento do `PlayState` sob pausa/level up;
- estados: Menu, CharacterSelect, Play, LevelUp, Pause, GameOver, Ranking e Instructions;
- testes de ciclo de vida, ordem e transições recursivas.

**Aceite:** empilhar LevelUp/Pause conserva a partida intacta e desempilhar retoma sem avanço de
tempo lógico durante a pausa.

**Branch sugerida:** `codex/frente-d-d5-state-stack`.

### D6 — menu, instruções e seleção

**Objetivo:** atender RF01 e a parte visual de RF02.

**Produzir:**

- Menu: Iniciar, Ranking, Instruções e Sair;
- navegação, foco, confirmação, retorno e clique;
- Instructions com controles, objetivo e regras essenciais;
- CharacterSelect com Steve/Alex, atributos, arma e passiva;
- confirmação devolvendo somente o ID à Application/B;
- tratamento de janela redimensionável, se permitido;
- testes de navegação com entrada falsa.

**Não produzir:** atributos ou regras dos personagens.

**Aceite:** todas as rotas do menu são alcançáveis e a seleção inicia a composição correta.

**Branch sugerida:** `codex/frente-d-d6-menu-selecao`.

### D7 — estado de jogo e HUD

**Objetivo:** apresentar a partida real.

**Produzir:**

- `PlayState` coordenando update lógico e render;
- desenho de jogador, comuns, especiais e boss;
- projéteis, TNT, espada e pulso;
- baús, pickups e elementos quebráveis;
- HUD com vida, XP, nível, tempo, armas, cooldowns e passivos;
- barra de vida do boss;
- indicador de evento/onda quando acordado;
- leitura apenas por snapshots;
- testes dos view models e campos obrigatórios.

**Aceite:** todo estado relevante fica visível sem a camada D consultar/mutar internals do domínio.

**Branch sugerida:** `codex/frente-d-d7-play-hud`.

### D8 — level up e pausa

**Objetivo:** atender RF14.

**Produzir:**

- overlay de level up com as opções fornecidas pela B;
- nome, tipo, descrição e efeito;
- navegação por teclado/mouse;
- confirmação única;
- fluxo de substituição de arma quando necessário;
- pausa manual separada do level up;
- retomada após resposta válida;
- caso explícito para ausência de opções;
- testes de abertura, seleção, cancelamento permitido e retomada.

**Aceite:** nenhum passo lógico ocorre enquanto a tela bloqueante estiver ativa e a D não aplica
o upgrade diretamente.

**Branch sugerida:** `codex/frente-d-d8-level-up-pausa`.

### D9 — feedback visual e sonoro

**Objetivo:** atender RNF05.

**Produzir feedback para:**

1. personagem recebendo dano;
2. inimigo atingido;
3. inimigo derrotado;
4. level up;
5. abertura de baú;
6. morte do jogador;
7. obtenção de item.

**Também produzir:**

- assinantes separados para áudio e efeitos;
- flash, partículas/animações e números de dano necessários;
- pool de objetos visuais reutilizando a infraestrutura genérica da A quando adequado;
- duração pelo relógio visual, sem alterar o tempo do domínio;
- ausência de assinante detectável por teste/checklist;
- testes de um feedback por evento e limpeza dos efeitos.

**Aceite:** os sete eventos têm feedback visual e sonoro perceptível e nenhuma duplicação causada
por reinscrição de listeners.

**Branch sugerida:** `codex/frente-d-d9-feedback`.

### D10 — game over, vitória, nome e ranking

**Objetivo:** apresentar RF09, RF33 e RF34.

**Produzir:**

- GameOver/Victory com personagem, nível, mortes, tempo e pontuação;
- campo de nome com validação comunicada pela B;
- comando de salvamento e resposta de sucesso/erro;
- Ranking com consulta, ordenação já fornecida, limite/paginação;
- retorno ao menu e nova partida;
- prevenção de salvamento duplicado;
- testes de estados, entrada inválida e falha do repositório.

**Aceite:** o usuário consegue salvar uma partida concluída e consultá-la no ranking após reiniciar.

**Branch sugerida:** `codex/frente-d-d10-fim-ranking`.

### D11 — assets e créditos

**Objetivo:** fornecer todos os recursos visuais/sonoros legalmente utilizáveis.

**Produzir:**

- sprites de jogador, inimigos, boss, armas, projéteis, itens, baús e cenário;
- elementos de HUD e menus;
- fontes;
- músicas e efeitos;
- tamanhos/origens consistentes com raios lógicos;
- registro no mesmo commit em `assets/CREDITOS.md`;
- origem, autor, licença, responsável e data;
- tela de créditos quando a licença exigir.

**Regras:** não usar arquivos originais do Minecraft; na dúvida sobre licença, não usar.

**Aceite:** nenhum asset sem origem/licença e nenhum recurso ausente no fluxo principal.

**Branch sugerida:** `codex/frente-d-d11-assets-creditos`.

### D12 — bootstrap gráfico, desempenho e regressão

**Objetivo:** tornar o projeto executável e fechar RNF06 com a A.

**Produzir:**

- `jogo.php`/bootstrap sem singletons;
- composição das implementações A/B/C/D já integradas na `main`;
- EventBus novo por partida;
- ciclo de recursos por estado/partida;
- smoke test do jogo gráfico;
- cenário real com 300 inimigos e pico de 200 projéteis;
- medição de pelo menos 50 FPS e tempo até o menu menor que 3 s;
- registro da máquina, resolução, seed e resultado;
- regressão de todos os estados e sete feedbacks;
- documentação de execução e instalação.

**Aceite:** o jogo inicia no menu, uma partida completa pode ser jogada até vitória/derrota e as
metas visuais ficam registradas honestamente.

**Branch sugerida:** `codex/frente-d-d12-integracao-final`.

---

## 5. Ordem recomendada

```text
D1 Binding
 ├── D2 Input
 ├── D3 Renderer/câmera
 └── D4 Áudio
D5 StateStack
 ├── D6 Menu/seleção
 ├── D7 Play/HUD
 │    ├── D8 Level up/pausa
 │    └── D9 Feedback
 └── D10 Fim/ranking
D11 Assets alimenta D3/D4/D6–D10
Todos ─────────────── D12 Bootstrap/desempenho/regressão
```

---

## 6. Matriz de testes obrigatórios

| Área | Casos mínimos |
| --- | --- |
| FFI | carregamento, símbolos, falha clara e isolamento arquitetural |
| Input | eixos, opostos, mouse/teclado, olhar e pausa |
| Renderer | câmera, interpolação, camadas, null renderer e leitura pura |
| Áudio | null/fake, ciclo de recursos e eventos |
| StateStack | push/pop/replace, enter/exit, pausa e overlay |
| Menu | quatro opções, navegação, retorno e sair |
| Seleção | Steve/Alex, confirmação e dados apresentados |
| HUD | vida, XP, nível, tempo, armas, cooldown e boss |
| Level up | opções, escolha única, substituição e retomada |
| Feedback | os sete eventos obrigatórios, sem duplicação |
| Fim/ranking | vitória, derrota, nome, save, erro e consulta |
| Desempenho | 300 inimigos, 200 projéteis, ≥50 FPS e menu <3 s |

---

## 7. Decisões que precisam ser fechadas

- teclado, mouse ou ambos como controle inicial;
- política real da direção do olhar;
- resolução, tamanho da janela e escala visual;
- payloads finais de `LevelUp`, `ChestOpened`, `ItemPicked` e `WaveStarted`;
- comportamento da pausa manual versus level up;
- quais informações aparecem no HUD;
- forma de informar erro de SQLite;
- licenças e identidade visual finais;
- máquina/resolução usada para certificar 50 FPS.

Registrar decisões compartilhadas em `docs/CONTRATOS.md`.

---

## 8. Definição de pronto

Uma entrega D está pronta somente quando:

- [ ] apresentação não contém regra de gameplay;
- [ ] somente `Infrastructure/Ffi` chama `FFI::`;
- [ ] recursos são carregados/descarregados com segurança;
- [ ] possui adapter nulo/fake para testes quando aplicável;
- [ ] eventos não são duplicados entre partidas;
- [ ] todos os assets possuem licença registrada;
- [ ] testes, `composer check` e `git diff --check` passam;
- [ ] smoke test gráfico e checklist visual foram executados;
- [ ] documentação de instalação/execução foi atualizada;
- [ ] nenhum binário de Raylib, segredo ou arquivo temporário entrou no Git;
- [ ] branch foi enviada e revisada por outra frente.

---

## 9. Próxima ação

Começar pela D1 e D5 em branches separadas. Em paralelo, ratificar com B os view models de
seleção/level up/ranking e com C os snapshots visuais dos inimigos, boss, baús e drops. Não
esperar todos os assets para testar: use formas simples temporárias no renderer, mantendo-as
claramente substituíveis e sem criar protótipo vertical separado.
