# Frente A — núcleo do domínio

> O cronograma completo, as responsabilidades e os critérios de aceite estão em
> [PLANO_FRENTE_A.md](PLANO_FRENTE_A.md).

Este documento explica a base já implementada da Frente A e os contratos ratificados da A1.
A base fornece os objetos fundamentais usados por movimento, colisão, dano e armas, sem
depender de Raylib, FFI ou entrada e saída.

## Entrega A10 — concluída no escopo da Frente A

A1–A10 estão concluídas. `Application/Benchmark` conecta os sistemas reais das entregas
anteriores, entrada programada por passo, jogador/inimigos sintéticos, movimento/olhar/limites,
grade/colisão, quatro armas, dano/eventos, modificadores/evoluções, pool e limpeza.
Não há conteúdo B/C ou apresentação D reais nesta main; suas integrações permanecem externas.

`composer benchmark` executa a carga oficial da A10: seed 20261012, 600 passos de aquecimento,
1.800 medidos, cinco repetições, 300 inimigos sustentados e pico exatamente 200 projéteis.
Na configuração Windows/PHP 8.5.11 sem OPcache/JIT ativo ou uso de FFI, p95 agregado
8,9303 ms, pior p95 individual 9,7013 ms e máximo 5,209302326 testes/entidade/passo.
Checksum igual nas cinco execuções e na baseline. O máximo temporal observado é publicado
no relatório; aceite usa p95, sem descartar amostras.

Duas otimizações comprovadas: preparação explícita do índice após movimento e reutilização
de consultas exatas dentro dessa preparação. Invalidação ocorre após mutações e ao encerrar;
o modo padrão da visão conserva consultas frescas. Instrumentação opcional conta geometria
da grade, visão, miras, áreas, contatos e trajetórias; relógio real fica fora do domínio.

38 cenários A10 adicionados. `composer check` e suíte completa com FFI desabilitada:
**907 testes, 12.301 asserções**, Pint e PHPStan nível 9 aprovados. `composer sim`,
`composer benchmark` e `git diff --check` aprovados. Configurações e marcadores B/C/D preservados.
[DESEMPENHO_A10.md](DESEMPENHO_A10.md) contém cenário, comandos, ambiente, resultados,
reprovações intermediárias, decisões e pendências. Não houve nova ratificação humana.

**Meta visual de 50 FPS pendente de validação conjunta com a Frente D.**

| Consumidor | APIs da A disponíveis | Responsabilidade externa |
| --- | --- | --- |
| B | `EnemyKilled`/`PlayerDied`, `CombatInventory`, `Stats`, `WeaponProgressionService` | Jogador real, atributos, concessão/elegibilidade, XP/níveis/pontuação/persistência |
| C | `Movable`, `Collidable`, `Damageable`, movimento/grade/colisão/dano, `WorldEntityProvider`, `WorldView`, resolver/contexto de ataques | Conteúdo, IA, ondas, entidades/metadados e política de contato/invulnerabilidade |
| D | `InputInterface`/`InputState`, `Facing`, loop/pausa/interpolação, snapshots de mundo/combate e eventos | Entrada real, estados, renderer/áudio/feedback; copiar projéteis antes de liberação/reutilização |

## Entrega A9 — concluída: pool e simulação sem janela

`ObjectPool<T>` pré-aloca identidades distintas, reserva lotes atomicamente e limpa estado
na liberação. `ProjectilePool` limita a 200 ativos. Rajada sem espaço é recusada por inteiro,
sem consumir cooldown ou ID. Projéteis consumidos retornam após dano/eventos do passo.

`LogicalProjectile` conserva trajetória/snapshot enquanto adquirido e elimina referências
ao retornar. Testes comprovam reutilização por `spl_object_id()`, reset da correção numérica,
referências fracas, capacidade, rollback e comportamento determinístico com pool cheio.

`composer sim` usa armas reais, alvos sintéticos, entrada neutra, callback de render nulo e
loop de 1/60 s. Relatório separa métricas lógicas/checksum de medição real injetável.
[OBJECT_POOL_SIMULACAO.md](OBJECT_POOL_SIMULACAO.md) explica APIs, CLI, decisões técnicas
e limites. Nenhum conteúdo B/C/D ou JSON foi alterado. A10 está descrita acima.
73 cenários adicionados; suíte completa com 869 testes e 9.350 asserções, inclusive com
FFI desabilitado. Pint, PHPStan nível 9, `composer sim` e `git diff --check` aprovados.

## Entrega A8 — concluída: modificadores e evoluções

`Stats`, `StatModifier` e `ModifierType` materializam A1; o resolvedor parte sempre da base,
ordena efeitos por `source`, soma FLAT e multiplica fatores PERCENT. Conversão centralizada
preserva campos aplicáveis e rejeita frações de projéteis e resultados inválidos sem clamp.

`WeaponProgressionService` aplica comandos concedidos pela B por slot, sem níveis ou
elegibilidade. Aplicação/remoção são atômicas; cooldown mantém sua proporção restante.
Evolução conserva slot/executor, troca a base/ID e descarta efeitos da arma anterior.
Enchanted Stone Sword e Mega TNT reutilizam A7/A5; ataques existentes preservam snapshots.

Loaders validam os 16 upgrades e as duas evoluções, com metadados/referências/diagnósticos.
`composer check` passou com 796 testes e 8.722 asserções, Pint e PHPStan nível 9.
[MODIFICADORES_EVOLUCOES.md](MODIFICADORES_EVOLUCOES.md) documenta comandos, decisões técnicas
e limites. JSONs e marcadores da equipe preservados. A9/A10 estão descritas acima.

## Entrega A7 — concluída: miras e execução lógica das armas

Os contratos `TargetingStrategy`, contexto/resultado, `WorldView` e `EntityView` foram
materializados preservando as assinaturas da A1. As quatro miras filtram snapshots atuais;
empates usam ID, e o sorteio usa gerador privado com seed externa obrigatória.

`WeaponAttackSystem` é a porta invocável da A6: executa setor de espada, projéteis do arco,
cargas temporizadas de TNT e pulso da tocha. Usa a grade A4, colisão de segmento e círculo,
porta separada para resolver alvos e `DamageSystem` da A5. IDs de ataque evitam dano duplicado.
Arco/TNT recusam sem alvo; espada/tocha executam sua geometria e consomem cooldown.
Essas são decisões técnicas da A7, sem nova ratificação humana.

[MIRAS_ARMAS.md](MIRAS_ARMAS.md) detalha dependências, geometria, ordem e integração com C.
93 cenários adicionados; `composer check` e suíte completa com FFI desabilitado passaram
com 635 testes e 8.061 asserções, Pint e PHPStan nível 9. Os testes comparam todo o estado
e eventos em 30/60/144/240 FPS e quadros irregulares e comprovam pausa do combate.
Configurações e marcadores da equipe preservados. A8/A9/A10 estão descritas acima.

## Entrega A6 — concluída: armas e inventário de combate

`WeaponDefinition` e `WeaponCatalog` são finais readonly. `Core/Config` lê, decodifica e
valida as quatro definições existentes, com diagnóstico por arquivo/ID/campo e detecção
de chaves duplicadas. `config/weapons.json` e seus metadados permanecem intactos.

Cada `Weapon` cria seu próprio `Cooldown` e tenta automaticamente uma execução por update.
O callback retorna aceitação: somente `true` inicia o cooldown. `CombatInventory` tem dois
slots por padrão, consultas, adição e substituição com ordem determinística e IDs únicos.
A soma compensada em `Cooldown` evita um passo extra por resíduos, sem mudar sua API.

[ARMAS_INVENTARIO_COMBATE.md](ARMAS_INVENTARIO_COMBATE.md) documenta APIs e decisões.
234 cenários adicionados; `composer check` passou com 542 testes e 7.590 asserções, Pint
e PHPStan nível 9. Integração sem FFI: 21 testes e 2.686 asserções. Os testes comprovam
independência dos cooldowns, pausa/retomada e sequências iguais em 30/60/120/144/240 FPS.
Na A6, não houve mira, arma concreta ou dano de arma. A7 está descrita acima;
progressão e apresentação permanecem com B/D.

## Entrega A5 — concluída: dano, morte e eventos

`DamageSystem` recebe `Entity&Damageable` e `Damage` imutável, aplica o HP efetivo retornado
por `Health::damage()` e publica `EnemyHit`/`PlayerDamaged` antes de desativar e publicar
`EnemyKilled`/`PlayerDied`. O contrato de `Damageable` permanece inalterado. Um registro por
ID impede mortes repetidas na partida, inclusive antes dos listeners do golpe fatal.

`Core/EventBus` é síncrono, por classe exata e ordem de inscrição, sem estado global. Barramento
e sistema são criados novamente em cada partida. Os quatro eventos são finais readonly,
com posição e tempo de simulação em snapshots, origem opcional e classificação de boss externa.
Erros de listeners são propagados; não há replay nem rollback, e golpe fatal ainda desativa.

[DANO_MORTE_EVENTOS.md](DANO_MORTE_EVENTOS.md) documenta APIs, ordem, validações, falhas e
integração futura. Foram adicionados 127 cenários; `composer check` passou com 308 testes,
6.559 asserções, Pint e PHPStan nível 9 sem erros; `git diff --check` passou.
Na A5, nenhum consumidor B/C/D ou arma foi implementado. A6 está descrita acima.

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
Esses resultados são da integração da A4; A5 está descrita acima.

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
