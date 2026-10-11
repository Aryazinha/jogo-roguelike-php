# A6 — núcleo de armas e inventário de combate

A6 concluída: definições, carga validada e execução genérica automática com cooldown
individual, sem dependência gráfica. A7 está em [MIRAS_ARMAS.md](MIRAS_ARMAS.md). Este registro descreve
decisões técnicas da entrega; não constitui nova ratificação humana ou balanceamento.

## Organização e responsabilidades

| Classe | Local | Responsabilidade |
| --- | --- | --- |
| `WeaponDefinition` | `Domain/Weapon` | Dados finais readonly, validados e compartilháveis |
| `WeaponCatalog` | `Domain/Weapon` | Catálogo imutável por ID, na ordem da configuração |
| `Weapon` | `Domain/Weapon` | Estado individual e tentativa automática por callback |
| `CombatInventory` | `Domain/Weapon` | Slots de combate e atualização em ordem |
| `WeaponConfigLoader` | `Core/Config` | Leitura de arquivo e decodificação JSON |
| `WeaponDefinitionMapper` | `Core/Config` | Validação da estrutura e mapeamento para definições |
| `JsonDuplicateKeyValidator` | `Core/Config` | Verificação interna de chaves repetidas antes do mapeamento |

O domínio recebe definições, sem caminhos, `file_get_contents`, `json_decode`, relógio real,
Raylib ou FFI. Compartilhar uma definição é seguro: cada `new Weapon` cria seu próprio
`Cooldown` privado. A API expõe `definition()`, `isReady()` e `remainingSeconds()`, sem
permitir acesso direto ao objeto de cooldown.

## Definições e unidades

O ID vem da chave em `armas`. Não há transformação do ID, inclusive para chaves numéricas
textuais. Strings obrigatórias são não vazias. As combinações e faixas seguem
[CONTRATOS.md §4](CONTRATOS.md#4-esquema-dos-arquivos-de-configuração).

| JSON | Propriedade da definição | Unidade / validação |
| --- | --- | --- |
| chave da arma / `nome` | `id` / `name` | Strings não vazias |
| `dano` | `damage` | HP, finito ≥ 0 |
| `cooldownSegundos` | `cooldownSeconds` | Segundos, finito > 0 |
| `mira` / `formato` | `targetingId` / `shapeId` | Identificadores de combinação conhecida |
| `alcance` | `range` | Pixels, finito > 0 |
| `anguloGraus` | `angleDegrees` | Graus, finito > 0 e ≤ 360; meia-lua |
| `projeteis` | `projectileCount` | Inteiro ≥ 1; projétil |
| `velocidadeProjetil` | `projectileSpeed` | Pixels/s, finito > 0; projétil |
| `raioExplosao` | `explosionRadius` | Pixels, finito > 0; explosão |
| `atrasoDetonacaoSegundos` | `detonationDelaySeconds` | Segundos, finito ≥ 0; explosão |

Os campos específicos são obrigatórios apenas no formato correspondente. Propriedades
opcionais `null` representam ausência, sem números fictícios; um valor de campo de outro
formato é rejeitado. No JSON, o campo de outro formato é rejeitado mesmo se contiver `null`.
Alcance, ângulo e raio de explosão continuam distintos. Os identificadores apenas descrevem
dados: não implementam mira, geometria de ataque, projéteis ou aplicação de dano.

## Carregamento validado

```php
use Jogo\Core\Config\WeaponConfigLoader;

$catalog = (new WeaponConfigLoader())->load($weaponsPath);
$definition = $catalog->get('stone_sword');
```

O caminho é fornecido pela composição. `decode($json, $source)` aceita JSON em memória;
`WeaponDefinitionMapper::map($decoded, $source)` aceita a raiz já decodificada como objeto.
`WeaponCatalog::get()` rejeita ID desconhecido; `has()`, `all()` e `count()` permitem consulta.
`all()` devolve uma cópia da coleção na ordem original, com definições imutáveis.

A carga valida raiz, `_meta`, objeto `armas` não vazio, objetos individuais, campos comuns,
específicos e combinações. Números contínuos aceitam inteiro ou float JSON e são convertidos
para float. `projeteis` exige inteiro, rejeitando fração, string, booleano e `1.0`.
Não há defaults silenciosos. Chaves repetidas são rejeitadas antes que o resultado de
`json_decode` esconda a duplicação, inclusive quando escritas com escapes Unicode.

Campos desconhecidos de gameplay são erros. Metadados `_...` permanecem fora do domínio;
`_fonte` e `_nota` são validados, sem reescrita. `_meta.arquivo` deve ser `weapons.json`,
`requisitos` uma lista de IDs não vazios e `nota` uma string. A lista pode ser vazia, pois
o contrato não impõe quantidade mínima de referências. Outros metadados podem ser ignorados.

Erros de arquivo, JSON ou validação usam `InvalidArgumentException`, identificando arquivo
e, quando aplicável, ID e campo. Essa escolha interna não define uma exceção universal para
todos os loaders do projeto. Não há validação cruzada de personagens, upgrades ou evoluções.

Os quatro registros existentes são carregados com todos os valores originais. O arquivo
`config/weapons.json` permanece literalmente inalterado, incluindo `_fonte`, `_nota` e
valores provisórios. SHA-256 verificado antes/depois:
`5C6F3045D93353923578DF5CF3DAE5479529129BB57139807BAB598497E43610`.

## Ciclo de execução

`new Weapon($definition, $execute)` começa pronta. O callback tem assinatura
`callable(WeaponDefinition): bool` e identifica a definição que está tentando executar.
A7 materializa essa porta com `WeaponAttackSystem`, sem alterar o núcleo.

Em `update($deltaSeconds)`:

1. O cooldown avança com segundos finitos ≥ 0.
2. Se estiver bloqueada, a arma retorna `false` sem chamar a porta.
3. Se estiver pronta, chama a porta uma vez.
4. `false` mantém a arma pronta; `true` inicia o cooldown completo e retorna `true`.

Uma atualização faz no máximo uma tentativa por arma, mesmo com `dt` grande: não recupera
ataques retroativos. O jogo fornece passos fixos de `1/60 s`. `dt = 0` não avança o relógio,
mas permite tentar uma arma já pronta; um quadro sem passos do loop não chama `update()`.
Exceções da porta são propagadas sem iniciar cooldown. Tempo já avançado não sofre rollback.
Atualização recursiva da mesma arma ou inventário é rejeitada com `LogicException`.

O retorno indica aceitação da execução, não presença de alvo. A6 não definiu a política sem
alvo. A7 adota a decisão técnica documentada em [MIRAS_ARMAS.md](MIRAS_ARMAS.md): arco/TNT
recusam sem alvo, espada/tocha aceitam. A6 não fornece alvo, inimigo, projétil ou `DamageSystem`
ao núcleo genérico; esses colaboradores permanecem externos à classe `Weapon`.

O `Cooldown` mantém sua API e duração imutável. A soma compensada do tempo corrige resíduos
de ponto flutuante: 120 passos de `1/60 s` liberam uma duração de 2 s no passo esperado.
Não foi introduzido epsilon absoluto ou duração mínima; cooldowns muito pequenos continuam
válidos. `tryStart()` e `reset()` também reiniciam o estado da soma.

## Inventário e slots

`new CombatInventory()` tem capacidade exatamente 2, conforme RF15. Uma capacidade positiva
alternativa pode ser fornecida apenas no construtor para composição/testes; não existe
upgrade de capacidade. Os slots começam em 0, são contíguos e mantêm a ordem de adição.

| Operação | Regra |
| --- | --- |
| `add($definition, $execute): int` | Cria nova arma pronta e retorna o próximo slot livre |
| Terceira arma no inventário padrão | `LengthException`, sem modificar os slots |
| ID duplicado na adição | `InvalidArgumentException`, inclusive antes de verificar capacidade |
| `at($slot): ?Weapon` | Slot válido vazio retorna `null`; fora da capacidade gera `OutOfBoundsException` |
| `find($id): ?Weapon` | ID não equipado retorna `null`; ID vazio é inválido |
| `replace($slot, $definition, $execute)` | Exige slot ocupado; mantém a posição e cria nova arma pronta |
| Substituir por ID equipado | Rejeitado com `InvalidArgumentException`, inclusive o mesmo ID do próprio slot |
| `weapons()` | Cópia da lista em ordem, com referências às armas para consulta |
| `update($dt): int` | Atualiza todos os slots em ordem e retorna o total de execuções aceitas |

Substituir pelo mesmo ID é rejeitado para não reiniciar cooldown silenciosamente. Armas
com definições iguais, criadas separadamente, continuam independentes; um inventário exige
IDs distintos. Na substituição válida, a arma antiga não é alterada e os demais slots
conservam seus objetos e tempos.

Cada `update()` percorre um snapshot dos slots presentes no início. Adição ou substituição
dentro de uma porta afeta a próxima atualização; a arma antiga do snapshot ainda participa
da atualização corrente. Exceções interrompem o percurso e são propagadas, sem rollback
das armas já atualizadas. Isso é uma regra técnica de iteração, sem elegibilidade da B.

## Composição com o loop e outras frentes

```php
use Jogo\Domain\Weapon\CombatInventory;

$inventory = new CombatInventory();
$inventory->add($catalog->get('stone_sword'), $attemptSword);
$inventory->add($catalog->get('bow'), $attemptBow);

// Dentro do callback de atualização existente de GameLoop:
$inventory->update($deltaSeconds);
```

O exemplo recebe callbacks externos; não fornece execução concreta de espada ou arco.
A ordem de sistemas permanece em [LOOP_FIXO.md](LOOP_FIXO.md): movimento e índice atualizados
antes das armas; impactos e dano nas etapas seguintes. A6 fornece somente este componente
para o callback de atualização, sem uma nova composição de partida.

Quando `GameLoop` pausa, não há passos de atualização e nenhum cooldown avança. A apresentação
continua independente. A retomada descarta tempo real pausado e conserva os dois estados.
Nem arma nem inventário conhece tela de level up ou entrada real.

B decidirá concessão e elegibilidade e enviará comandos de adição/substituição; A mantém
o estado de combate. C fornecerá os dados do mundo usados pela A7. D fornecerá apresentação
e áudio. Não há XP, níveis, upgrades, `Stats`, `StatModifier`, inimigos ou interface visual.

## Verificação e pendências

Foram adicionados 234 cenários à suíte: definições e imutabilidade, catálogo, arquivo real,
erros de configuração, chaves duplicadas, execução aceita/recusada, cooldowns independentes,
slots, substituição e pausa/retomada. Regressões verificam precisão de `Cooldown` e a barreira
arquitetural contra JSON no domínio.

Os testes de integração comparam a sequência completa de ataques de duas armas em
30/60/120/144/240 FPS, com os mesmos 150 passos lógicos, além de carregar espada/arco reais
com portas falsas. Não aplicam dano. `composer check` passou: 542 testes, 7.590 asserções,
Pint e PHPStan nível 9 sem erros. A suíte de integração com `ffi.enable=false` passou com
21 testes e 2.686 asserções. `git diff --check` integra a revisão antes do commit.

Mira/impacto e consumo sem alvo foram implementados na A7 como decisões técnicas, sem nova
ratificação humana. Permanecem integração com dados da C, modificadores e troca da duração
de cooldown (A8), aquisição pela B e apresentação pela D. Os nomes B/C/D continuam como informados. Nenhuma dessas
pendências bloqueia o núcleo genérico da A6; A8 não foi iniciada.
