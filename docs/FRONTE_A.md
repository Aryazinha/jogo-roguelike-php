# Frente A — núcleo do domínio

> O cronograma completo, as responsabilidades e os critérios de aceite estão em
> [PLANO_FRENTE_A.md](PLANO_FRENTE_A.md).

Este documento explica a base já implementada da Frente A e a preparação documental da A1.
A base fornece os objetos fundamentais usados por movimento, colisão, dano e armas, sem
depender de Raylib, FFI ou entrada e saída.

## Entrega A1 — contratos preparados para revisão

Em 09/10/2026, [CONTRATOS.md](CONTRATOS.md) foi revisado para registrar as assinaturas reais
de `Entity`, `Vector2`, `Health` e `Cooldown` e propor os contratos que ainda não têm código:

- estado de entrada e `InputInterface`, em integração com D;
- `Stats`, capacidades pequenas e `StatModifier`, em integração com B/C;
- `TargetingStrategy` e resultados de seleção, em integração com B/C;
- eventos de dano e morte e porta do barramento, em integração com B/C/D;
- `WorldView` com cópias imutáveis de leitura, em integração com C;
- estrutura de `weapons.json`, unidades, validação e referências usadas por B/A.

Essa revisão distingue código existente de propostas e registra as responsabilidades e
decisões pendentes. Não altera `src/`, testes, configuração ou funcionalidades de B, C e D.
Não implementa A2 a A10 nem declara ratificação pela equipe.

**Pendência de aceite:** reunir A/B/C/D e registrar participantes, data e assinaturas
efetivamente aprovadas em `CONTRATOS.md` §9 e no histórico. A data da revisão documental não
é uma data de aprovação. As propostas novas não devem ser tratadas como APIs disponíveis.

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

Antes de outras frentes dependerem dessas assinaturas, a equipe deve confirmar os contratos
em `docs/CONTRATOS.md`. A revisão da A1 registra a base existente, mas mantém essa confirmação
pendente. Para validar a base sem modificar sua formatação, execute `composer check`.
