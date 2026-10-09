# Frente A — núcleo do domínio

> O cronograma completo, as responsabilidades e os critérios de aceite estão em
> [PLANO_FRENTE_A.md](PLANO_FRENTE_A.md).

Este documento explica a primeira entrega da Frente A. Ela fornece os objetos fundamentais
usados por movimento, colisão, dano e armas, sem depender de Raylib, FFI ou entrada e saída.

## Organização

| Classe | Responsabilidade |
| --- | --- |
| `Vector2` | Posição, direção e deslocamento em duas dimensões |
| `Health` | Vida atual, vida máxima, dano, cura e morte |
| `Cooldown` | Intervalo individual entre dois usos de uma arma ou habilidade |
| `Entity` | Identidade, posição, raio de colisão e atividade de um objeto do mundo |

As três primeiras classes ficam em `src/Domain/Value/`. A entidade base fica em
`src/Domain/Entity/`. Todas pertencem ao domínio puro e podem ser testadas sem abrir uma
janela.

## Decisões importantes

### Vetores são imutáveis

Uma operação não altera o vetor existente; ela devolve outro objeto. Assim, compartilhar uma
posição entre sistemas não permite que um deles a modifique acidentalmente.

```php
$position = new Vector2(10.0, 20.0);
$nextPosition = $position->add(new Vector2(2.0, 0.0));

// $position continua sendo (10, 20); $nextPosition é (12, 20).
```

Normalizar o vetor zero devolve outro vetor zero. Essa regra evita divisão por zero quando o
jogador não fornece uma direção de movimento.

### Vida nunca sai do intervalo válido

`Health` garante `0 <= vida atual <= vida máxima`. Os métodos `damage()` e `heal()` devolvem
o valor realmente aplicado, o que permite produzir eventos e números de dano corretos.

```php
$health = new Health(20.0);
$appliedDamage = $health->damage(25.0); // 20.0

$health->isDead(); // true
```

### Verificar e iniciar o cooldown é uma operação única

`Cooldown::tryStart()` só reinicia o relógio quando o uso está disponível. Isso impede que
uma arma seja disparada duas vezes por acidente no mesmo quadro.

```php
$cooldown = new Cooldown(2.0);

if ($cooldown->tryStart()) {
    // Executa o ataque.
}

$cooldown->advance($deltaSeconds);
```

Cada arma deve possuir sua própria instância de `Cooldown`, conforme o RF20.

### A entidade base contém apenas o que é universal

Nem toda entidade possui vida ou causa dano. Por isso, `Entity` não herda `Health` nem conhece
armas. Jogadores e inimigos receberão essas capacidades por composição, evitando uma árvore
de herança profunda.

Uma entidade desativada deixa de participar das atualizações do mundo. A classe base não
oferece reativação automática, pois cada subtipo poderá exigir uma reinicialização diferente.

## Invariantes e erros

Os construtores e métodos rejeitam imediatamente valores inválidos:

- coordenadas, tempos e raios precisam ser números finitos;
- vida máxima e duração de cooldown precisam ser maiores que zero;
- dano, cura, passagem de tempo e raio de colisão não podem ser negativos;
- o identificador de uma entidade não pode estar vazio.

Falhar no ponto em que o dado inválido entra torna o erro mais simples de localizar do que
permitir que um `NaN` ou valor negativo chegue ao loop do jogo.

## Testes

Os testes estão em `tests/Unit/Domain/` e cobrem operações matemáticas, limites de vida,
controle de cooldown, movimentação da entidade e rejeição de estados inválidos.

```bash
composer test
composer stan
composer fmt:check
```

Antes de outras frentes dependerem dessas assinaturas, a equipe deve registrá-las como
aprovadas em `docs/CONTRATOS.md`.
