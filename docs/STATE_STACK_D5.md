# D5 - Maquina de estados

Implementa a estrutura de navegacao e ciclo de vida
das telas do jogo, sem regras de gameplay.

## Contratos

GameState define:

- name
- enter
- update
- render
- exit
- rendersBelow

ScreenState fornece implementacao basica, permitindo
especializacoes nas entregas posteriores.

## Estados disponiveis

- MenuState
- CharacterSelectState
- PlayState
- LevelUpState
- PauseState
- GameOverState
- RankingState
- InstructionsState

Os estados ainda nao implementam os menus ou HUD
definitivos. A D5 entrega seu ciclo de vida e as
regras de empilhamento.

## StateStack

A pilha aceita:

- push: empilha uma tela
- pop: remove a tela superior
- replace: substitui a tela superior

As operacoes sao enfileiradas e aplicadas por flush,
fora dos callbacks dos estados.

Transicoes solicitadas dentro de enter, update,
render ou exit nao interrompem callbacks ativos.

O update executa apenas o estado superior.

Na renderizacao, overlays transparentes permitem
desenhar tambem os estados abaixo deles.

PauseState e LevelUpState sao overlays.
Outras telas sao opacas nesta versao.

## Integracao com GameLoop

StateLoopSynchronizer liga as transicoes da pilha
aos metodos pause e resume do GameLoop.

Com PauseState ou LevelUpState acima de PlayState,
o GameLoop fica pausado e nao executa passos logicos.

Quando os overlays bloqueantes sao removidos,
o GameLoop pode continuar sem recuperar o tempo
decorrido na pausa.

A integracao final precisa chamar synchronize
depois de tratar entrada/transicoes e antes de tick.

A entrada da UI durante pausa deve ser processada
fora do callback update do GameLoop, pois esse callback
fica suspenso. A implementacao dessa entrada pertence
as entregas de telas e integracao posteriores.

## Fora do escopo D5

- Desenho real de menus: D6
- Logica real da partida e HUD: D7
- Escolha de upgrades e controles de pausa: D8
- Tela final e ranking funcional: D10
- Bootstrap grafico completo: D12

## Testes

composer check

php tools/smoke_state_stack.php

O smoke da D5 e executado no terminal, sem janela
grafica, porque testa o fluxo dos estados.