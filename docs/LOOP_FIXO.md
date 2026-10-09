# Entrega A2 — loop de tempo fixo

A A2 fornece o agendamento das atualizações e da renderização. Ela sustenta o passo fixo
previsto em `PLANO.md` §3.5 para RNF06 e a base de atualização de RF03; também permite
interromper a ação conforme RF14. Não implementa movimento, combate, progressão ou telas.

## Componentes

| Arquivo | Responsabilidade |
| --- | --- |
| `src/Core/GameLoop.php` | Acumular tempo, executar passos fixos, limitar atraso e controlar pausa |
| `src/Core/ClockInterface.php` | Fornecer timestamps monotônicos em segundos |
| `src/Infrastructure/Time/MonotonicClock.php` | Adaptar `hrtime(true)` de nanossegundos para segundos |
| `tests/Support/ManualClock.php` | Permitir que os testes controlem o tempo sem espera real |

O loop recebe um relógio, um callback `update(float $deltaSeconds): void` e um callback
`render(float $alpha): void`. Converte os callbacks em `Closure`; não importa sistemas
concretos, entidades, Raylib ou FFI. O domínio não precisa conhecer o loop ou o relógio.

```php
use Jogo\Core\GameLoop;
use Jogo\Infrastructure\Time\MonotonicClock;

$loop = new GameLoop(
    new MonotonicClock,
    update: static function (float $deltaSeconds): void {
        // A futura composição de sistemas atualiza o mundo com $deltaSeconds.
    },
    render: static function (float $alpha): void {
        // A futura apresentação lê o estado e interpola com $alpha.
    },
);

$loop->tick(); // Uma chamada por quadro pelo chamador; não abre janela.
```

Não há `run()` infinito, espera ou limite de FPS de renderização. Application poderá
controlar seu próprio ciclo de janela; o loop apenas processa um quadro por `tick()`.
Um teste ou simulador pode chamá-lo sem apresentação real usando um callback vazio.

## API e unidades

| API pública de `GameLoop` | Significado |
| --- | --- |
| `STEP_SECONDS = 1.0 / 60.0` | Duração constante de cada atualização lógica |
| `DEFAULT_MAX_UPDATES_PER_FRAME = 5` | Limite técnico padrão, não valor do enunciado |
| `__construct(ClockInterface $clock, callable $update, callable $render, int $maxUpdatesPerFrame = 5)` | Injeta as dependências, valida limite `>= 1` e captura a referência inicial do relógio |
| `tick(): int` | Atualiza e renderiza um quadro; retorna o número de atualizações realizadas |
| `pause(): void` | Suspende atualizações e conserva a fração de passo |
| `resume(): void` | Retoma com nova referência do relógio, sem recuperar tempo de pausa |
| `isPaused(): bool` | Consulta a pausa; começa `false` |
| `elapsedSeconds(): float` | Número de passos realizados × `STEP_SECONDS` |

`ClockInterface::now(): float` devolve segundos finitos, não negativos e que não retrocedem.
A origem pode ser qualquer uma: o loop usa diferenças de timestamps, não data civil.
Valor inválido gera `UnexpectedValueException` antes de atualização/renderização; limite de
atualizações inválido gera `InvalidArgumentException`. Exceções dos callbacks são propagadas.
`tick()` e a leitura do relógio declaram `@phpstan-impure`: seus resultados podem mudar em
chamadas consecutivas. Essa anotação descreve os efeitos, conforme a
[documentação do PHPStan](https://phpstan.org/blog/remembering-and-forgetting-returned-values),
sem desativar verificações ou presumir que essas operações sejam consultas puras.

## Um quadro, em ordem

1. O chamador trata entrada e pedidos de tela/pausa uma vez, fora do loop. A entrada real
   pertence à D e ainda não é implementada.
2. `tick()` lê o relógio e calcula a diferença desde a última referência.
3. Se ativo, adiciona ao acumulador no máximo `maxUpdatesPerFrame × STEP_SECONDS` de tempo
   novo. Excedente é descartado; a fração já acumulada permanece.
4. Enquanto houver um passo completo, chama `update(STEP_SECONDS)`, até o limite por quadro.
   Zero, uma ou várias atualizações são possíveis; nenhum passo recebe o tempo variável do quadro.
5. Chama `render(alpha)` uma vez, depois das atualizações, inclusive quando pausado.
   `alpha = tempo residual / STEP_SECONDS`, em `[0, 1)`, serve para interpolação visual.

A pequena tolerância interna de `1e-9 s` compensa arredondamentos ao comparar o acumulador
com o passo. Ela não muda o `deltaSeconds` entregue aos sistemas. O tempo de simulação é
calculado a partir da contagem de passos, evitando somar o mesmo float indefinidamente.
Não chamar `tick()` recursivamente dentro dos callbacks nem modificar regras de jogo em `render()`.

## Limite de atraso

O padrão de cinco atualizações por quadro é uma decisão técnica ajustável pelo construtor,
sem alterar o timestep ou os JSON. Depois de uma interrupção longa, o loop processa no máximo
esse orçamento; não mantém uma dívida de segundos para os quadros seguintes. Assim uma
máquina atrasada não entra em um ciclo de tentar recuperar um atraso cada vez maior.

O limite protege o agendamento, mas não mede ou garante sozinho as metas de desempenho da
A10. Com descarte de atraso, o tempo de simulação avança menos que o tempo real.
`elapsedSeconds()` conta somente passos executados; a política do tempo registrado da
partida pela B continua pendente em `CONTRATOS.md` §9 e não é decidida por este getter.

## Pausa e retomada

`pause()` e `resume()` são idempotentes. Pausado, o mundo não recebe `update()`, o contador
de passos fica congelado e a renderização continua com a mesma fração. O cooldown do teste
fica congelado porque só é avançado em `update()`; a integração real dos cooldowns é futura.

Retomar descarta o intervalo real de pausa mesmo se nenhum `tick()` tiver ocorrido durante
ela. A fração menor que um passo é conservada, permitindo completar esse passo depois da
retomada. Se `update()` chamar `pause()`, as demais atualizações desse quadro são interrompidas;
passos inteiros que ainda estavam pendentes são descartados e somente a fração é preservada.
Isso permite que uma futura solicitação de level up suspenda a ação dentro do quadro.

A2 define essa política apenas para o agendador e seu tempo de simulação. Não cria tela de
pausa, `GameState`, regeneração, estatísticas ou relógio de sobrevivência.

## Ordem de atualização dos sistemas na integração futura

O callback de atualização será composto pelo chamador. A ordem abaixo documenta as
dependências para essa futura composição; A2 não instancia nem implementa esses sistemas.

| Ordem dentro de cada passo | Operação prevista | Responsabilidade / entrega |
| --- | --- | --- |
| 1 | Usar o estado de entrada já recebido; obter intenções de IA pela visão de leitura | A3 e C |
| 2 | Aplicar movimento às entidades a partir das intenções | A3 e integração com C |
| 3 | Atualizar o índice espacial para as posições atuais | A4 |
| 4 | Avançar cooldowns e executar armas/miras automáticas | A6/A7 |
| 5 | Atualizar projéteis e detectar contatos/impactos nas posições atuais | A4/A7; atualizar o índice se houver movimento adicional |
| 6 | Aplicar dano, desativar mortos e publicar eventos | A5 |
| 7 | Processar consequências de progressão/conteúdo em ponto seguro, fora da iteração de combate | B/C; protocolo ainda pendente |
| 8 | Encerrar o passo; renderizar só depois dos passos do quadro | A2 e futura apresentação D |

Se uma consequência solicitar pausa, os próximos passos não executam. Eventos, mutações
seguras e integração final dessa sequência continuam nas entregas e contratos correspondentes.

## Evidência de determinismo e verificações

`GameLoopTest` compara toda a sequência de estados após 120 passos (2 segundos simulados)
em 30, 60, 144 e 240 FPS, além de quadros irregulares. As entradas são indexadas pelo passo
lógico e o cenário reutiliza `Health` e `Cooldown`; todos os estados e os valores de cooldown
são idênticos. Não é uma implementação de movimento, arma ou progressão.

O critério vale para a mesma sequência lógica, sem descarte de atraso. Se o limite for
excedido, o comportamento esperado é descartar tempo; o teste de atraso verifica isso
separadamente. A transformação de entrada real em entradas lógicas será integrada por A/D.

Os testes também cobrem frações, múltiplos passos, ordem update/render, uma renderização por
quadro, pausa com/sem quadros intermediários, pausa durante update, relógio com origem
arbitrária e rejeição de relógio/limite inválidos. O adaptador monotônico tem teste próprio.

```bash
composer check
git diff --check
```

A2 termina nesta base testada. A3 não está implementada; valores do enunciado e configurações
permanecem preservados.
