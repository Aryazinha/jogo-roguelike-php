# A9 — pool de objetos e simulação de combate

A9 fornece a infraestrutura estrutural de RNF03/RNF06: reutilização real de projéteis,
teto de 200 ativos e simulação sem janela. As políticas abaixo são **decisões técnicas
da A9**, sem nova ratificação humana. A10 não foi iniciada; os números deste cenário
não certificam 300 inimigos, 16,6 ms, 50 FPS ou menos de 40 verificações por entidade.

## Componentes

| Componente | Local | Responsabilidade |
| --- | --- | --- |
| `ObjectPool<T>`, `PoolSnapshot` | `Domain/Pool` | Propriedade, reservas atômicas, reset e contadores |
| `ProjectilePool` | `Domain/Weapon` | Pré-alocar projéteis, inicializar rajadas e ativar após o lote |
| `LogicalProjectile` | `Domain/Weapon` | Trajetória fixa e reinicialização sem referências anteriores |
| `WeaponAttackSystem` | `Domain/System` | Reservar antes do ID, resolver dano e liberar no fim do passo |
| `ProjectileCollisionMetrics`, `ProjectileCollisionSnapshot` | `Domain/System` | Contar candidatos e testes estreitos das trajetórias |
| `NeutralInput` | `Infrastructure/Input` | Implementar `InputInterface` com um estado neutro readonly |
| `SimulationClock` | `Application/Simulation` | Controlar timestamps artificiais para `GameLoop` |
| `MeasurementClock`, `MonotonicMeasurement`, `UpdateTiming` | `Application/Simulation` | Medição externa e injetável, independente de gameplay |
| `SyntheticWorld`, `BenchmarkTarget` | `Application/Simulation` | Dados artificiais, sem conteúdo ou IA da C |
| `HeadlessSimulation`, `SimulationOptions`, `SimulationMetrics`, `SimulationReport` | `Application/Simulation` | Composição, parâmetros, checksum e relatório |
| `tools/simulate.php` | Ferramenta CLI | Carga de armas, argumentos, saída e código de erro |

O plano geral sugeria `Core/ObjectPool`. A implementação fica em `Domain/Pool` para
preservar a regra de que Domain depende somente de si e do PHP padrão. O pool genérico
pode servir futuramente à B/D; somente projéteis foram integrados. Não há pool de TNT,
gemas, números de dano, ataques, definições ou estratégias.

## Pool genérico e propriedade

`new ObjectPool($capacity, $factory, $reset)` exige capacidade positiva e cria exatamente
essa quantidade de **identidades distintas** no construtor. A factory não volta a ser
chamada. Não há crescimento, clone ou substituição de objetos ativos.

| API | Comportamento |
| --- | --- |
| `acquire($initialize)` | Uma instância ou `null` quando cheio |
| `acquireMany($count, $initialize)` | Lista completa ou `null`, nunca parte do lote |
| `release($object)` | Reset seguido de disponibilização; estrangeiro/dupla liberação falham |
| `clear()` | Libera todos os objetos ativos; pode ser chamada novamente |
| `snapshot()` | Capacidade, ativos, disponíveis, pico, aquisições, liberações e recusas |

O inicializador de lote recebe objeto e índice começando em zero. A escolha usa a menor
posição disponível na coleção pré-alocada, tornando a reutilização determinística.
O mesmo objeto nunca pertence a duas aquisições simultâneas. Pool não é singleton.

Reservas só passam a constar como ativas depois de todos os inicializadores concluírem.
Se uma inicialização lançar, todos os reservados recebem reset, inclusive os ainda não
inicializados, e o erro original propaga. Não há aquisição bem-sucedida nesse caso.
Callbacks não podem chamar recursivamente o pool, inclusive suas consultas: isso gera
`LogicException`, evitando observar/transacionar uma reserva incompleta.

O contrato do callback de reset exige conclusão sem erro e limpeza completa do objeto.
Se um reset externo defeituoso lançar, o pool conserva a propriedade dessa posição e
impede que ela seja entregue suja; `clear()` tenta as demais posições e propaga o erro.
Uma nova limpeza pode tentar novamente. Na falha de inicialização, a exceção original é
preservada e posições cujo reset falhou ficam indisponíveis. O reset de projétil fornecido
pela A9 não lança. Essas salvaguardas não autorizam callbacks arbitrários a violar o contrato.

`PoolSnapshot` é readonly. Aquisições e liberações contam **objetos**, recusas contam
**pedidos/lotes**; recusar dois projéteis é uma recusa, sem aquisições. Rollback com reset
correto não conta como aquisição/liberação. A limpeza zera ocupação atual, mas conserva
pico e contadores históricos. Uma nova partida cria um novo pool e sistemas de dano/eventos.

## Projéteis e migração da leitura da A7

`ProjectilePool` tem capacidade padrão e máxima **200**, definida em `MAX_CAPACITY`.
Capacidades menores positivas são permitidas para testes; maior que 200 falha na construção.
As instâncias são criadas antes do combate, sem `new LogicalProjectile` dentro do coordenador.

`LogicalProjectile` pode ser construído vazio para o pool; seu construtor antigo com
`Attack`, origem e alvo continua disponível. `initialize()` valida formato/direção,
atribui o ataque atual e deixa inativo. `activate()` só ocorre após inicializar a rajada
completa. O movimento, alcance, primeiro impacto e soma compensada continuam os da A7.

`reset()` elimina ataque/definição/weaponId anteriores, origem, alvo/direção e posição;
zera distância, correção numérica e atividade. O vetor de posição passa a ser zero.
Não há referência a entidades mutáveis no alvo: ele continua sendo `TargetingResult`.

A leitura `$projectile->attack`, `->origin` e `->target` da A7 é preservada por propriedades
mágicas somente de leitura, compatíveis com PHP 8.3. Elas não podem mais ser propriedades
`readonly` reais porque a mesma instância recebe novas referências a cada aquisição.
Ler essas referências depois da liberação gera `LogicException`; posição/distância ficam
zeradas. Escrever externamente também falha. A anotação `@property-read` mantém a tipagem.

**Referências de `projectiles()` são empréstimos válidos até a liberação.** Quem precisar
conservar histórico deve copiar valores ou guardar `Attack`/`TargetingResult` antes dela;
não pode assumir que uma referência antiga de projétil continuará representando o disparo.
Ela poderá ser o mesmo objeto, com outro ataque, numa tentativa posterior. Os testes antigos
que liam uma trajetória já liberada foram migrados; movimento e alcance continuam testados
antes do reset. Os testes novos usam `spl_object_id()` e `WeakReference` para comprovar
identidade e ausência de referências residuais, além de comparar a soma com um objeto novo.

## Rajada cheia, ID e ordem do passo

`WeaponAttackSystem` aceita um `ProjectilePool` opcional como último argumento; chamadas
anteriores criam o pool padrão de 200. A composição deve dar a cada coordenador seu próprio
pool e não liberar seus objetos externamente durante combate.

1. Selecionar alvo válido como na A7.
2. Tentar reservar `projectileCount` posições de uma vez.
3. Com espaço, criar um único `Attack`, inicializar todos e ativar o lote completo.
4. Só então consumir o próximo ID lógico e aceitar a tentativa da arma.
5. Atualizar apenas projéteis existentes no início do passo.
6. Detectar primeiro impacto ou fim do alcance e marcar o projétil consumido.
7. Resolver a fila de impactos/dano/eventos, com deduplicação compartilhada entre irmãos.
8. No `finally` do passo, liberar consumidos e limpar suas referências.

Sem espaço, retorna `false`: nenhum disparo parcial, cooldown consumido, ID avançado ou
projétil ativo descartado. Uma vaga não comporta rajada de dois. Contagens válidas da A8
podem exceder a capacidade; a concessão permanece válida, mas a tentativa é recusada.
Não há clamp de `Stats` para 200. Vagas liberadas no fim do passo só atendem à próxima
tentativa; a fase de inventário continua antes de movimento/impactos.

Falhas de listeners continuam propagando conforme A5/A7. Projéteis consumidos são liberados
ao sair, sem repetir dano já aplicado. Um listener não pode executar `clear()` durante
combate. Definições efetivas da A8, snapshots em voo, mira fixa sem homing, pausa e ordem
de eventos são preservados. Evoluções continuam sendo executadas pela A7, sem outra lógica.

## Encerramento

`WeaponAttackSystem::clear()` exige ponto seguro fora de `update()`. Devolve todos os
projéteis, esvazia cargas e impactos pendentes em memória. Não reinicia cooldowns,
contadores históricos ou IDs; uma nova partida cria novas instâncias.
`poolSnapshot()` e `collisionSnapshot()` permitem guardar relatório antes/depois da limpeza.

A simulação usa `finally` para limpar combate, grade e mundo sintético, inclusive em erro.
Não conserva ataques ou entidades da execução em seu serviço readonly. O relatório contém
somente valores e snapshots imutáveis. As armas e eventos são locais à execução e deixam
de ser referenciados quando ela retorna.

## Executar a simulação

```bash
composer sim
composer sim -- --seed 123 --steps 600 --enemies 24
composer sim -- --seed 123 --seconds 10 --enemies 24 --json
php -d ffi.enable=false tools/simulate.php --seed 123 --steps 600 --enemies 24 --json
```

| Argumento | Regra / padrão técnico da ferramenta |
| --- | --- |
| `--seed` | Inteiro representável; padrão explícito `123` |
| `--steps` | Inteiro positivo; padrão `600` |
| `--seconds` | Número positivo finito; executa `ceil(segundos × 60)` passos |
| `--enemies` | Quantidade não negativa de alvos sintéticos; padrão `24` |
| `--json` | Flag sem valor; saída JSON em vez de texto |

São aceitos `--seed 123` e `--seed=123`. Não combinar `--steps` e `--seconds`.
Valores inválidos, opções desconhecidas/repetidas ou ausentes saem com código 1 e erro
em stderr. Não há parser externo, configuração reescrita ou arquivo de saída automático.
O comando deve rodar no PHP CLI com `register_argc_argv` habilitado.

O script Composer usa `@php` para executar o mesmo PHP do Composer e passa
`-d opcache.enable_cli=0`. Os subprocessos de teste usam a mesma opção, além de
desabilitar FFI. Isso isola o processo do cache de código compartilhado no Windows,
sem editar `php.ini`, dependências ou configurações de gameplay. A
[documentação do PHP](https://www.php.net/manual/en/opcache.configuration.php)
descreve esse compartilhamento entre processos do mesmo usuário/SAPI/cache ID.
Na verificação local com PHP 8.5.11, a configuração com OPcache CLI e JIT ativos
produziu falhas nativas intermitentes (`-1073741819`) em subprocessos, inclusive na
rejeição de argumentos antes do combate e no início do Pint. A associação ao cache
compartilhado é uma hipótese apoiada pela comparação local; não identifica um defeito
específico do PHP.
A execução isolada mantém os mesmos estados e checksums. Para reproduzir manualmente
esse ambiente de medição, use:

```bash
php -d opcache.enable_cli=0 -d ffi.enable=false tools/simulate.php --seed 123 --steps 600 --enemies 24 --json
```

O relatório mede a execução com essa configuração; não compara desempenho de JIT ou
certifica metas da A10. A invocação direta sem essa opção também permanece disponível.
Para repetir as verificações neste runtime Windows, execute o Composer com a mesma
opção no processo pai, substituindo o caminho pelo `composer.phar` da sua instalação:

```bash
php -d opcache.enable_cli=0 /caminho/composer.phar check
php -d opcache.enable_cli=0 -d ffi.enable=false vendor/bin/phpunit
php -d opcache.enable_cli=0 /caminho/composer.phar sim
```

Não há edição da configuração global do PHP nem alteração dos checks do Composer.

O cenário equipa arco e TNT reais nos dois slots; não simula aquisição pela B. Um gerador
privado com a seed posiciona os alvos uma vez: x de 60 a 239 px, y de −150 a 150 px,
raio 4 px e 20 HP. Esses números são **dados artificiais de benchmark**, não balanceamento
ou conteúdo da C. Não há movimento de inimigos, reposição, ondas, IA, drops ou pontuação.
`--enemies=0` é válido e resulta em tentativas sem alvo.

`GameLoop` recebe `SimulationClock`, entrada neutra por `InputInterface` e callback
`render(float $alpha): void {}`. Continua a executar `1/60 s`, sem janela ou hardware.
Cada execução usa `EventBus`, `DamageSystem`, inventário e coordenador reais por partida.
`run($options, $frames)` aceita intervalos de quadros artificiais positivos para testar
30/60/144/240 FPS e quadros irregulares. Para exatamente a quantidade pedida, callbacks
extras do último quadro não executam combate. Nenhum tempo real alimenta o relógio lógico.

## Métricas e checksum

| Grupo | Campos e significado |
| --- | --- |
| Lógico | Passos, segundos simulados, entidades ativas no fim e pico inicial |
| Pool antes da limpeza | Ocupação no fim do combate, pico, capacidade, aquisições, liberações e recusas |
| Pool após limpeza | Ocupação zero, todas as vagas disponíveis, liberações incluindo cancelamentos |
| Eventos | Ataques aceitos; impactos = eventos `EnemyHit` com dano efetivo; mortes = `EnemyKilled` |
| Colisão de projéteis | Total, último passo e máximo por passo de candidatos retornados e testes segmento/círculo |
| Tempo real | Total, média e maior duração de atualização, em ns no JSON e ms no texto |

Um candidato é contado quando a porta de trajetórias o retorna. Um teste estreito é
contado quando o coordenador executa `segmentCircleFirstHit()` após validar inimigo vivo.
Consultas de mira, área/TNT, snapshots e consultas administrativas não entram nesse contador.
Não se afirma medir todas as operações geométricas do jogo nem custo universal por entidade.

`MeasurementClock` é injetável. `MonotonicMeasurement` usa `hrtime(true)` somente em
Application. A medição envolve `WeaponAttackSystem::update()`; não inclui construção,
carga de arquivos, renderização ou limpeza. Os testes injetam intervalos conhecidos,
sem depender da velocidade da máquina ou estabelecer limiar em milissegundos.

O SHA-256 inclui estado final antes da limpeza, eventos em ordem e métricas lógicas:
HP/atividade/posição dos alvos, definições/cooldowns, projéteis e cargas em andamento.
Não inclui tempo real, flag de saída JSON, FPS de apresentação ou identidade de memória.
Mesma configuração, seed e passos reproduzem o checksum; trocar a seed altera o cenário.

Exemplo observado, seed 123, 600 passos e 24 alvos:

```text
Tempo lógico: 10 s; entidades no fim: 23; pico: 24
Projéteis no fim: 0; pico: 1; capacidade: 200
Aquisições/liberações: 6/6; rajadas recusadas: 0
Ataques: 9; impactos: 35; mortes: 1
Candidatos/testes estreitos: 6/6; máximos por passo: 1/1
Após limpeza: 0 ativos, 200 disponíveis
Checksum: 35f3ee30f41fb78004f3162bd2fc02b841330b9fc48ff52d27fc10a04780fb62
```

Esse cenário exercita os sistemas, não satura o pool. Testes separados saturam exatamente
200 posições, recusam a 201ª e comparam rajadas recusadas/reutilização em pools pequenos.

## Verificação e fronteiras

```bash
composer check
php -d ffi.enable=false vendor/bin/phpunit
composer sim
git diff --check
```

Foram adicionados **73 cenários**. `composer check` passou com **869 testes e 9.350
asserções**, Pint e PHPStan nível 9 sem erros. A suíte completa com FFI desabilitado
passou com os mesmos resultados. CLI direta e `composer sim` executaram com sucesso;
`git diff --check` passou. Nas verificações finais locais, OPcache CLI foi desativado
pela opção de processo documentada acima. Os cinco testes da CLI passaram também em
cinco repetições consecutivas após o isolamento. As verificações são repetidas após
a integração.

Os testes cobrem propriedade, identidade, reset, referências fracas, rollback, recursão,
capacidade, rajadas, IDs, cooldown, fim de alcance, impacto, listener com erro e limpeza.
Comparam estados/eventos/métricas em diferentes FPS, preservam regressões de A7/A8 e
executam a CLI em subprocessos com FFI desabilitado. PHPStan nível 9 passou a analisar
também `tools/`, sem reduzir regras. Os JSONs permanecem idênticos.

B mantém progressão, gemas, concessão e elegibilidade; C mantém inimigos/conteúdo;
D mantém renderer, entrada real e feedback. Não foi criado `RendererInterface`,
`NullRenderer`, jogo completo ou protótipo vertical. O plano geral menciona esses adaptadores
da D, mas A9 usa somente o callback nulo autorizado.

Para A10 ficam integração final com conteúdo real e apresentação, cenário de carga,
metas oficiais com máquina/seed documentadas e otimização de gargalos comprovados.
Não há certificação de FPS ou orçamento por quadro nesta entrega.
