# A10 — integração, desempenho e regressão

Registro técnico de 12/10/2026. A composição está isolada em
`Application/Benchmark`; não é conteúdo nem interface do jogo. O enunciado e os contratos
ratificados continuam sendo as fontes dos requisitos. As escolhas de cenário, medição e
preparação do índice abaixo são decisões técnicas da A10, sem nova ratificação humana.

## Escopo e integrações disponíveis

O inventário da `main` de partida (`c08205cf7aec55cc2ca2b619dd011dc74c3e2c22`) contém
o núcleo A1–A9, loaders e adaptadores de entrada/tempo. Não contém Player de gameplay da
B, inimigos/IA/ondas da C, nem renderer, entrada real, estados, áudio ou Raylib da D.
Não foram integradas branches dessas frentes.

`BenchmarkPlayer` e `BenchmarkEnemy` usam `Entity`, `Movable`, `Collidable` e
`Damageable`. Têm círculo de 6 px e 1 bilhão de HP para manter a população durante
a amostra; não há reposição, spawn, invulnerabilidade ou dano por contato. Qualquer morte
reprova a carga. Movimento inimigo é roteiro de ensaio, sem decisão de IA.

| Ordem do passo | Sistema real exercitado |
| --- | --- |
| 1 | `GameLoop` fornece `dt = 1/60 s`; `ScriptedInput` implementa `InputInterface` |
| 2 | `MovementSystem`, `Facing` e `MapBounds` movimentam jogador e entidades sintéticas |
| 3 | `SpatialHash` e `SpatialWorldView` preparam consultas; `CollisionSystem` verifica contatos sem consequências |
| 4 | Comandos A8 aplicam/removem efeitos, upgrades e evoluções em ponto seguro |
| 5 | `CombatInventory` e `WeaponAttackSystem` processam miras, cooldowns, ataques, projéteis existentes e TNT |
| 6 | `DamageSystem` e `EventBus` resolvem impactos na ordem da A7; consumidos retornam ao `ProjectilePool` |
| 7 | Fora do intervalo medido: população, snapshots de métricas e checksum dos eventos |

No encerramento, o estado é capturado antes de `clear()`; ataques e referências do pool
são liberados, visão invalidada, grade e mundo esvaziados. Cada repetição cria uma composição
nova. O callback de renderização é vazio; tempo real só mede, nunca comanda o mundo.

## Cenário reproduzível

| Parâmetro técnico | Valor padrão |
| --- | --- |
| Identificador | `a10-load-v1` |
| Seed | `20261012`, gerador privado sem estado global |
| Aquecimento | 600 passos, 10 segundos lógicos, fora da amostra |
| Medição | 1.800 passos por execução, 30 segundos lógicos |
| Repetições de aceite | 5, 9.000 amostras no total |
| População | 300 inimigos ativos em **todos** os passos medidos |
| Pool | Capacidade 200; pico medido exatamente 200; nunca 201 |
| Mapa | `x ∈ [-320, 320]`, `y ∈ [-256, 256]` px; limites sobre o centro, conforme A3 |
| Distribuição | Malha 20 × 15, espaçamento 32 px; x inicial `32·coluna−304`, y `32·linha−240`, jitter independente inteiro de −2 a 2 px |
| Movimento inimigo | 12 px/s horizontal, inversão a cada 120 passos |
| Jogador | 240 px/s; oito fases de 60 passos: direita, diagonal abaixo/direita, abaixo, diagonal abaixo/esquerda, esquerda, diagonal acima/esquerda, diagonal acima/direita, repouso |
| Armas | Stone Sword, Bow, TNT e Torch, pelos loaders/configurações reais |

A entrada do ensaio é consumida **por passo lógico**, tornando equivalente a sequência de
comandos nas diferentes agendas de quadros. A entrada de hardware continua responsabilidade
da D, que deverá amostrá-la por quadro e entregar snapshots; a ferramenta não a implementa.

Os quatro slots são exclusivos desta composição; o padrão de gameplay permanece dois.
Para saturar o pool, a API A8 aplica `FLAT +199` projéteis ao arco e `PERCENT
(0,02 / cooldown_base − 1)` ao cooldown, produzindo rajadas de 200 a cada 0,02 s quando
houver espaço. TNT recebe `PERCENT −0,9` no cooldown. Sem espaço, a A9 recusa a rajada inteira
sem consumir cooldown/ID. Vagas aparecem após impactos/alcance, promovendo reutilização.
Nenhum JSON de gameplay foi alterado.

O roteiro ainda aplica FLAT de dano no passo 30, upgrade de espada no 45, PERCENT de dano
no 60, remove FLAT no 90, evolui para Enchanted Stone Sword no 120 e Mega TNT no 150.
Não concede XP, passivas ou elegibilidade: são comandos sintéticos de teste pelas portas A8.

## Medição e critérios

`MeasurementClock` é injetável; `MonotonicMeasurement` usa `hrtime(true)` fora do domínio.
A amostra começa antes do movimento e termina após combate/dano/eventos/liberação dos
consumidos. Inclui instrumentação, contatos e aplicação dos comandos A8 nesses passos.
Exclui inicialização, carga, aquecimento, contagem administrativa da população, serialização,
checksum, relatório, renderização e limpeza final. Não há `sleep`.

Todas as amostras entram nos cálculos. Média é soma/n; mediana usa os elementos centrais;
p95/p99 usam posto `ceil(p·n)`, contado a partir de 1. Milissegundos = ns/1.000.000.
Atualizações/s = n·1.000.000.000/tempo total; não são FPS visuais.

**Aceite temporal:** p95 ≤ 16.600.000 ns **em cada execução**, sem arredondar para decidir.
Média, mediana, p99 e máximo também são publicados, inclusive pausas do sistema operacional.
O máximo sozinho não decide o orçamento. Testes PHPUnit usam relógios controlados e não
aplicam limiares à velocidade real da máquina.

**Aceite geométrico:** máximo observado estritamente menor que 40 verificações por entidade
ativa por passo. A comparação usa inteiros `testes < 40·entidades`, antes de formatar razões.
40 reprova; 39.999 verificações/1.000 entidades = 39,999 aprova. O denominador é a população
ativa daquele passo: jogador + inimigos Collidable vivos, 301 no cenário-alvo. Projéteis
lógicos não são Collidable registrados no mundo e não aumentam esse denominador; isso
torna a comparação conservadora. Uma morte não mantém artificialmente o denominador.

`GeometryMetrics` acompanha todas as chamadas geométricas relevantes do cenário: filtros
circulares da grade, visão, miras e áreas; contatos; setores; segmento–círculo de projéteis.
Chamadas compostas contam também seu teste circular interno, além de setor/segmento.
Candidatos são entidades únicas por consulta espacial ampla, somadas entre consultas;
reconstruções são publicadas separadamente. Snapshots, ordenações, acesso por ID, raio
envolvente de consulta e iteração administrativa não são testes de colisão. Os contadores
de projéteis A9 continuam separados e não são somados novamente.

O JSON contém total de candidatos, círculos/segmentos/setores, reconstruções, distribuição
de testes por passo e por entidade, e passo lógico do primeiro máximo. Contadores geométricos
cobrem somente a janela medida; ataques, eventos e pool abrangem aquecimento + medição.
Memória inicial é medida antes da composição; pico é `memory_get_peak_usage()` do processo,
cumulativo entre repetições, sem fingir que é exclusivo de cada uma.

## Baseline e otimização

Em preparação: resultados serão registrados antes da conclusão do aceite.

## Reprodução e saídas

```bash
# Composer deve ser iniciado com OPcache CLI desativado nesta máquina Windows.
php -d opcache.enable_cli=0 /caminho/composer.phar check
php -d opcache.enable_cli=0 /caminho/composer.phar sim -- --seed=123 --steps=600 --enemies=24 --json
php -d opcache.enable_cli=0 /caminho/composer.phar benchmark

# Medição de aceite, sem FFI ou OPcache/JIT ativo:
php -d opcache.enable_cli=0 -d ffi.enable=false tools/benchmark.php --seed=20261012 --warmup=600 --steps=1800 --runs=5 --enemies=300 --projectiles=200 --json

# Baseline funcional reproduzível com as consultas frescas:
php -d opcache.enable_cli=0 -d ffi.enable=false tools/benchmark.php --seed=20261012 --warmup=600 --steps=1800 --runs=1 --enemies=300 --projectiles=200 --fresh-queries --json
```

Parâmetros aceitam `--nome valor` ou `--nome=valor`. Seed deve ser inteiro representável;
warmup ≥ 0; steps/runs/enemies > 0; projectiles entre 1 e 200. Opções desconhecidas,
repetidas, valores ausentes, fracionários ou que excedam inteiros falham com código **1**.
`--json` e `--fresh-queries` são flags sem valor. Sem `--json`, imprime texto. Nenhuma
saída automática reescreve arquivos; redirecionamento é escolha explícita do operador.
`--output` não foi necessário.

Código **0** indica todas as metas A10 obrigatórias aprovadas; **2** indica reprovação.
Cargas menores são diagnósticas e reprovam as metas 300/200. Ausência da D não reprova A.
Além das metas numéricas, o relatório verifica reutilização, limpeza, nenhuma morte e
checksum igual em repetições. A suíte A9 prova identidade dos objetos, reset, teto e
recusa atômica; a regressão A10 exercita o comportamento na composição integrada.

O SHA-256 lógico inclui seed, estado antes da limpeza, eventos em ordem e métricas lógicas;
exclui tempo, memória, Git, modo de consulta, IDs de memória e contadores de reconstrução.
Geometria é comparada separadamente antes/depois. Eventos podem ser capturados em testes;
saída normal publica seu checksum para não gerar dumps grandes.

O ambiente registra data UTC, sistema, arquitetura, identificação disponível da CPU,
PHP, OPcache/JIT/FFI efetivos, commit/branch, estado dos arquivos rastreados/não rastreados
e hashes das três configurações lidas. Composer e memória física aparecem indisponíveis
quando não há uma fonte portátil, sem comandos frágeis para preenchê-los.
FFI pode estar carregada como extensão, mas `ffi.enable=false` impede uso de sua API.

## Regressão e fronteiras

As suítes A2–A9 permanecem: loop, pausa/retomada, direção/limites, grade, dano parcial/fatal,
morte única/ordem, quatro miras/armas, cooldown independente/proporcional, ausência de alvo,
projéteis/TNT, efeitos FLAT/PERCENT, evolução, pool cheio/reset/limpeza e fronteiras do domínio.
Os testes A10 acrescentam estatística, validação da CLI/JSON/códigos de saída, instrumentação,
preparação/invalidação da visão e regressão longa com 300/200 em 30/60/144/240 FPS e quadros
irregulares. Comparações incluem estado, eventos, métricas lógicas/geométricas e checksum.

## Limitações e dependências externas

**Meta visual de 50 FPS pendente de validação conjunta com a Frente D.**
Não há renderer real nesta main. Não foram medidos FPS visuais, menu, áudio ou apresentação.
O resultado caracteriza esta máquina, versão do PHP, configuração e carga sintética; não
certifica outras máquinas nem custos futuros de IA, progressão ou efeitos.

B deve fornecer jogador/atributos/concessões e consumir mortes; C deve fornecer entidades,
comportamentos/ondas e políticas de contato; D deve fornecer entrada/estados/renderização
e feedback. As portas A estão descritas em `CONTRATOS.md`. Contato, invulnerabilidade,
vida máxima, velocidade real/mapa, conteúdo e os demais pontos explicitamente indefinidos
continuam pendentes. Os marcadores dos participantes B/C/D foram preservados.
