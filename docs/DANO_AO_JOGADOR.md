# Entrega C4 — dano dos inimigos ao jogador

A C4 completa o lado inimigo → jogador do RF08: decide quando um inimigo acerta o jogador
e quanto tira. Quem aplica o dano e publica `PlayerDamaged` e `PlayerDied` continua sendo o
`DamageSystem` da Frente A, sem nenhuma alteração.

**Estado:** C4 concluída e verificada com `composer check`.

## Componentes

| Componente | Responsabilidade |
| --- | --- |
| `config/enemies.json`, seção `contato` | Tempo de invulnerabilidade depois de um golpe (0,5 s, provisório) |
| `Core/Config/ContactConfigLoader` | Lê e valida a seção |
| `Domain/System/EnemyAttackSystem` | Contato, invulnerabilidade, um golpe por passo e ataques específicos |

## Regras

| Pendência do plano | Decisão | Quem confirma |
| --- | --- | --- |
| Frequência do dano por contato | No máximo um golpe por passo, e só fora da invulnerabilidade | Frente A |
| Intervalo de invulnerabilidade | 0,5 s de tempo de simulação | Frente A / equipe |
| Vários inimigos encostados | Vale o de maior dano; empate vai para o menor ID | — |

- **Contato é encostar.** Os círculos de colisão se tocam, e a tangência exata conta, igual
  ao `CollisionSystem` da A.
- **Tempo de simulação, não de relógio.** Durante a pausa o tempo não anda, então a
  invulnerabilidade não acaba enquanto o jogo está parado.
- **Ataques específicos.** `strike()` recebe a flecha do Skeleton (C5) ou a investida do
  Warden (C6) e respeita a mesma invulnerabilidade, para contato e flecha não somarem no
  mesmo instante.
- **Morte única.** Depois que o jogador morre, o `DamageSystem` o desativa e o sistema para
  de procurar contato. `PlayerDied` sai uma vez só.

## Uso no passo da partida

```php
$steering->update($world, $dt);          // C3: inimigos andam
$attacks->update($world, $tempoDecorrido); // C4: quem encostou bate
$world->flush();                          // ponto seguro
```

## Testes

| Arquivo | O que cobre |
| --- | --- |
| `tests/Unit/Core/Config/ContactConfigLoaderTest.php` | Seção real e campos inválidos |
| `tests/Unit/Domain/System/EnemyAttackSystemTest.php` | Contato, tangência, invulnerabilidade, grupo, empate, mortos, morte única, `strike()` e pausa |
| `tests/Integration/EnemyChaseAndHitTest.php` | Zombies reais perseguem, acertam no tempo certo e matam o jogador uma vez |
