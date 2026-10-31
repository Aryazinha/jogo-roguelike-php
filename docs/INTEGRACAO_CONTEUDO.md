# Entrega C11 — integração do conteúdo

A C11 junta tudo o que a Frente C fez (C1 a C10) num único ponto de entrada, testa uma partida
inteira sem janela e mede o desempenho com 300 inimigos (RNF06).

**Estado:** C11 concluída e verificada com `composer check`. Falta a ligação com as armas reais
da A, a progressão da B e o desenho da D, que é trabalho conjunto da Application.

## `ContentSession`

`Application/Content/ContentSession` carrega `config/`, monta o mundo e o cenário e liga todos
os sistemas da C:

```php
$sessao = ContentSession::fromProject($raiz, $jogador, $seed, $barramento);

// a cada passo fixo do GameLoop da A:
$sessao->step($dt, $tempo, $nivelDoJogador);
```

Ordem dentro de `step()`:

| # | Sistema | Entrega |
| --- | --- | --- |
| 1 | Eventos aleatórios e multiplicador do Tesouro | C9 |
| 2 | Ondas, com os multiplicadores da Horda | C7 |
| 3 | Warden (surgimento, fases, invocação) | C6 |
| 4 | Movimento dos inimigos | C3, C5 |
| 5 | Bloqueio por caixote e minério | C10 |
| 6 | Tiros do Skeleton e voo das flechas | C5 |
| 7 | Dano de contato | C4 |
| 8 | Abertura de baús e coleta de itens | C8, C10 |
| 9 | Ponto seguro: `flush()` e limpeza de estado dos que saíram | C2 |

**Onde entram as outras frentes:**

- **A:** as armas do jogador rodam entre o movimento (4) e o ponto seguro (9), usando
  `$sessao->world` como `EnemyTargetResolver` e `$sessao->damage` para aplicar o dano.
- **B:** o jogador (`Entity&Damageable`) entra em `fromProject()`. A B lê
  `$sessao->chests->takeOpened()`, `$sessao->breakables->takePicked()` e
  `$sessao->events->xpMultiplier()`, e escuta `EnemyKilled` para dar XP.
- **D:** desenha a partir de `$sessao->world->snapshots()` (todos os `kind`) e consulta
  `boss->phase()`, `events->active()` e `waves->currentBandIndex()`.

**Seeds.** Cada sistema que sorteia recebe o próprio gerador, derivado da seed da partida.
Mudar um sistema não altera os sorteios dos outros, e a mesma seed repete a partida inteira.

## Teto de 300 inimigos

Uma partida completa mostrou o total chegando a 308: filhos de Slime e Zombies invocados
entravam mesmo com o mundo cheio. A correção:

- o `GameWorld` guarda o teto e informa as vagas livres (`freeEnemySlots()`);
- ondas, divisão do Slime e invocação do Warden criam só o que cabe;
- uma vaga fica reservada para o Warden, que nasce mesmo com o mundo cheio. Assim o total
  nunca passa de 300.

## Partida completa sem janela

`tests/Integration/ContentSessionTest.php` roda 720 s com todo o conteúdo real e uma arma de
teste (8 de dano a cada 0,25 s em tudo a até 120 px do jogador):

- a população nunca passa de 300;
- acontecem eventos aleatórios;
- morrem os sete tipos de inimigo, inclusive o Slime pequeno e o Warden;
- o baú do Warden é aberto e itens do cenário são coletados;
- a mesma seed repete o estado final, e outra seed muda.

## Desempenho (RNF06)

`tools/content-benchmark.php` coloca 299 inimigos reais (mais a vaga do Warden) cercando o
jogador e mede o passo inteiro da C durante 60 s:

```bash
php -d opcache.enable_cli=0 tools/content-benchmark.php 3600
```

| Medição (máquina da Lara, sem outra carga) | Mediana | p95 | Máximo |
| --- | --- | --- | --- |
| Passo da C, 300 inimigos, sem OPcache | 6,9 ms | 9,1 ms | 13,5 ms |
| Passo da C, 300 inimigos, com OPcache/JIT | 4,5 ms | 6,7 ms | 14,4 ms |
| Benchmark A10 da Frente A, sem OPcache | 3,0 ms | 5,3 ms | 10,5 ms |

**Orçamento conjunto.** Somando os p95 de A e C, que é uma conta pessimista porque os piores
passos não coincidem, dá cerca de 14,4 ms sem OPcache, abaixo dos 16,6 ms de um quadro a
60 FPS. Ainda falta medir A, C e o desenho da D juntos, numa partida com janela. O benchmark A10
roda armas e projéteis com alvos sintéticos; o da C roda o conteúdo real sem as armas da A.

## Pendências com a equipe

| Pendência | Com quem |
| --- | --- |
| Ligar as armas reais ao passo e ao dano nos quebráveis | A |
| Payloads de `ChestOpened`, `ItemPicked` e `WaveStarted` | B, D |
| Ordem entre derrota do Warden, baú e tela de vitória | Todos |
| Valor do `xp_pequeno` e efeito do `item_especial` | B |
| Tamanho da tela, para confirmar a distância de spawn | D |
| Benchmark conjunto com janela | A, D |

## Testes

| Arquivo | O que cobre |
| --- | --- |
| `tests/Integration/ContentSessionTest.php` | Partida completa e repetição pela seed |
| `tests/Unit/Domain/World/GameWorldTest.php` | Vagas livres com teto e fila |
| `tests/Unit/Domain/Enemy/SplitOnDeathTest.php` | Divisão com o mundo cheio |
| `tests/Unit/Domain/System/BossSystemTest.php` | Invocação com o mundo cheio |
