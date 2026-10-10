# Entrega A3 — jogador e movimentação

A A3 implementa a lógica de movimento do RF04 sobre a entrada abstrata ratificada na A1.
O passo fixo da A2 permite atualizar essa lógica independentemente da frequência de
renderização. Teclado ou mouse concretos serão fornecidos pela Frente D.

**Estado:** A3 concluída, com critérios verificados por `composer check` e `git diff --check`.
A suíte completa passa com 118 testes e 3.526 asserções; 77 execuções de teste foram
acrescentadas nesta entrega, contando os cenários parametrizados. A4 permanece não iniciada.

## Componentes e responsabilidades

| Componente | Responsabilidade |
| --- | --- |
| `Domain/Entity/Movable` | Contrato de posição, `moveTo()` e `translate()`, com as assinaturas da A1 |
| `Domain/Entity/Entity` | Base existente; agora declara `implements Movable`, sem mudar seus métodos |
| `Domain/Value/InputState` | Entrada imutável com movimento, olhar opcional e pulso de pausa |
| `Domain/Value/Facing` | Componente mutável que guarda o último olhar válido |
| `Domain/Value/MapBounds` | Retângulo imutável e configurável de posições permitidas |
| `Domain/System/MovementSystem` | Validar velocidade e tempo, aplicar movimento e atualizar o olhar |
| `Infrastructure/Input/InputInterface` | Porta abstrata com `poll(): InputState`, conforme a A1 |
| `Infrastructure/Input/ScriptedInput` | Consumir uma sequência de entradas sem hardware, para testes |
| `tests/Support/FakeMovable` | Objeto mínimo de teste que implementa somente a capacidade de movimento |

Os nomes completos começam com `Jogo\`; os componentes do domínio não importam `Core`
nem `Infrastructure`. Nenhuma classe de jogador concreto é necessária: a futura entidade
pode compor `Facing` e implementar `Movable`. O sistema depende dessa capacidade, não de
`Entity`, de personagens da B ou de entidades de conteúdo da C.

## Entrada e direção do olhar

`InputState` conserva exatamente o construtor ratificado:

```php
new InputState(
    movement: new Vector2($horizontalAxis, $verticalAxis),
    facing: $desiredFacing, // Vector2 unitário ou null.
    pauseRequested: $newPauseRequest,
);
```

Cada eixo deve ser finito e estar em `[-1, 1]`; `(0, 0)` significa repouso. `Vector2`
rejeita coordenadas não finitas, e `InputState` rejeita eixos fora da faixa. `facing` deve
ser unitário e não nulo; `null` conserva o olhar anterior. A validação de comprimento usa
tolerância técnica de `1e-9`, sem normalizar silenciosamente um olhar inválido.

`Facing::__construct(Vector2 $direction)` exige a direção inicial explicitamente.
`direction()` consulta o estado e `update(?Vector2 $direction)` o atualiza. A ausência de
movimento não apaga o olhar; uma entrada explícita pode mudar o olhar enquanto parado.
Movimento e olhar são independentes: mover para a direita não impõe olhar para a direita.
A direção inicial real e a política de tradução do olhar continuam pendentes de acordo A/D.

D implementará `InputInterface`, convertendo teclado **ou** mouse em coordenadas lógicas.
A conversão de tela para mundo pertence à D. A aplicação consulta a entrada uma vez por
quadro, trata `pauseRequested` uma vez e reutiliza os mesmos eixos nos passos desse quadro.
O domínio recebe apenas `InputState`; não consulta dispositivos nem trata telas.

`ScriptedInput::__construct(array $states)` aceita uma lista de `InputState` e rejeita
índices não sequenciais ou outros tipos. Cada `poll()` consome um estado. Quando a lista
acaba, inclusive se começar vazia, retorna movimento zero, olhar `null` e pausa `false`.
Assim, não continua andando nem repete um pulso de pausa após o roteiro. `poll()` declara
`@phpstan-impure`, pois chamadas sucessivas podem retornar entradas diferentes.

## Velocidade, tempo e normalização

```php
$movement = new MovementSystem($bounds);
$movement->update($target, $facing, $inputState, $speed, $deltaSeconds, $active);
```

`speed` é fornecida a cada atualização, em pixels do mundo por segundo. B fornecerá
futuramente o valor final calculado pelos atributos; A3 não implementa `Stats`, passivas,
progressão ou upgrades, nem escolhe velocidade oficial. Receber a velocidade por passo
permite mudar esse dado externo sem reconstruir o sistema.

Esta API rejeita velocidade negativa ou não finita. Zero permite imobilizar o alvo sem
apagar seu olhar; é uma decisão técnica da A3, não um valor base de personagem ou alteração
da validação de `Stats` prevista na A1. `deltaSeconds` deve ser finito e não negativo.
Na integração, ele vem de `GameLoop::STEP_SECONDS`, sempre `1/60 s`.

Se o vetor de movimento tiver comprimento maior que 1, o sistema o normaliza. Uma diagonal
`(1, 1)` passa a ter comprimento 1, com a mesma velocidade máxima de `(1, 0)` ou `(0, 1)`.
Entradas parciais conservam sua intensidade: `(0.3, 0.4)` tem comprimento `0.5` e aplica
metade da velocidade máxima. Depois dessa limitação:

```text
deslocamento = vetor de movimento × velocidade × dt
próxima posição = posição atual + deslocamento
```

Movimento zero, velocidade zero ou `dt` zero conservam a posição. O olhar ainda pode ser
atualizado explicitamente. Overflow no cálculo gera `InvalidArgumentException` antes de
alterar posição ou olhar; dados inválidos não são convertidos silenciosamente em zero.

## Limites e atividade

`MapBounds::__construct(float $minX, float $minY, float $maxX, float $maxY)` recebe quatro
coordenadas finitas. Cada mínimo deve ser menor ou igual ao máximo; valores iguais permitem
fixar um eixo. `clamp(Vector2 $position): Vector2` limita cada coordenada ao intervalo
fechado correspondente. A3 não define tamanho oficial, não carrega tiles nem gera mapa.

Os limites se aplicam à posição de referência de `Movable`, sem interpretar raio, sprite
ou câmera. Quem compõe a partida fornece a posição inicial dentro do intervalo permitido
e pode fornecer intervalos já ajustados para margens. Em repouso o sistema não reposiciona
um alvo colocado fora do mapa. Movimento nas bordas conserva o componente permitido do
deslocamento. Obstáculos e colisões entre entidades pertencem à A4 e não foram iniciados.

O contrato ratificado de `Movable` não inclui `isActive()`. Por isso, `update()` exige
explicitamente o argumento `active`: o chamador obtém esse dado da entidade ou da sua
composição. Para uma subclasse de `Entity`, passa `$entity->isActive()`. Quando `false`,
nem posição nem olhar mudam. A validação de velocidade e `dt` ocorre mesmo nesse caso.

## Composição com o loop fixo

Este exemplo mostra a ligação entre colaboradores já fornecidos pelo chamador. `$speed`
e `$active` são dados externos atualizados antes de cada quadro; `$bounds`, `$player`,
`$facing`, `$clock`, `$input` e `$render` também são fornecidos pela composição da partida.
Os valores reais não estão embutidos no sistema.

```php
use Jogo\Core\GameLoop;
use Jogo\Domain\System\MovementSystem;
use Jogo\Domain\Value\InputState;
use Jogo\Domain\Value\Vector2;

$movement = new MovementSystem($bounds);
$inputState = new InputState(Vector2::zero());
$loop = new GameLoop(
    $clock,
    update: static function (float $dt) use (
        $player, $facing, $movement, &$inputState, &$speed, &$active,
    ): void {
        $movement->update($player, $facing, $inputState, $speed, $dt, $active);
    },
    render: $render, // Somente leitura/apresentação, sem mover entidades.
);

// Repetir uma vez por quadro na futura aplicação, sem consultar a entrada em render().
$inputState = $input->poll();
if ($inputState->pauseRequested) {
    if ($loop->isPaused()) {
        $loop->resume();
    } else {
        $loop->pause();
    }
}
$loop->tick();
```

A alternância acima é um exemplo de composição de pedido manual, não uma implementação
de menu nem uma nova exigência do RF04. A pausa de level up será solicitada por B/D.
Enquanto pausado, o loop não chama `update()`: movimento e olhar ficam congelados, sem
acumular o intervalo pausado. A apresentação pode continuar lendo o estado.

Movimento ocupa o passo 2 da ordem documentada em [LOOP_FIXO.md](LOOP_FIXO.md), antes do
futuro índice espacial. O loop continua independente dos sistemas; A3 não altera sua API.

## Testes e alcance do determinismo

Os testes unitários cobrem eixos e diagonais nas quatro direções, repouso, entrada parcial,
velocidade × tempo, troca externa de velocidade, limites mínimos/máximos, deslocamento
nas bordas, olhar independente, direção inicial explícita, atividade e valores inválidos.
Há testes próprios de `InputState`, `Facing`, `MapBounds` e da sequência `ScriptedInput`.

`MovementLoopTest` comprova uso de `1/60 s`, reutilização da entrada do quadro, renderização
sem movimento e pausa/retomada. Compara **todo o percurso**, incluindo posição e olhar após
cada um dos 120 passos, em 30, 60, 144 e 240 FPS e em quadros irregulares. O roteiro inclui
diagonais, limites, repouso e olhar; os resultados são exatamente iguais.

Nesse teste, o roteiro é consumido por passo lógico para fornecer a mesma sequência lógica
em todos os cenários. Isso não muda a consulta por quadro do contrato de entrada real.
Eventos reais amostrados em momentos diferentes podem produzir sequências lógicas diferentes;
não se promete igualdade nesse caso nem quando o loop descarta tempo por excesso de atraso.
`A3ArchitectureTest` verifica os tokens do domínio, entrada abstrata e loop para impedir
dependências gráficas e operações de I/O, além de impedir importações de infraestrutura
ou `Core` pelo domínio.

```bash
composer check
git diff --check
```

Permanecem pendentes os valores oficiais de velocidade e limites, controle concreto e
política/direção inicial de olhar. São dados de integração externa, não bloqueios da A3.
Os marcadores de participantes B/C/D e todos os valores do enunciado foram preservados.
