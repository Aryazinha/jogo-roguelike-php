# Frente A — núcleo do domínio

> O cronograma completo, as responsabilidades e os critérios de aceite estão em
> [PLANO_FRENTE_A.md](PLANO_FRENTE_A.md).

Este documento explica a base já implementada da Frente A e os contratos ratificados da A1.
A base fornece os objetos fundamentais usados por movimento, colisão, dano e armas, sem
depender de Raylib, FFI ou entrada e saída.

## Entrega A4 — concluída: colisão circular e grade espacial

`CollisionSystem` confirma interseções circulares, incluindo tangência, sem aplicar dano
ou resposta física. `SpatialHash` indexa os círculos em células padrão de 64 px, incluindo
suas bordas, e oferece consultas das nove células e por interseção com uma área circular.
`Entity` implementa `Collidable` com seus métodos existentes. Inativos são ignorados, IDs
duplicados são rejeitados e resultados têm ordem por ID, sem repetição.

Cada consulta fornece candidatos únicos, quantidade devolvida e células visitadas.
O cenário de 300 entidades falsas tem médias de 29,24 candidatos nas nove células e
13,1867 na área circular; máximos de 35 e 15. Outra comparação usa um oráculo independente
e a integração com A2/A3 reconstrói a grade depois do movimento de cada passo.

[COLISAO_GRADE_ESPACIAL.md](COLISAO_GRADE_ESPACIAL.md) documenta APIs, geometria, orçamento
técnico, responsabilidades, métricas e cenários. `composer check` passou com 181 testes,
6.161 asserções, Pint e PHPStan nível 9 sem erros; `git diff --check` passou.
A5 permanece não iniciada.

## Entrega A3 — concluída: movimentação por entrada abstrata

`MovementSystem` recebe `Movable`, `InputState`, o componente `Facing`, velocidade externa
em px/s, `dt` em segundos e atividade explícita. Limita a magnitude do movimento a 1,
aplica velocidade × tempo e restringe a posição a `MapBounds` fornecido pelo chamador.
O olhar inicial é explícito e `null` conserva o olhar anterior, inclusive em repouso.

`Entity` passa a implementar a capacidade ratificada `Movable`, sem alterar seus métodos.
Não foi necessário criar `Player` concreto. `InputInterface` conserva a assinatura da A1;
`ScriptedInput` fornece roteiros de teste. D fornecerá a entrada real, B fornecerá a
velocidade final, e a composição fornecerá os limites reais, sem definir esses valores aqui.

[MOVIMENTACAO.md](MOVIMENTACAO.md) explica as APIs, decisões técnicas e ligação ao callback
de atualização de `GameLoop`. Testes comparam o percurso completo em 30/60/144/240 FPS e
quadros irregulares e verificam pausa, limites, olhar, atividade e isolamento do domínio.
`composer check` passou com 118 testes e 3.526 asserções, Pint e PHPStan nível 9 sem erros;
`git diff --check` passou na integração da A3. A4 está descrita acima.

## Entrega A2 — loop de tempo fixo

`Jogo\Core\GameLoop` agenda atualizações de `1/60 s` com relógio injetável, acumulador,
limite de recuperação, pausa/retomada e callbacks separados de atualização e renderização.
A apresentação é chamada uma vez por quadro e recebe a fração de interpolação; o mundo
recebe apenas passos fixos. A pausa congela a simulação sem acumular o intervalo pausado.

O adaptador `MonotonicClock` usa o relógio monotônico do PHP; testes usam `ManualClock`, sem
espera real. [LOOP_FIXO.md](LOOP_FIXO.md) detalha API, unidades, política de atraso, sequência
de integração futura e testes de determinismo em diferentes taxas de quadros.

A A2 fornece somente o agendamento; a composição com movimento da A3 está documentada em
[MOVIMENTACAO.md](MOVIMENTACAO.md). Entrada real e apresentação continuam com D.

## Entrega A1 — concluída e ratificada

Em 09/10/2026, [CONTRATOS.md](CONTRATOS.md) foi revisado para registrar as assinaturas reais
de `Entity`, `Vector2`, `Health` e `Cooldown` e propor os contratos que ainda não têm código:

- estado de entrada e `InputInterface`, em integração com D;
- `Stats`, capacidades pequenas e `StatModifier`, em integração com B/C;
- `TargetingStrategy` e resultados de seleção, em integração com B/C;
- eventos de dano e morte e porta do barramento, em integração com B/C/D;
- `WorldView` com cópias imutáveis de leitura, em integração com C;
- estrutura de `weapons.json`, unidades, validação e referências usadas por B/A.

As quatro frentes aprovaram as assinaturas, unidades, responsabilidades e políticas definidas
em 09/10/2026, conforme confirmação humana: Thales (A), [NOME B] (B), [NOME C] (C) e [NOME D]
(D). Os marcadores foram preservados como informados; os nomes completos B/C/D estão pendentes.

**Aceite da A1 concluído; A2 liberada.** A ratificação está registrada em `CONTRATOS.md` §9
e no histórico. Os pontos explicitamente indefinidos continuam pendentes. A ratificação é
documental: não cria APIs no autoload nem implementa A2 a A10.

## Organização

| Classe | Responsabilidade |
| --- | --- |
| `Vector2` | Posição, direção e deslocamento em duas dimensões |
| `Health` | Vida atual, vida máxima, dano, cura e morte |
| `Cooldown` | Intervalo individual entre dois usos de uma arma ou habilidade |
| `Entity` | Identidade, posição, raio de colisão e atividade de um objeto do mundo |

As três primeiras classes da tabela ficam em `src/Domain/Value/`. A entidade base fica em
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

Os contratos da A1 estão ratificados em `docs/CONTRATOS.md`; consultar suas ressalvas antes de
integrar cada sistema. Para validar a base sem modificar sua formatação, execute `composer check`.
