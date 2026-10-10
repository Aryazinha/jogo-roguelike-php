# Entrega A4 — colisão circular e grade espacial

A A4 fornece geometria e consultas genéricas para a base de RF08/RF38 e RNF06.
Não aplica dano, gera eventos, coleta itens, implementa conteúdo ou desenha a grade.
A5 permanece não iniciada.

**Estado:** A4 concluída, com 63 novos cenários de teste. A suíte completa passou com
181 testes e 6.161 asserções; Pint, PHPStan nível 9 e `git diff --check` passaram.

## Componentes

| Componente | Responsabilidade |
| --- | --- |
| `Domain/Entity/Collidable` | Capacidade ratificada: ID, posição, raio e atividade |
| `Domain/Entity/Entity` | Implementar `Collidable` e `Movable` com os métodos existentes |
| `Domain/System/CollisionSystem` | Confirmar interseções entre dois círculos |
| `Domain/Spatial/SpatialHash` | Indexar entidades e obter candidatos em células locais |
| `Domain/Spatial/SpatialQueryResult` | Expor resultados e métricas de cada consulta |
| `tests/Support/FakeCollidable` | Entidade mínima para testar a infraestrutura sem conteúdo de B/C/D |

Os arquivos de produção ficam em `src/`, com namespace `Jogo\`. Não usam relógio, estado
global, arquivos, FFI ou Raylib. `CollisionSystem` e `SpatialHash` recebem `Collidable`,
sem depender de jogadores, inimigos ou pickups concretos.

## Fase ampla e fase estreita

A **fase ampla** reduz a busca a células relevantes. `queryNeighbors()` devolve candidatos,
que ainda podem estar separados: compartilhar uma célula não comprova colisão.
A **fase estreita** compara a geometria. `CollisionSystem::intersects()` confirma um par;
`queryRadius()` também faz essa confirmação após obter candidatos pela grade.

```text
distância² = (x1 − x2)² + (y1 − y2)²
interseção = distância² <= (raio1 + raio2)²
```

Tangência exata conta como interseção geométrica nesta A4. Círculos contidos ou sobrepostos
também intersectam; dois pontos de raio zero intersectam somente na mesma posição.
Não há tolerância que aumente artificialmente os raios. A implementação evita raiz quadrada
e reescala os cálculos nos extremos numéricos para evitar overflow ou underflow.

`intersects(Collidable $first, Collidable $second): bool` ignora entidades inativas e pares
com o mesmo ID, que representam a mesma identidade lógica. Não altera os objetos.
`circlesIntersect(Vector2 $first, float $firstRadius, Vector2 $second, float $secondRadius): bool`
é a primitiva estática, sem identidade ou atividade. Rejeita raios negativos ou não finitos;
coordenadas continuam validadas por `Vector2`.

Interseção não implica dano, morte, empurrão, coleta ou bloqueio de movimento. A5 definirá
o consumo das colisões pelo dano; frequência de contato, invulnerabilidade, consumo de
projéteis e demais consequências continuam pendentes. Não foram ratificadas nesta execução.

## Como funciona a grade

`SpatialHash` usa células quadradas de **64 px por padrão**, conforme o plano. O construtor
permite configurar `cellSize`, sempre finito e positivo; isso não altera a geometria.
Para cada coordenada, o índice é `floor(coordenada / cellSize)`, inclusive com negativos:
`x = -0.1` pertence à célula `-1`, não à célula `0`.

`insert(Collidable $entity): void` registra cada círculo em todas as células do seu
retângulo envolvente, de `(x − raio, y − raio)` até `(x + raio, y + raio)`. Os extremos
são inclusivos. Um círculo que alcança uma borda é encontrado pela consulta do outro lado;
um círculo grande pode ocupar muitas células. O retângulo pode incluir cantos que o círculo
não cobre; esses candidatos são descartados na fase estreita.

IDs não podem estar em branco. Inserir o mesmo ID novamente, inclusive o mesmo objeto,
gera `InvalidArgumentException`; não há substituição silenciosa. Entidades inativas não
são inseridas. `entityCount()` conta as entidades registradas, não as referências repetidas
nas células. Uma desativação posterior é filtrada imediatamente nas consultas e removida
do registro na próxima reconstrução.

`clear()` esvazia somente as coleções em memória. `rebuild(iterable $entities)` constrói
uma nova grade com as posições atuais, substituindo a anterior ao terminar. Se os dados
forem inválidos ou houver ID duplicado, a reconstrução falha e conserva a grade anterior.
Uma reconstrução vazia limpa o índice. Após alterar posição ou raio, é necessário reconstruir
antes de consultar; a grade não acompanha setters automaticamente. IDs devem permanecer
estáveis enquanto as entidades estiverem indexadas.

## Consultas e ordenação

| Método | Resultado |
| --- | --- |
| `queryNeighbors(Vector2 $position, ?string $excludeId = null)` | Candidatos da célula do ponto e das oito células adjacentes; visita exatamente nove células |
| `queryRadius(Vector2 $center, float $radius, ?string $excludeId = null)` | Entidades cujos círculos intersectam o círculo da consulta, inclusive em tangência |

Ambos devolvem `SpatialQueryResult`, ignoram inativos, removem duplicados por ID e ordenam
lexicograficamente por ID. IDs numéricos continuam strings: `"10"` vem antes de `"2"`.
`excludeId` permite excluir quem consulta antes da contagem de candidatos; por padrão a
própria entidade pode aparecer. O resultado vazio contém `entities = []`.

A consulta circular percorre todas as células do retângulo da área; não fica limitada a
nove células. Como os círculos indexados também ocupam suas células de borda, um centro fora
da área pode ser devolvido quando seu raio a alcança. Raio zero consulta um ponto e devolve
inclusive círculos que o cobrem. Para detectar contatos com raios arbitrários, use
`queryRadius($entity->position(), $entity->collisionRadius(), $entity->id())`; as nove células
isoladas não cobrem necessariamente toda a extensão de uma entidade grande.

Essa consulta de círculos **não muda** `WorldView::neighbors()` da A1, que seleciona centros
em um raio e devolve cópias imutáveis. O futuro adaptador da visão deve filtrar a distância
dos centros e gerar `EntityView`. `SpatialQueryResult` é uma coleção de referências de
domínio, não uma visão de leitura para C/D; as posições dos objetos podem mudar depois.

## Validação e orçamento técnico

Além das validações anteriores, índices espaciais precisam caber na faixa de inteiros do
PHP, com margem para os vizinhos. Coordenadas ou extremos derivados fora dessa faixa
falham com `InvalidArgumentException`, sem conversão silenciosa para outro índice.

`maxCellsPerOperation`, segundo argumento do construtor, tem padrão técnico de `100_000`
e deve ser positivo. Inserções e consultas rejeitam retângulos que excedam esse orçamento
antes de expandir as células. Ele protege a expansão de intervalos inviáveis e pode ser
configurado; não define tamanho oficial do mapa, alcance de armas ou limite de entidades.
Essa decisão interna da A4 não altera os dados do enunciado. Inserções inválidas não deixam
um registro parcial na grade.

## Métricas por consulta

| Campo ou método de `SpatialQueryResult` | Significado |
| --- | --- |
| `entities` | Lista sem duplicação, em ordem por ID |
| `candidatesExamined` | Candidatos únicos ativos após excluir `excludeId`, antes da confirmação geométrica |
| `resultCount()` | Quantidade de entidades devolvidas; na consulta circular pode ser menor que os candidatos |
| `cellsVisited` | Células percorridas, inclusive vazias |

Uma entidade registrada em várias células é contada uma vez. As métricas acompanham cada
resultado imutável, sem contador global ou dependência de uma consulta anterior.
Elas contam candidatos únicos, não leituras repetidas dos buckets, tempo de CPU ou pares
de gameplay. Consultar as duas direções de um par pode devolvê-lo nas duas consultas;
o futuro consumidor deverá coordenar suas consequências.

## Cenário de referência determinístico

`SpatialReferenceTest` insere **300 entidades falsas**, todas ativas, em 20 colunas e
15 linhas. O ID é `entity-000` até `entity-299`, em ordem de linha. Para coluna `c` e linha `l`:

```text
posição = (32 × c − 304, 32 × l − 240) px
raio da entidade = 6 px
tamanho da célula = 64 px
```

Não há sorteio. Cada entidade consulta seus nove vizinhos e uma área circular de 32 px,
excluindo o próprio ID. Os números são exclusivamente dados desse cenário de teste,
não conteúdo, distribuição oficial do mapa ou balanceamento de B/C.

| Métrica nas 300 consultas | Nove células | Área circular de 32 px |
| --- | ---: | ---: |
| Total de candidatos únicos | 8.772 | 3.956 |
| Média de candidatos por entidade | 29,24 | 13,1867 |
| Máximo de candidatos por entidade | 35 | 15 |
| Células por consulta | 9 | 4 |
| Total de resultados confirmados geometricamente | Não se aplica à fase ampla | 1.130 |

Ambas as médias ficam abaixo de 40; os testes também verificam os totais exatos. A área
circular encontra somente os centros ortogonais a 32 px, confirmados por um oráculo
independente baseado nos índices da malha. Outro cenário com 80 círculos variados, posições
negativas e inativos compara 320 consultas a um cálculo independente com `hypot()`.
A varredura todos-contra-todos existe apenas nesse oráculo de teste, não na implementação.

Essa evidência vale para a distribuição documentada. Concentrações maiores de entidades
podem gerar mais candidatos; não se promete um limite universal. Medição de FPS, CPU e carga
com combate real permanece na A10, sem usar tempo instável como critério de aceite da A4.

## Integração entre as frentes e com A2/A3

C poderá fornecer inimigos e elementos implementando `Collidable`; B/C poderão fornecer
pickups pela mesma capacidade, sem exigir vida, XP ou uma classe concreta nesta entrega.
A forma do objeto não determina o que coletar, atacar ou destruir. D continuará responsável
pela apresentação. Não há renderer ou visualização de depuração na A4.

Em cada passo fixo, a composição aplica movimento, reconstrói o índice e então consulta:

```php
// Colaboradores fornecidos pela composição; nenhuma entidade concreta é criada aqui.
$movement->update($entity, $facing, $inputState, $speed, $dt, $entity->isActive());
$grid->rebuild($activeWorldEntities);
$contacts = $grid->queryRadius($entity->position(), $entity->collisionRadius(), $entity->id());
// A5 futuramente consumirá os contatos; A4 não aplica consequências.
```

Se o quadro tiver vários passos, reconstruir depois do movimento de cada passo evita
consultar posições indexadas antigas. `MovementCollisionTest` comprova essa ordem com o
`GameLoop` da A2 e o `MovementSystem` da A3, incluindo travessia de borda e tangência,
sem dano ou resposta física. O teste de arquitetura existente verifica todo o domínio,
incluindo as novas classes, contra dependências gráficas e I/O.

## Verificações

Os testes cobrem separação, sobreposição, tangência, mesma posição, raio zero, inatividade,
auto-interseção, inserção, bordas, negativos, múltiplas células, limpeza, reconstrução,
consultas, duplicação, ordem, IDs repetidos, dados inválidos e métricas reproduzíveis.

```bash
composer check
git diff --check
```

Os contratos ratificados e os valores `_fonte: "enunciado"` permanecem preservados.
Nenhuma consequência de gameplay foi declarada aprovada pela equipe nesta execução.
