# D12 — Bootstrap, integracao e medicao (etapa inicial)

## Execucao

```powershell
composer check
php jogo.php
php jogo.php --demo
php tools/benchmark_raylib_d12.php
composer benchmark -- --warmup=120 --steps=600 --runs=2 --enemies=300 --projectiles=200
```

O `jogo.php` abre um menu grafico com os recursos D11, selecao de personagem
(e dados reais de `config/characters.json`) e uma tela de jogo. Usa:

- `GameLoop`, `EventBus`, `MovementSystem` e `Facing` da Frente A;
- `ProgressionSession` e o SQLite real da Frente B;
- D6-D11: menu, HUD, level up, pausa, game over, ranking, efeitos e assets;
- um `EventBus` novo por partida, sem singletons ou FFI fora do binding D1;
- descarregamento final de audio, fontes e texturas.

## Controles

| Tecla | Acao |
| --- | --- |
| WASD | Mover (sistema da Frente A com velocidade da Frente B) |
| Setas | Olhar ou navegar pelos menus |
| P | Pausar/retomar |
| Q | Voltar ao menu sem registrar uma partida incompleta |
| ESC | Voltar de telas de menus e ranking |
| F8 (`--demo`) | Conceder XP de teste pelo sistema REAL da Frente B |
| F9 (`--demo`) | Publicar morte de teste e abrir Game Over / ranking REAL da Frente B |

As teclas F8 e F9 existem **apenas** para inspecionar telas e nao representam
mecanicas de combate. O comando `php jogo.php` nao habilita esses atalhos.

## Banco de dados

`database/jogo.sqlite` e criado pela `SqliteConnection` e migrado com
`Migrator` da Frente B. `BResultsGateway` usa `PlayerName`, `ScoreCalculator`,
`ScoreEntry` e `SqliteScoreRepository` reais. Nao usa o SQLite demonstrativo da D10.

O score e registrado uma unica vez por partida na mesma execucao; o ranking
continua persistido depois de reabrir o programa.

## Desempenho

- O tempo ate o primeiro desenho de menu aparece no terminal.
- `tools/benchmark_raylib_d12.php` desenha 300 inimigos e 200 projeteis
  e registra FPS medio, FPS do percentil 5, minimo e ambiente em
  `tools/output/d12_visual.json`.
- `tools/benchmark.php` da A10 mede a simulacao logica sem Raylib.
- **Estes dois benchmarks sao separados** e nao comprovam, sozinhos, uma
  partida real com combate e renderizacao simultaneos acima de 50 FPS.
- Os resultados devem ser copiados para o relatorio final somente depois
  de executar na maquina do projeto. Nao presumir aprovacao sem mediccao.

## Dependencias ainda bloqueadas (Frente C)

Na revisao de 17/10/2026, as implementacoes de inimigos e mundo da Frente C
estavam em branches separadas, nao na `main` de base da D.

D12 usa o `ProgressionSession` real da B, mas sem a Frente C integrada:

- o piso exibido e visual/decorativo; nao e o mapa final;
- nao existe spawn de inimigos, boss, baus ou drops naturais;
- o callback de execucao de arma da B devolve `false` (nao simula combate);
- eventos F8/F9 sao atalhos **de demonstracao**;
- nao ha partida real completa ate a composicao final da A/B/C/D.

A proxima etapa e obter o merge/branch integrador da Frente C e conectar
os sistemas de combate/spawn/drops, substituir o piso ilustrativo e rodar a
regressao grafica integrada com 300 inimigos e 200 projeteis.

Nao considerar o aceite completo da D12 ate esses passos terminarem.
