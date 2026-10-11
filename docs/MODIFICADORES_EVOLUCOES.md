# A8 — modificadores e evoluções de armas

A8 concluída para RF19/RF37: um comando externo da B altera uma arma equipada e o próximo
ataque usa os valores resultantes. A9 e A10 não iniciadas. Este documento registra
**decisões técnicas da implementação A8**, sem nova ratificação humana ou requisito do
professor. Os três JSONs de armas, upgrades e evoluções permanecem idênticos.

## Componentes e responsabilidades

| Componente | Local em `src/` | Responsabilidade |
| --- | --- | --- |
| `Stats`, `StatModifier`, `ModifierType` | `Domain/Value` | Contratos imutáveis da A1 e tipos FLAT/PERCENT |
| `StatResolver` | `Domain/Value` | Resolver a fórmula, ordenando por origem |
| `WeaponStatsMapper`, `ResolvedWeaponStats` | `Domain/Weapon` | Conversão centralizada e definição efetiva validada |
| `UpgradeDefinition`, `UpgradeCatalog` | `Domain/Weapon/Upgrade` | Dados de upgrades, sem controlar níveis |
| `EvolutionDefinition`, `EvolutionCatalog` | `Domain/Weapon/Upgrade` | Resultado e requisitos imutáveis para consumo da B |
| `WeaponProgressionService` | `Domain/Weapon/Upgrade` | Aplicar, remover e evoluir por slot |
| `WeaponMutationGuard` | `Domain/Weapon` | Rejeitar comandos durante atualização de combate |
| `UpgradeConfigLoader`, `UpgradeDefinitionMapper` | `Core/Config` | Ler e validar `upgrades.json` |
| `EvolutionConfigLoader`, `EvolutionDefinitionMapper` | `Core/Config` | Ler, validar referências e mapear `evolutions.json` |
| `ConfigJsonReader`, `ConfigSchema` | `Core/Config` | Leitura, chaves repetidas, tipos, metadados e diagnósticos comuns |

`Weapon`, `Cooldown`, `CombatInventory` e `WeaponAttackSystem` receberam extensões compatíveis.
Os construtores e métodos anteriores permanecem disponíveis. `WeaponDefinitionMapper::mapWeapon()`
passou a ser público para reutilizar integralmente a validação da A6 nas evoluções.
Não há outra implementação de mira, geometria, movimento, detonação, dano ou eventos.

## Base, modificadores e definição efetiva

Cada `Weapon` mantém três dados separados:

1. `baseDefinition()`: definição imutável original; após evolução, a definição da evolução.
2. `modifiers()`: cópia da coleção de efeitos vigentes, ordenada por `source`.
3. `definition()`: definição imutável efetiva, entregue ao executor para ataques novos.

Não há aplicação de percentual sobre resultado anterior. A resolução sempre parte da base:

```text
valorFinal = (valorBase + soma(FLAT)) × produto(1 + PERCENT)
```

`StatResolver` ordena por `source` usando comparação lexicográfica, soma os FLAT e multiplica
os fatores PERCENT separadamente. Exemplo didático, sem valor de balanceamento: base 10,
FLAT +2, PERCENT +25% e PERCENT −10% resultam em `12 × 1,25 × 0,90 = 13,5`.
A ordem recebida não muda a resolução, inclusive no arredondamento de ponto flutuante.

`Stats` preserva a API da A1: `has()`, `value()` e `values()`. Só armazena atributos presentes,
sem preencher campos ausentes com zero. Inteiros são convertidos para float; booleanos,
strings numéricas, NaN, infinito e nomes desconhecidos são rejeitados.
Os nomes de personagens já documentados na A1 podem ser representados pelo mapa genérico,
mas a A8 não cria nem modifica jogador, passivo, `Health`, regeneração ou coleta.

`WeaponStatsMapper` mapeia exatamente os oito nomes de combate:

| Atributo | Campo em `WeaponDefinition` | Faixa final |
| --- | --- | --- |
| `dano` | `damage` | HP finito ≥ 0 |
| `cooldown` | `cooldownSeconds` | Segundos finitos > 0 |
| `alcance` | `range` | Pixels finitos > 0 |
| `projeteis` | `projectileCount` | Inteiro ≥ 1, representável como int |
| `velocidadeProjetil` | `projectileSpeed` | Pixels/s finitos > 0 |
| `anguloGraus` | `angleDegrees` | Graus finitos, 0 < valor ≤ 360 |
| `raioExplosao` | `explosionRadius` | Pixels finitos > 0 |
| `atrasoDetonacaoSegundos` | `detonationDelaySeconds` | Segundos finitos ≥ 0 |

Os campos específicos só existem no formato correspondente. A conversão de volta exige
exatamente o mesmo conjunto de atributos da base; `null` continua significando ausência.
Modificar `raioExplosao` de espada, arco ou tocha falha, sem transformá-los em explosivos.

**Contagem:** o resultado final deve ser matematicamente inteiro. Não há `floor`, `ceil`,
`round` ou tolerância para aceitar frações. Como `Stats` usa float, contagens que não possam
ser convertidas com segurança para int também são rejeitadas. Não foi imposto o teto do
pool da A9 nesta entrega. Os upgrades atuais de projéteis usam FLAT inteiro.

## Sources, remoção e atomicidade

`source` identifica um efeito concedido; não é `_fonte` do JSON. Cada efeito precisa de
uma origem não vazia e única na arma. Um comando pode conter vários efeitos, cada um com
sua própria origem. Reenviar origem já aplicada falha, inclusive em outro atributo.
`modifier($source)` localiza o efeito ou retorna `null`.

`remove($slot, $source)` remove uma origem exata e recalcula os demais efeitos a partir da
base. Retorna `true` quando remove; origem inexistente retorna `false`, sem alteração.
Origem vazia é inválida. Remover um efeito que tornaria o resultado restante inválido falha.

Aplicação e remoção resolvem e validam todo o resultado antes de sincronizar cooldown e
trocar os dados da arma. Se a validação falhar, base, definição efetiva, modificadores,
slot e cooldown permanecem inalterados. Os erros usam `InvalidArgumentException`, sem clamp.

A API de comandos aplica efeitos a uma arma identificada por slot; não oferece concessão
global ao inventário. Para resolução genérica, `StatResolver::resolve(..., ignoreMissing: true)`
ignora atributos ausentes, conforme a A1, sem criar atributos. A B continua responsável
por decidir destinos e duração dos efeitos; não existe expiração automática na A8.

## Cooldown proporcional

`Cooldown::changeDuration($seconds)` mantém os métodos anteriores e acrescenta a política:

```text
remainingNew = (remainingOld / durationOld) × durationNew
```

Arma pronta permanece pronta; com metade restante, conserva metade da nova duração.
A operação não dispara, reinicia o cooldown completo ou produz ataques retroativos.
Duração inalterada conserva inclusive o estado da soma compensada. Ao trocar a duração,
o acumulador é rebaseado para continuar avançando por `dt` fixo.

Duração final precisa ser finita > 0. Uma mudança que causaria underflow e tornaria uma
arma bloqueada pronta é rejeitada antes de mutar o estado. A pausa continua sendo ausência
de atualização: um comando pode mudar valores enquanto pausado, mas não avança o relógio.
Essa política é decisão técnica da A8, não uma ratificação da equipe.

## Comandos enviados pela B

```php
use Jogo\Core\Config\UpgradeConfigLoader;
use Jogo\Core\Config\EvolutionConfigLoader;
use Jogo\Domain\Weapon\Upgrade\WeaponProgressionService;

$upgrades = (new UpgradeConfigLoader($weaponCatalog))->load($upgradesPath);
// A composição/B fornece IDs de passivos disponíveis; A não implementa seus efeitos.
$evolutions = (new EvolutionConfigLoader($weaponCatalog, $passiveIds))->load($evolutionsPath);
$progression = new WeaponProgressionService($inventory, $evolutions, $combat);

// B já decidiu qual concessão foi autorizada. Slots de combate começam em zero.
$progression->applyUpgrade(0, $upgrades->get('stone_sword', 2));
$progression->apply(0, $modifiersGrantedByB);
$removed = $progression->remove(0, 'upgrades:stone_sword:2:0');
$progression->evolve(0, 'enchanted_stone_sword');
```

`apply($slot, $modifiers)` recebe uma lista de `StatModifier`. `applyUpgrade()` confere
somente se o upgrade pertence à base equipada, sem verificar nível alcançado.
Slot vazio ou inválido falha explicitamente. Nenhuma chamada escolhe opções, sorteia,
concede aquisição, registra nível ou decide elegibilidade.

O terceiro argumento é `WeaponMutationGuard`; o próprio `WeaponAttackSystem` da A7 o
implementa. Comandos devem ser chamados entre updates, por exemplo na etapa 7 do loop,
depois do retorno de `$combat->update()`. Chamadas durante inventário, tentativa da arma ou
combate, inclusive em listeners de dano, geram `LogicException` antes de modificar a arma.
O executor invocável A7 também protege as operações locais de `Weapon`. Se for envolvido
em closure, a B deve continuar usando o serviço com o guard explícito da composição.
Não foi criada fila de comandos ou protocolo geral de mutações de B/C.

## Loaders e catálogos

Os loaders oferecem `load($file)` e `decode($json, $source)`. Os mapeadores recebem a raiz
decodificada; I/O e JSON ficam em `Core/Config`, nunca no domínio. As coleções são imutáveis,
preservando a ordem de configuração. Metadados são validados e não viram atributos.

Validação cobre raiz, `_meta.arquivo`, requisitos/nota, campos obrigatórios, tipos, números
finitos, nomes canônicos, formato/mira, campos específicos e desconhecidos de gameplay.
Níveis de referência devem ser inteiros ≥ 1, sem controlar o nível do jogador/arma.
Referências de armas usam `WeaponCatalog`; referências de passivos usam somente a lista de
IDs externos. Não há loader de passivos ou personagem implementado pela A8.

Cada modificador recebe `upgrades:<weaponId>:<nivel>:<indice>`, com índice começando em zero.
Nível repetido para a mesma arma e chaves JSON repetidas são erros. Cada concessão é validada
contra a base, isoladamente e como lote, para permitir comandos sem pressupor efeitos anteriores;
a coleção vigente volta a ser validada em tempo de aplicação. `UpgradeCatalog::get()` só consulta
dados e não autoriza a concessão.

Nas evoluções, `requer.arma.id` precisa coincidir com `resultado.substituiArma`. IDs de evolução
não podem se repetir ou colidir com armas base. Combinações idênticas de arma/passivo e níveis
também são rejeitadas. O resultado passa pela mesma validação de `WeaponDefinitionMapper`,
e preserva formato/mira da arma substituída. Diagnósticos incluem arquivo, ID e campo quando
o registro já pode ser identificado; erros de raiz/JSON são identificados pelo arquivo/caminho.
Exemplo: `upgrades.json: bow.nivel[3].modificadores[0].atributo/valor: ...`.

## Evoluções e snapshots

`EvolutionDefinition` expõe arma/passivo e níveis mínimos como dados para a B. O serviço
verifica apenas slot ocupado, existência do ID e arma substituída compatível. Não recebe
XP, personagem, nível atual ou passivo adquirido.

Enchanted Stone Sword e Mega TNT usam exatamente `evolutions.json` e o executor A7.
`CombatInventory::evolve()` mantém o slot e o mesmo objeto `Weapon`, conservando seu executor
e cooldown próprio. Troca o ID e a base pela definição completa da evolução e **descarta
todos os modificadores anteriores**, evitando aplicar upgrades da arma base duas vezes.
Não pode duplicar ID equipado. A substituição genérica `replace()` da A6 continua criando
uma arma pronta; evolução tem a operação distinta com preservação proporcional do cooldown.

Ataques já criados guardam o objeto imutável que receberam. Projéteis em voo conservam dano,
alcance, velocidade e ID; cargas conservam dano, raio, atraso, posição e ID. Só ataques novos
recebem a definição efetiva atual e publicam `enchanted_stone_sword` ou `mega_tnt` nos eventos.
Descarte de modificadores e cooldown proporcional são decisões técnicas da A8.

## Verificações e limites

Foram adicionados 161 cenários de teste. Cobrem os 16 upgrades reais, ambas as evoluções, fórmula/ordem, contagem inteira,
removibilidade, atomicidade, erros de configuração, cooldown, snapshots e identidade de eventos.
A integração compara todos os 300 estados por passo e os eventos em 30/60/144/240 FPS e
quadros irregulares, com upgrades nas quatro armas, evoluções e remoção de efeito.
Não há tempo real ou espera nos testes. Os hashes dos três JSONs são conferidos pela suíte.

`composer check`: 796 testes e 8.722 asserções; Pint e PHPStan nível 9 aprovados.
Suíte completa com `php -d ffi.enable=false vendor/bin/phpunit`: mesmos resultados.
`git diff --check` passou. As verificações são repetidas após o merge e registradas
no relatório de entrega.

A B mantém XP, level up, níveis, concessão e elegibilidade; C mantém inimigos/conteúdo;
D mantém entrada, apresentação e feedback. Nenhum desses sistemas, pool ou protótipo
vertical foi implementado. Para A9 continuam pendentes reutilização segura dos objetos,
capacidade e política de pool cheio; A10 conserva integração final e medição de desempenho.
