# Entrega C9 — eventos aleatórios

A C9 atende o RF35 com os três eventos sugeridos no enunciado, que pede no mínimo dois:
**Horda**, **Chuva de XP** e **Tesouro**.

**Estado:** C9 concluída e verificada com `composer check`.

## Componentes

| Componente | Responsabilidade |
| --- | --- |
| `Domain/RandomEvent/EventEffect` | Um efeito em PERCENT sobre um dos quatro alvos conhecidos |
| `Domain/RandomEvent/RandomEventDefinition` | Um evento: peso, duração e efeitos |
| `Domain/RandomEvent/EventSettings` | Os eventos e o intervalo entre eles |
| `Core/Config/EventConfigLoader` | Lê e valida `events.json` |
| `Domain/RandomEvent/EventDirector` | Sorteia, inicia e encerra os eventos |

## Os eventos (`events.json`)

| Evento | Peso | Duração | Efeito | Quem aplica |
| --- | --- | --- | --- | --- |
| Horda | 40 | 20 s | inimigos por spawn +200% (× 3) e intervalo −50% (× 0,5) | `WaveDirector` (C7) |
| Chuva de XP | 40 | 30 s | XP de cada inimigo +100% (× 2) | Frente B |
| Tesouro | 20 | 45 s | chance de baú +150% (× 2,5) | `ChestSystem` (C8) |

Depois que um evento termina, o próximo começa entre 60 e 120 s depois (sorteado). O peso
decide qual: Horda e Chuva de XP saem com 40% de chance cada, e o Tesouro com 20%.

## Regras

- **Um por vez.** Nunca há dois eventos ativos ao mesmo tempo.
- **Só o próprio alvo.** Cada evento mexe apenas nos atributos dele. Os outros multiplicadores
  continuam em 1.
- **Sem resto.** Quando o evento acaba, os multiplicadores voltam a 1 no mesmo instante.
- **Duração exata.** O evento termina em `início + duração`, contado do instante marcado, e não
  do passo em que foi percebido.
- **Pausa.** O diretor usa o tempo da partida: pausado, o tempo não anda e o evento não acaba.
- **Seed.** O intervalo e a escolha saem do `RandomIndex` injetado: a mesma seed repete a
  sequência.
- **Intervalo nunca zero.** O arquivo recusa efeitos de −100% ou menos, que zerariam o
  intervalo de spawn e criariam inimigos sem fim.

## Ligação no passo da partida

```php
$eventos->update($tempo);
$baus->setChanceMultiplier($eventos->chestChanceMultiplier());
$ondas->update($dt, $tempo, $nivel, $eventos->spawnCountMultiplier(), $eventos->spawnIntervalMultiplier());
// Frente B: XP do inimigo × $eventos->xpMultiplier()
```

Para a Frente D, `active()` e `activeEndsAt()` permitem mostrar o nome do evento e o tempo
que falta.

## Testes

| Arquivo | O que cobre |
| --- | --- |
| `tests/Unit/Core/Config/EventConfigLoaderTest.php` | Os três eventos reais e 8 arquivos inválidos |
| `tests/Unit/Domain/RandomEvent/EventDirectorTest.php` | Primeiro intervalo, pesos, efeitos de cada evento, fim sem resto, sem sobreposição, pausa e seed |
| `tests/Integration/RandomEventsTest.php` | Horda real multiplicando o ritmo do `WaveDirector` por 6 |
