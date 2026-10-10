# Entrega A5 — dano, morte e eventos

A A5 materializa o dano genérico do RF08 e os quatro eventos de combate que dão suporte
à integração do RNF05. Não implementa armas, inimigos, XP, drops ou feedback audiovisual.
A6 está documentada em [ARMAS_INVENTARIO_COMBATE.md](ARMAS_INVENTARIO_COMBATE.md); A7 não iniciada.

**Estado:** critérios locais da A5 comprovados com 127 novos cenários de teste.
`composer check` passou com 308 testes e 6.559 asserções, Pint e PHPStan nível 9 sem erros.
O fluxo de integração exige repetir as verificações após o merge e sincronizar a `main`.

## Componentes e responsabilidades

| Componente | Responsabilidade |
| --- | --- |
| `Domain/Entity/Damageable` | Capacidade ratificada: estende `Collidable` e oferece `health(): Health` |
| `Domain/Value/Damage` | Quantidade solicitada e origem já resolvidas; final readonly |
| `Domain/System/DamageSystem` | Aplicar dano, desativar o mesmo alvo e publicar eventos |
| `Domain/Event/DomainEvent` | Interface marcadora ratificada |
| `Domain/Event/EventBusInterface` | Porta de inscrição e publicação usada pelo domínio |
| `Core/EventBus` | Despacho síncrono por classe exata, com listeners em ordem |
| Quatro eventos em `Domain/Event/` | Snapshots imutáveis para consumo por outras frentes |
| `Domain/Value/CombatValidation` | Auxiliar interno das validações comuns; não transforma valores |
| `tests/Support/FakeDamageable` | Alvo mínimo com vida, sem conteúdo ou progressão |

Namespaces de produção começam por `Jogo\`; o código continua compatível com PHP 8.3+.
O domínio importa somente seus próprios contratos e PHP padrão. Não consulta relógio,
arquivos, configuração, FFI, Raylib, áudio ou serviços externos.

## Damageable, Entity e Health

O contrato da A1 permanece exatamente:

```php
interface Damageable extends Collidable
{
    public function health(): Health;
}
```

`DamageSystem` recebe **`Entity&Damageable`**: o mesmo objeto precisa ser uma entidade
e oferecer vida por composição. Assim a A usa `Entity::deactivate()` sem acrescentar
esse método a `Damageable` e sem colocar HP em toda entidade. `Entity` e `Health` não
precisaram de alterações. O fornecedor do alvo mantém seu componente `Health` estável;
o sistema o lê uma vez por aplicação e altera esse componente do mesmo alvo.

Um alvo inicialmente com HP zero é desativado sem publicar uma morte retroativa, pois não
houve dano efetivo nesta aplicação. Alvos inativos ou com ID já terminal são ignorados.
O sistema não implementa cura, regeneração ou ressurreição; a possibilidade isolada de
`Health::heal()` aumentar HP zero não reativa uma entidade nem libera seu ID terminal.

## Dano e origem

```php
new Damage(amount: 2.5, sourceEntityId: 'attacker-instance', weaponId: 'weapon-content-id');
```

- `amount`: HP solicitado, finito e `>= 0`; aceita frações.
- `sourceEntityId`: ID da entidade que causou o ataque, ou `null` se desconhecida.
  O chamador resolve o dono do ataque antes da chamada; não passa o ID temporário do projétil.
- `weaponId`: ID de conteúdo da arma, ou `null` quando ausente. Não é uma arma concreta.
  A validação não limita os IDs a uma lista atual, permitindo os IDs de evolução futuros.
- IDs presentes não podem estar em branco; são preservados sem remover espaços de IDs válidos.

O DTO é final readonly. Ele não calcula atributos, cria ataques, define cooldown, valida
conteúdo de JSON ou decide se um contato deve causar dano.

## API do DamageSystem

| Método | Dados externos |
| --- | --- |
| `damageEnemy(Entity&Damageable $target, Damage $damage, string $enemyTypeId, bool $isBoss, float $elapsedSeconds): float` | Tipo e classificação de boss fornecidos por C; origem e dano fornecidos pelo chamador |
| `damagePlayer(Entity&Damageable $target, Damage $damage, float $elapsedSeconds): float` | Dano lógico de inimigo/ataque, com origem resolvida pelo chamador |

Ambos retornam o HP efetivamente retirado, obtido de `Health::damage()`. Overkill é limitado
à vida restante, inclusive no payload: solicitar 100 HP contra 5 HP aplica e publica 5 HP.
Dano zero não publica eventos. O método para jogador não acrescenta `weaponId` aos eventos,
pois as assinaturas ratificadas de `PlayerDamaged` e `PlayerDied` não têm esse campo.

Tempo é de simulação em segundos, finito e `>= 0`, recebido de fora. `enemyTypeId` é não vazio.
Esses argumentos são validados antes de alterar vida, inclusive quando o alvo seria ignorado.
`Damage` e os construtores dos eventos validam seus campos na entrada, com
`InvalidArgumentException`; coordenadas continuam validadas por `Vector2`.

## Ordem de dano e morte

Para dano efetivo positivo, o fluxo é:

1. Aplicar `Health::damage()` e guardar o HP restante e uma cópia imutável da posição.
2. Publicar `EnemyHit` ou `PlayerDamaged`.
3. Se foi fatal, desativar o alvo.
4. Publicar `EnemyKilled` ou `PlayerDied`.

O listener do dano fatal observa HP zero e, normalmente, atividade ainda verdadeira.
O listener da morte observa o alvo inativo. Os dois eventos compartilham o mesmo snapshot
de posição e o mesmo `elapsedSeconds`, mesmo se um listener deslocar o objeto depois.
Eventos não guardam `Entity`, `Health`, callbacks ou qualquer referência mutável de alvo.

A morte de boss usa `EnemyKilled` com `isBoss=true`. O sistema não infere essa classificação
pelo ID, tipo, nome ou quantidade de HP; não publica `PlayerDied` para morte de boss.

## Morte única e ciclo de vida por partida

A composição cria **um `EventBus` e um `DamageSystem` por partida**, compartilhados pelos
produtores de dano, e registra os listeners dessa partida. Ao iniciar outra partida, cria
novas instâncias; não reutiliza o barramento nem o registro de mortes. Não há singleton,
estado estático, método de reset, persistência ou replay.

`DamageSystem` conserva apenas um conjunto de IDs terminais, não os objetos eliminados.
No golpe fatal, reserva o ID antes de chamar listeners. Chamadas posteriores, inclusive
com outro objeto ou outra função de dano usando o mesmo ID, retornam zero sem eventos.
Um alvo encontrado já morto também tem seu ID marcado como terminal, sem evento retroativo.
O prefixo interno `id:` preserva IDs numéricos como strings.

Isso não autoriza reutilizar um ID: a A1 exige identidade única durante a partida. Também
não coordena dois sistemas de dano criados erroneamente para a mesma partida; a composição
deve compartilhar a instância. Remoção dos objetos do mundo permanece externa.

## EventBus síncrono e falhas de listeners

`subscribe()` preserva o contrato genérico `class-string<T>` e `callable(T): void`.
`publish()` aceita `DomainEvent`. O barramento usa a identidade canônica da classe e chama
somente seus listeners exatos; inscrever `DomainEvent` não cria um assinante universal.
Uma inscrição de `EnemyHit` não recebe `EnemyKilled`.

Cada inscrição recebe uma chamada, na ordem de registro, antes de `publish()` retornar.
Inscrever o mesmo callable duas vezes gera duas inscrições; a composição deve evitar
registro acidental duplicado. Novos listeners inscritos durante uma publicação participam
da próxima publicação. Publicar sem listeners é válido.

Exceções de listeners chegam ao chamador e interrompem o despacho corrente. Não há rollback
do HP aplicado nem tentativa automática de republicação. Se o listener de dano fatal falhar,
um `finally` desativa o alvo e mantém o ID reservado, mas o evento de morte não é publicado
nessa execução interrompida. Se o listener de morte falhar, a morte já foi publicada e não
se repete. Esses casos são testados: preservam o estado terminal sem esconder o erro.
A composição precisa tratar a falha; A5 não define uma tela de erro ou recuperação de partida.

Assinantes não devem reaplicar dano ou eliminar entidades. Consequências de mundo da C
precisam de um ponto seguro combinado com Application, ainda pendente; nenhuma fila de
comandos foi implementada nesta entrega.

## Payloads ratificados

Todos os eventos são finais readonly, implementam `DomainEvent` e expõem propriedades públicas
na mesma ordem e com os mesmos tipos dos construtores de [CONTRATOS.md](CONTRATOS.md) §3.2.

| Evento | Campos |
| --- | --- |
| `PlayerDamaged` | `playerId`, `position`, `appliedDamage`, `remainingHealth`, `sourceEntityId`, `elapsedSeconds` |
| `EnemyHit` | `enemyId`, `enemyTypeId`, `position`, `appliedDamage`, `remainingHealth`, `sourceEntityId`, `weaponId`, `elapsedSeconds` |
| `EnemyKilled` | `enemyId`, `enemyTypeId`, `isBoss`, `position`, `sourceEntityId`, `weaponId`, `elapsedSeconds` |
| `PlayerDied` | `playerId`, `position`, `sourceEntityId`, `elapsedSeconds` |

IDs presentes são não vazios. Dano aplicado é finito e `> 0`; HP restante e tempo são finitos
e `>= 0`. Posição é `Vector2`, final readonly, em pixels do mundo. Origem desconhecida e
arma ausente são `null`, sem strings vazias ou IDs inventados.

## Integração com A4 e entregas futuras

A4 fornece candidatos pela grade e confirma interseções circulares. A composição escolhe
os contatos/ataques que devem produzir dano e chama a A5 com os dados já resolvidos:

```php
// Exemplo de composição: colaboradores e metadados são fornecidos externamente.
if ($collision->intersects($attacker, $target)) {
    $damageSystem->damageEnemy($target, $resolvedDamage, $enemyTypeId, $isBoss, $elapsedSeconds);
}
```

Tangência continua sendo interseção geométrica. A5 não define frequência de contato,
invulnerabilidade, travessia/consumo de projétil, seleção de alvos ou resposta física.
A6 fornece o núcleo genérico de armas; A7 fornecerá a execução concreta dos ataques,
sem implementação neste sistema de dano.

- **B:** poderá consumir `EnemyKilled` para XP, contagem e pontuação; nada disso é calculado aqui.
- **C:** fornecerá entidades `Entity&Damageable`, tipo e classificação de boss, além das
  consequências de conteúdo. A5 não cria inimigos, IA, drops, baús, ondas ou divisões.
- **D:** poderá consumir os quatro eventos para som e efeitos. A5 não desenha, escolhe assets,
  atualiza HUD ou encerra telas.

`LevelUp`, `ChestOpened`, `ItemPicked` e `WaveStarted` não foram implementados.
A lista fechada de eventos, requisitos oficiais, JSON e marcadores B/C/D foram preservados.

## Verificações

Os testes cobrem dano parcial/fatal, overkill, zero, inatividade, morte prévia e por ID,
origem, arma, boss explícito, snapshots, tempo, ordem, desativação, ausência de listeners,
validações dos payloads, despacho exato, ordem de inscrições, erros e isolamento por partida.
A integração usa somente entidades falsas, colisão tangente da A4 e tempo do loop fixo,
incluindo pausa. O teste de arquitetura agora verifica também `Core/EventBus`, além de todo
o domínio, contra dependências gráficas e I/O.

```bash
composer check
git diff --check
php -d ffi.enable=false vendor/bin/phpunit tests/Integration
```

Pendências preservadas: política de contato/invulnerabilidade/impacto, integração dos
consumidores B/C/D e ponto seguro para mudanças de mundo. Não bloqueiam a infraestrutura A5.
