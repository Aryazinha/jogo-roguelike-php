# A7 — miras e execução lógica das quatro armas

A7 concluída, com execução sem janela e integração com A4/A5/A6. As políticas abaixo são
**decisões técnicas da implementação A7**, conforme a tarefa autorizada; não representam
nova ratificação da equipe nem requisitos adicionais do professor. A8 está em
[MODIFICADORES_EVOLUCOES.md](MODIFICADORES_EVOLUCOES.md); A9/A10 não iniciadas.
Nenhum valor ou metadado de `config/weapons.json` foi alterado.

## Arquitetura e dependências

| Componente | Local | Responsabilidade |
| --- | --- | --- |
| `WorldView`, `EntityView` | `Domain/Ai` | Contratos ratificados de leitura e snapshots finais readonly |
| `WorldEntityProvider`, `WorldEntity` | `Domain/Ai` | Porta de dados atuais e metadados fornecidos pela C |
| `SpatialWorldView` | `Domain/Ai` | Adaptar a grade da A4 para leitura por centros e candidatos de colisão |
| `TargetingStrategy`, `TargetingContext`, `TargetingResult` | `Domain/Weapon/Targeting` | Contratos ratificados de seleção |
| `FacingTargeting`, `NearestEnemyTargeting`, `RandomEnemyTargeting`, `SurroundingTargeting` | `Domain/Weapon/Targeting` | Quatro estratégias, sem dano ou mutação do mundo |
| `TargetCandidates` | `Domain/Weapon/Targeting` | Filtragem e ordenação internas compartilhadas |
| `RandomIndex`, `SeededRandomIndex` | `Domain/Random` | Sorteio injetável com estado privado e seed obrigatória |
| `AttackContextProvider` | `Domain/Weapon` | Origem e olhar atuais, com alcance recebido da definição |
| `EnemyTargetResolver`, `EnemyTarget` | `Domain/Weapon` | Resolver ID para `Entity&Damageable` e metadados mínimos de dano |
| `ProjectileCollisionView` | `Domain/Weapon` | Consulta de candidatos de leitura para o trecho percorrido |
| `Attack`, `AttackGeometry` | `Domain/Weapon` | Identidade, deduplicação e setor centrado no olhar |
| `LogicalProjectile`, `TntCharge` | `Domain/Weapon` | Trajetória e temporizador lógicos, sem apresentação ou pool |
| `WeaponAttackSystem` | `Domain/System` | Porta invocável da A6 e coordenação de ataques, impactos e dano |
| `CollisionSystem::segmentCircleFirstHit()` | `Domain/System` | Extensão compatível da A4: primeiro contato entre segmento e círculo |

As APIs da A6 permanecem intactas. `WeaponAttackSystem` é o callback
`callable(WeaponDefinition): bool` que cada arma recebe; `Weapon` continua criando seu
próprio `Cooldown`. O sistema recebe interfaces de contexto, mundo, colisão, resolução de
alvos e aleatoriedade, além do `DamageSystem` da partida. Não conhece jogador ou inimigo
concreto, relógio real, configuração em disco, entrada, Raylib, FFI ou renderização.

## Visão de leitura e integração com C

`EntityView` preserva exatamente os campos da A1: ID, posição, raio, categoria e vida
opcional. Posição é `Vector2` imutável; não há `Entity`, `Health` ou callback mutável nesse
snapshot. Categorias válidas continuam `player`, `enemy`, `projectile`, `chest`, `pickup`
e `breakable`. Dados inválidos falham com `InvalidArgumentException`.

`WorldEntityProvider::entities()` fornece entradas atuais, com `Collidable`, categoria e
`isAlive`. A C será responsável pela implementação concreta e pelos metadados. Atividade
é lida de `Collidable`; metadados de vida devem refletir o estado no momento da consulta.
`WorldEntity` pertence a essa fronteira interna e nunca é retornada por `WorldView`.

`SpatialWorldView` recebe esse provider, um callback da posição do jogador e uma grade da
A4, opcionalmente configurável. Reconstrói a grade a cada consulta, usando posições atuais,
ignorando inativos e rejeitando IDs duplicados. Isso prioriza correção e evita snapshots
antigos entre movimento, seleção e dano. Medição/otimização dessa reconstrução fica para
A10; não se afirma uma meta de FPS ou custo ainda não medida.

`neighbors($center, $radius)` filtra a consulta circular da A4 novamente por **distância
entre centros**, incluindo a fronteira. Um círculo que toca a área com seu centro fora
não é vizinho. Raio zero pode retornar entidades na mesma posição. Resultado vazio é `[]`,
sem IDs repetidos e em ordem lexicográfica, inclusive IDs textuais como `"10"` e `"2"`.
Entidades ativas mortas podem aparecer na visão, com `isAlive=false`; as miras as filtram.

`ProjectileCollisionView::candidates($start, $end)` é uma porta separada. O adaptador usa
o círculo que envolve o segmento como fase ampla na grade, incluindo os raios das entidades.
Entrega snapshots, e a fase estreita confirma cada contato. Não muda a semântica de
`WorldView` nem fornece escrita à IA.

Para aplicar dano, `EnemyTargetResolver::resolve($id)` retorna `EnemyTarget` ou `null`.
O alvo contém `Entity&Damageable`, `enemyTypeId` não vazio e `isBoss` explícito. O coordenador
confere identidade, atividade e vida novamente antes do dano; ID desaparecido ou morto é
ignorado. Identidade diferente da solicitada é erro de integração (`LogicException`).
A resolução não fica disponível nas estratégias ou snapshots.

## Miras, validação e seed

`TargetingContext` valida alcance finito > 0 e olhar unitário não nulo, com a mesma margem
numérica de `1e-9` da A3. Coordenadas finitas são garantidas por `Vector2`. Não normaliza
silenciosamente um olhar inválido. `TargetingResult` exige direção zero ou unitária e ID
opcional não vazio; alvo individual exige direção não nula.

| Mira | Resultado |
| --- | --- |
| Olhar | Direção do olhar; posição = origem + direção × alcance; sem ID individual |
| Mais próximo | Inimigo vivo em alcance; menor distância, empate por ID lexicográfico |
| Aleatória | Inimigo vivo sorteado após ordenar candidatos por ID |
| Área ao redor | Direção zero; posição = origem; ID `null` |

Miras individuais filtram `kind=enemy`, `isAlive=true` e centros dentro do alcance. Sem
candidato retornam `null`. Para alvo exatamente na origem usam o olhar, evitando direção
zero. A posição selecionada é uma fotografia; nenhuma mira implementa homing. Estratégias
não causam dano, não consomem cooldown e não alteram entidades.

`SeededRandomIndex($seed)` usa `Randomizer`/`Mt19937` do PHP padrão, com estado privado por
instância. A seed é obrigatoriamente fornecida pela composição; não usa horário, `rand()`,
`mt_rand()` ou estado global. `nextIndex($count)` exige quantidade positiva. Sem candidato,
nenhum sorteio é consumido. Mesma seed, mesmo conjunto e mesma sequência de chamadas
reproduzem os mesmos alvos, mesmo que a ordem recebida varie. `ControlledRandomIndex` existe
somente nos testes. A seed real da partida continua sendo um dado externo, sem valor oficial
inventado aqui.

## Geometria e execução

Todas as medidas usam pixels do mundo, HP, segundos e graus já documentados nos contratos.
Alcance, ângulo e raio de explosão conservam seus significados distintos.

| Arma | Execução lógica | Tentativa sem alvo |
| --- | --- | --- |
| Stone Sword | Setor centrado no olhar, usando alcance e abertura total `angleDegrees` | Aceita e consome cooldown |
| Bow | Projétil pontual na origem, direção inicial até o inimigo mais próximo | Recusa, sem consumir cooldown |
| TNT | Carga na posição sorteada, detonação após o atraso, explosão com `explosionRadius` | Recusa, sem consumir cooldown |
| Torch | Pulso radial na origem com `range` | Aceita e consome cooldown |

Espada, explosão e pulso usam os centros dos inimigos, com bordas inclusivas; raio de colisão
não aumenta essas áreas. O setor inclui origem, limite de alcance e ambas as bordas angulares.
Uma margem angular de `8 × PHP_FLOAT_EPSILON × max(1, meioÂngulo)` compensa somente o
arredondamento das funções trigonométricas; não aumenta alcance ou raios.

O arco move cada projétil por `projectileSpeed × dt`, com distância acumulada por soma
compensada, posição calculada a partir da origem e direção fixas, e término no alcance
máximo. Não há raio de flecha configurado: ela é um ponto lógico, sem número de balanceamento
inventado. O contato com o círculo do inimigo usa o segmento completo percorrido no passo,
evitando atravessar alvos pequenos. Tangência e último ponto do alcance são incluídos.
Primeiro contato vence; empate de fração no segmento usa ID lexicográfico. O projétil é
consumido nesse primeiro impacto válido, sem atravessar outros inimigos.

`projectileCount` cria essa quantidade de projéteis com a mesma origem e direção, sem
dispersão fictícia. Os irmãos compartilham o ID e registro do ataque. Podem produzir no
máximo um dano por inimigo nesse ataque; contato com alvo já registrado ainda consome o
projétil. Essa política mínima está testada com contagem 3, exclusivamente em uma definição
de teste. A configuração real do arco continua com quantidade 1.

A TNT não implementa viagem ou perseguição: a carga aguarda na posição selecionada. Uma
morte ou movimento posterior do alvo original não cancela nem desloca a explosão. Cada
carga possui temporizador independente, baseado em `dt`; atraso zero explode no passo de
criação. Temporizadores positivos reutilizam `Cooldown`, sem relógio real ou espera.

Cada execução aceita recebe um ID lógico crescente no coordenador da partida. `Attack`
registra IDs atingidos antes de chamar dano/listeners, prevenindo duplicação em golpe,
pulso, explosão e projéteis irmãos. O registro vive junto ao ataque, sem lista global eterna.
Definição e fonte ficam fixadas no ataque, inclusive durante sua viagem ou atraso.

## Passo fixo, ordem e falhas

Após mover entidades, a composição chama:

```php
// Dependências e seed fornecidas pela composição; entidades reais pertencem à C.
$combat = new WeaponAttackSystem(
    $sourceEntityId, $contextProvider, $worldView, $collisionView,
    $targetResolver, $damageSystem, $randomIndex,
);
$inventory->add($catalog->get('bow'), $combat);
$inventory->add($catalog->get('tnt'), $combat);

// No callback update do GameLoop, após movimento:
$combat->update($inventory, $deltaSeconds, $elapsedSeconds);
```

Os imports são `Jogo\Domain\System\WeaponAttackSystem`; as demais dependências vêm das
portas da tabela. O provider de contexto recebe o alcance e deve preservá-lo. O chamador
fornece o tempo lógico do fim do passo, normalmente `GameLoop::elapsedSeconds()` ou contador
de passos × `1/60 s`, e só atualiza esse combate enquanto o proprietário estiver ativo.

O coordenador segue as etapas 4–6 de [LOOP_FIXO.md](LOOP_FIXO.md):

1. Avança cooldowns e tenta armas em ordem dos slots; registra golpes/pulsos e cria objetos.
2. Avança os projéteis já existentes em ordem de criação e resolve o primeiro contato.
3. Avança cargas já existentes em ordem de criação e registra explosões prontas.
4. Aplica os danos registrados nessa ordem, com alvos de áreas ordenados por ID.

Objetos criados no passo começam a avançar no próximo, para não receber tempo anterior ao
nascimento. Atraso zero é a exceção explícita. Golpes e pulsos ficam antes dos impactos e
explosões já existentes. A A5 publica cada `EnemyHit` imediatamente antes de sua eventual
`EnemyKilled`, conservando fonte, arma, tipo, boss, HP efetivo e instante lógico. Alvos mortos
por um dano anterior são revalidados e ignorados nos seguintes.

`__invoke()` só funciona na fase de atualização do inventário desse coordenador. Update
recursivo, uso externo da porta ou tempo retrocedendo gera `LogicException`. Tempos negativos
ou não finitos falham antes de mutar combate. Não há ataques retroativos em um `dt` grande.
`dt=0` conserva trajetória e temporizador; arma pronta ainda pode tentar, conforme A6.

Exceções são propagadas sem rollback. A fila local de golpes é descartada ao sair, objetos
consumidos/detonados não voltam a executar e o bloqueio de atualização é liberado. Um erro
não recupera golpes restantes automaticamente; a composição deve tratar a falha da partida.
Isso conserva a política da A5 e evita replay de dano já aplicado. Não é fila de comandos
de conteúdo: consequências B/C continuam para um ponto seguro externo.

Pausa significa ausência de update do `GameLoop`: cooldowns, posições, temporizadores e
tempo lógico congelam juntos. Renderização pode continuar lendo o estado. Não há dependência
do FPS; atraso descartado pelo limite do loop continua fora da promessa de equivalência.

## Limites e verificações

A A7 fornece portas e adaptadores, não inimigos, IA, ondas, boss concreto, drops, atributos
de jogador, aquisição, XP, level up, melhorias, evolução, entrada real, áudio ou apresentação.
Nenhum protótipo vertical ou pool foi criado. `projectiles()` e `charges()` oferecem cópias
das coleções de objetos lógicos para consulta; não são snapshots da `WorldView` ou serviços
de renderização. A composição deve controlar seu ciclo de vida por partida.

Foram adicionados 93 cenários de teste, incluindo contexto, snapshots, fronteiras,
aleatoriedade, quatro armas, colisão contínua, deduplicação, origem e metadados de eventos,
falhas de listeners, slots independentes e arquitetura. Integração compara os 180 estados
por passo e eventos em 30/60/144/240 FPS e quadros irregulares, usando a configuração real.
A pausa congela simultaneamente arco e TNT e descarta 600 s de tempo real artificial.

`composer check`: 635 testes, 8.061 asserções, Pint e PHPStan nível 9 aprovados.
Suíte completa com `php -d ffi.enable=false vendor/bin/phpunit`: mesmos resultados.
`git diff --check` participa da revisão completa antes do commit e após o merge.
O SHA-256 de `weapons.json` permanece
`5C6F3045D93353923578DF5CF3DAE5479529129BB57139807BAB598497E43610`.

A8 sincroniza cooldown proporcionalmente, rejeita contagens fracionárias e recebe comandos
externos de upgrade/evolução. A7 continua executando ataques com a definição efetiva; objetos
já criados mantêm o snapshot anterior, incluindo ID. `WeaponAttackSystem` também fornece
`WeaponMutationGuard`, rejeitando comandos durante combate/listeners. Conteúdo real da C,
seed da partida, apresentação D, orçamento do pool A9 e medição A10 permanecem externos.
