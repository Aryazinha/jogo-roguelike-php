# Entrega C7 — spawn e ondas

A C7 controla quando, quantos e quais inimigos surgem ao longo da partida. Atende RF05
(geração progressiva pelo nível do jogador), RF28 (dificuldade crescente) e RF29 (intensidades
diferentes ao longo do tempo), com o teto de 300 inimigos do RNF06.

**Estado:** C7 concluída e verificada com `composer check`.

## Componentes

| Componente | Responsabilidade |
| --- | --- |
| `Domain/Wave/WaveBand` | Uma faixa de tempo: intervalo, quantos por vez e tipos |
| `Domain/Wave/WaveSettings` | Faixas sem buraco, teto e escalonamento por nível |
| `Core/Config/WaveConfigLoader` | Lê `waves.json` e confere os tipos no catálogo |
| `Domain/Wave/WaveDirector` | Cria os inimigos a cada passo |
| `Enemy` / `EnemyFactory` | Passaram a aceitar multiplicadores de vida e dano |

## A curva (`waves.json`, valores provisórios)

| Tempo | A cada | Quantos | Tipos |
| --- | --- | --- | --- |
| 0–120 s | 2,0 s | 2 | Spider |
| 120–300 s | 1,5 s | 3 | Spider, Zombie |
| 300–480 s | 1,1 s | 4 | Zombie, Slime, Skeleton |
| 480–600 s | 0,8 s | 5 | Zombie, Husk, Slime, Skeleton |
| 600 s em diante | 0,7 s | 6 | Zombie, Husk, Slime, Skeleton |

O início da faixa conta e o fim não: com 120 s exatos, já vale a segunda faixa. O Warden não
está aqui, porque tem hora própria (C6).

## Como o RF28 é atendido

A dificuldade cresce de quatro jeitos, os quatro citados no enunciado:

1. **Mais inimigos por vez** (2 → 6);
2. **Maior frequência** (a cada 2 s → a cada 0,7 s);
3. **Novos tipos** em cada faixa;
4. **Inimigos mais fortes**: pelo nível do jogador, +4% de vida e +3% de dano por nível acima
   do 1. Um Zombie criado com o jogador no nível 11 tem 13 × 1,4 = 18,2 de vida.

## Regras do `WaveDirector`

- **Nível mínimo (RF05).** O tipo é sorteado entre os da faixa que o nível do jogador já libera
  (o Zombie só a partir do nível 6, por exemplo). Se nenhum tipo da faixa estiver liberado,
  usa qualquer tipo liberado, para a partida não ficar vazia.
- **Onde nasce.** Pelo `SpawnPointPicker` da C2: fora da tela e dentro do mapa.
- **Teto de 300.** Cada intervalo "deve" uma quantidade de inimigos. Se não cabem todos, o
  restante fica devendo e entra assim que abrir vaga. A dívida nunca passa de 300, para não
  virar uma enxurrada depois de um tempo cheio. A contagem inclui os filhos de Slime e os
  invocados que ainda estão na fila.
- **Eventos (C9).** `update()` recebe multiplicadores de quantidade e de intervalo. A Horda
  usa ×3 na quantidade e ×0,5 no intervalo.
- **Pausa.** Passo zero não acumula tempo.
- **Seed.** Tipo e posição saem do `RandomIndex` injetado: a mesma seed repete a curva.

## Decisões

| Pendência | Decisão |
| --- | --- |
| Evento `WaveStarted` | Não foi criado: está pendente de ratificação no `CONTRATOS.md`. A Frente D lê `currentBandIndex()` |
| Diretor adaptativo | Continua fora (`diretorAdaptativo: false`); o loader recusa `true` |
| Divisões e invocações acima do teto | Não são barradas, porque são consequência de uma morte ou do boss. O teto vale para as ondas |

## Testes

| Arquivo | O que cobre |
| --- | --- |
| `tests/Unit/Core/Config/WaveConfigLoaderTest.php` | Curva real, fronteiras, escalonamento e 11 arquivos inválidos |
| `tests/Unit/Domain/Wave/WaveDirectorTest.php` | Intervalo, troca de faixa, nível mínimo, fallback, escalonamento, teto e retenção, posições, seed, Horda e pausa |
| `tests/Integration/WaveCurveTest.php` | De 0 a 600 s com os arquivos reais: curva, teto e repetição pela seed |
