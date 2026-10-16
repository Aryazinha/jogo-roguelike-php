# Entrega C6 — boss Warden

A C6 atende o RF22-b com um boss que não é só "um inimigo comum gordo": ele tem fases, avisa
antes de atacar e muda de comportamento conforme perde vida (`PLANO.md` §4.2). Também
sustenta a condição de vitória do RF09 definida no plano: **derrotar o Warden, que surge aos
10 minutos**.

**Estado:** C6 concluída e verificada com `composer check`.

## Componentes

| Componente | Responsabilidade |
| --- | --- |
| `Domain/Enemy/Boss/BossPhaseSettings` | Durações, limiares e bônus lidos de `enemies.json` |
| `Core/Config/BossConfigLoader` | Lê `boss.warden.fases` e confere o tipo invocado |
| `Domain/Enemy/Boss/BossPhase` | As cinco fases de movimento |
| `Domain/Enemy/Boss/WardenBrain` | Máquina de estados: decide o que o Warden faz em cada passo |
| `Domain/Enemy/Boss/BossAction` | A decisão do passo: direção, multiplicador de velocidade e invocação |
| `Domain/System/BossSystem` | Surgimento aos 600 s, movimento, invocações e registro da derrota |

## Fases

```
perseguindo ──(8 s sem investir)──> telegrafando ──(0,8 s)──> investindo ──(1 s)──> recuperando ──(1 s)──┐
     ^                                                                                                     │
     └─────────────────────────────────────────────────────────────────────────────────────────────────────┘

perseguindo ──(vida cruzou 70% ou 40%)──> invocando (1 s) ──> perseguindo
```

| Fase | O que faz |
| --- | --- |
| perseguindo | Vai até o jogador na velocidade normal (45 px/s) |
| telegrafando | Para e **trava a direção** da investida. É o aviso para o jogador |
| investindo | Avança em linha reta, na direção travada, a 3 × a velocidade |
| recuperando | Fica parado: a janela em que o jogador é premiado por ter desviado |
| invocando | Fica parado e chama 8 Zombies em volta de si |

**Enfurecido** não é uma sexta fase de movimento: é um estado que vale por cima de todas a
partir de 25% de vida, até o fim da luta. Anda 30% mais rápido (a investida vai a
3 × 1,3 = 3,9) e espera 30% menos entre investidas (5,6 s em vez de 8).

## Regras de borda

- **Uma vez por limiar.** Cada limiar (70% e 40%) invoca uma vez só, mesmo que a vida volte
  a subir e descer.
- **Golpe grande.** Um golpe que leva de 100% para 30% cruza dois limiares: são duas
  invocações seguidas.
- **Não interrompe a investida.** Uma invocação devida espera o Warden voltar a perseguir.
- **Pausa.** Com passo zero, nenhum relógio de fase anda.
- **Determinismo.** O `WardenBrain` não sorteia nada; só o ponto de surgimento usa o RNG da
  partida. A mesma seed repete a luta inteira.

## Derrota e vitória

A lista de eventos do `CONTRATOS.md` §3 é fechada, e criar um `BossDefeated` seria mudança de
contrato. A derrota do Warden já é inequívoca: é o `EnemyKilled` com `isBoss = true`, que o
`DamageSystem` publica uma única vez. O `BossSystem` registra o instante em `defeatedAt()`, e
`isDefeated()` diz à Application e à Frente B que a partida foi vencida.

A ordem entre derrota, baú (C8) e tela de vitória continua pendente de decisão da equipe.

## Por que o boss não passa pelo `EnemySteeringSystem`

A velocidade do Warden muda de acordo com a fase (parado, normal, 3 ×, 3,9 ×). O
`EnemySteeringSystem` usa a velocidade fixa do arquétipo, então pula o boss, e o `BossSystem`
chama o `MovementSystem` da A com a velocidade já multiplicada.

## Para a Frente D

`BossSystem::phase()` e `isEnraged()` permitem, por exemplo, piscar o sprite no telegrafar e
mudar a cor na fúria. `WardenBrain::lockedDirection()` dá a direção da investida para
desenhar o aviso.

## Testes

| Arquivo | O que cobre |
| --- | --- |
| `tests/Unit/Core/Config/BossConfigLoaderTest.php` | Fases reais e dados inválidos |
| `tests/Unit/Domain/Enemy/Boss/WardenBrainTest.php` | Cada transição e tempo, direção travada, limiares, golpe grande, fúria, pausa, determinismo |
| `tests/Unit/Domain/System/BossSystemTest.php` | Surgimento único aos 600 s, movimento, investida, invocação, derrota e pausa |
| `tests/Integration/WardenFightTest.php` | Luta completa com o conteúdo real, repetida com a mesma seed |
