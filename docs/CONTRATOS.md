# Contratos compartilhados

As assinaturas e os dados que mais de uma frente usa. Esta revisão cobre somente a
[entrega A1](PLANO_FRENTE_A.md): registra o código existente e apresenta propostas para
evitar incompatibilidades antes de implementar os sistemas de jogo.

**Estado:** proposta para revisão humana; contratos não congelados nem ratificados.
**Revisão documental:** 09/10/2026, preparada por Codex a pedido da Frente A.
**Participantes da reunião:** ainda não informados; nenhuma reunião registrada nesta revisão.

O [enunciado](enunciado/) é a fonte dos requisitos; [PLANO.md](PLANO.md) registra a
arquitetura e as confirmações já obtidas do professor (§5.0). Nada aqui muda essas fontes
ou os valores dos JSON. Assinaturas, unidades e políticas novas abaixo são
**[SUGESTÃO A1]**, não requisitos adicionais do professor.

| Marca | Significado |
| --- | --- |
| Existente | Assinatura e comportamento conferidos em `src/`; isso não significa aprovação da equipe |
| Proposta A1 | Contrato documentado, ainda sem implementação e sujeito à confirmação humana |
| Pendente fora de A1 | Responsabilidade de outra entrega ou frente; não definida nesta revisão |

## Regras

1. Estes contratos são definidos na **reunião da semana 1**, antes de qualquer código de jogo.
2. Depois de congelados, **só mudam com a concordância das quatro frentes**, e a mudança é
   registrada aqui com data.
3. Cada frente programa contra o contrato, não contra a implementação da outra frente.
4. O que não está aqui é decisão interna de cada frente, e não precisa de acordo.

**Congelado em:** pendente de confirmação das quatro frentes.
**Presentes na ratificação:** pendente de reunião e registro humano.

Para ratificar, registrar data, nomes dos participantes, contratos aprovados e ressalvas no
histórico. A data desta revisão não substitui a data de aprovação. Até lá, os exemplos PHP
são especificações para discussão, não arquivos a adicionar ao autoload.

### Convenções comuns propostas

| Dado | Unidade e regra |
| --- | --- |
| Posição, deslocamento, alcance e raio | Pixels do mundo, independentes da câmera e da janela; `x` cresce para a direita e `y` para baixo |
| Velocidade | Pixels por segundo; deslocamento = velocidade × segundos |
| Direção | `Vector2` sem unidade, normalizado; zero significa ausência de direção |
| Tempo, `deltaSeconds`, cooldown e atraso | Segundos finitos, nunca milissegundos nem número de quadros |
| Vida e dano | HP em `float`; não arredondar para inteiro no domínio |
| Ângulo | Graus nos JSON; conversão interna para radianos não altera os dados |
| Percentual | Fração: `0.25` = +25%, `-0.15` = -15% |
| Identidade de entidade | `string` não vazia, única durante a partida; não reutilizar para outra entidade após uma morte |
| Identidade de conteúdo | Chave estável dos JSON, como `stone_sword` ou `zombie`; diferente do ID da instância |

Todos os números de entrada devem ser finitos. Dados inválidos falham no ponto de entrada
com mensagem clara; não são substituídos silenciosamente por zero. Limites numéricos que
já existem no código estão descritos na §2; limites novos nas demais seções são propostas.

---

## 1. Interfaces de apresentação

`RendererInterface`, `AudioInterface`, `InputInterface` — a fronteira entre o domínio e o
Raylib. Nenhuma classe de regra de jogo chama FFI; toda a apresentação passa por aqui.

`RendererInterface` (sprite, texto, forma, HUD e apresentação do quadro) e `AudioInterface`
(efeito, música e volume) continuam **pendentes fora de A1**, sob responsabilidade da D.
Esta entrega não define suas APIs nem implementações concretas.

### Entrada abstrata — proposta A1, confirmar com D

```php
// Jogo\Infrastructure\Input\InputInterface
interface InputInterface
{
    public function poll(): InputState;
}

// Jogo\Domain\Value\InputState — dados imutáveis
final readonly class InputState
{
    public function __construct(
        public Vector2 $movement,
        public ?Vector2 $facing = null,
        public bool $pauseRequested = false,
    ) {}
}
```

Nos exemplos, tipos curtos devem ser importados de seus namespaces indicados. `Vector2`
sempre significa `Jogo\Domain\Value\Vector2`. As declarações propostas mostram a API,
omitindo os corpos de validação; não são implementações prontas.

- `movement`: eixos horizontal e vertical finitos em `[-1, 1]`. `(0, 0)` significa parado.
  A normaliza o movimento diagonal na A3, antes de aplicar a velocidade fornecida pela B.
- `facing`: direção unitária desejada, ou `null` para conservar o último olhar válido.
  O vetor zero não é uma direção válida nesse campo. A direção inicial ainda depende de
  acordo A/D; a ausência de movimento não deve apagar o olhar.
- `pauseRequested`: pulso de uma nova solicitação, não um botão mantido. Application trata
  o pulso uma vez; ele não é repetido em cada atualização de passo fixo.
- Application chama `poll()` uma vez por quadro, trata solicitações de tela e entrega os
  mesmos dados de movimento às atualizações desse quadro. A entrada não recebe `dt` nem
  movimenta entidades. O domínio recebe `InputState`, sem importar `InputInterface`.
- D traduz teclado ou mouse para esses dados. Teclas, conversão de coordenadas de tela para
  mundo e política de olhar precisam ser acordadas com A. RF04 permite teclado **ou** mouse;
  este contrato não exige implementar os dois. Botões de menu ficam para a API de telas da D.
- Não há comando de ataque manual: RF06 mantém as armas automáticas. Solicitar pausa manual
  é uma sugestão de interface; a pausa de level up continua exigida pelo RF14.

A declara a necessidade do estado; D fornece a entrada concreta; uma entrada programada
poderá usar a mesma API nos testes futuros. Nenhuma delas é implementada na A1.

---

## 2. Entidade base e value objects

### 2.1 Assinaturas existentes — conferir antes de integrar

Estas quatro classes pertencem à A e já estão implementadas e testadas. B e C compõem suas
entidades com elas; D lê seus dados para apresentação. A A1 não altera essas APIs.

| Classe e caminho | API pública existente |
| --- | --- |
| `Jogo\Domain\Entity\Entity` — `src/Domain/Entity/Entity.php` | Classe abstrata; `__construct(string $id, Vector2 $position, float $collisionRadius)`; métodos finais `id(): string`, `position(): Vector2`, `collisionRadius(): float`, `isActive(): bool`, `moveTo(Vector2 $position): void`, `translate(Vector2 $displacement): void`, `deactivate(): void` |
| `Jogo\Domain\Value\Vector2` — `src/Domain/Value/Vector2.php` | Classe final readonly; `__construct(float $x, float $y)`; estático `zero(): self`; `x(): float`, `y(): float`, `add(self $other): self`, `subtract(self $other): self`, `scale(float $factor): self`, `lengthSquared(): float`, `length(): float`, `distanceTo(self $other): float`, `normalized(float $epsilon = 1.0E-9): self`, `equals(self $other, float $epsilon = 1.0E-9): bool` |
| `Jogo\Domain\Value\Health` — `src/Domain/Value/Health.php` | Classe final; `__construct(float $maximum, ?float $current = null)`; `current(): float`, `maximum(): float`, `ratio(): float`, `isDead(): bool`, `damage(float $amount): float`, `heal(float $amount): float` |
| `Jogo\Domain\Value\Cooldown` — `src/Domain/Value/Cooldown.php` | Classe final; `__construct(float $durationSeconds)`; `durationSeconds(): float`, `remainingSeconds(): float`, `isReady(): bool`, `tryStart(): bool`, `advance(float $deltaSeconds): void`, `reset(): void` |

**Entity:** começa ativa; ID e raio são imutáveis; raio finito `>= 0`; ID com `trim($id)`
vazio é rejeitado, mas o ID válido é armazenado sem transformação. Mover troca a posição ou
soma um deslocamento; desativar só muda a atividade. Não há reativação, vida, velocidade,
morte automática ou evento na base. Unicidade dos IDs é responsabilidade futura de quem
cria e registra entidades, não uma validação do construtor atual.

**Vector2:** coordenadas e fator de escala finitos. Operações devolvem novos vetores;
normalizar comprimento `<= epsilon` devolve zero. A tolerância precisa ser finita e `>= 0`;
`equals()` compara cada componente com essa tolerância. Não impõe limites de mapa.

**Health:** máximo finito `> 0`, vida inicial entre zero e máximo; `null` inicia cheia.
Dano e cura finitos `>= 0`; retornam HP efetivamente retirado/restaurado, limitados pela vida
disponível/faltante. `ratio()` retorna a fração `[0, 1]`; morte é vida zero. Máximo imutável.
`heal()` pode aumentar vida que estava em zero: impedir cura de entidade eliminada será
responsabilidade do sistema que chama esse método, não uma regra existente em `Health`.

**Cooldown:** duração finita `> 0`, imutável; começa pronto. `tryStart()` retorna `true`
e carrega a duração somente quando pronto; bloqueado retorna `false` sem reiniciar.
`advance()` aceita segundos finitos `>= 0` e limita o restante a zero; `reset()` torna pronto.
RF20 exige uma instância por arma. A operação evita dois usos aceitos sem passagem de tempo,
mas não oferece sincronização entre threads.

As quatro classes rejeitam os argumentos inválidos descritos com `InvalidArgumentException`.
Elas não publicam eventos nem consultam relógio, arquivos ou Raylib. A política de pausa e
a troca de duração por upgrade ficam para acordo e implementação posteriores.

### 2.2 Stats — proposta A1, confirmar com B

`Jogo\Domain\Value\Stats`: classe final readonly, mapa imutável de atributos de um jogador
ou arma. API proposta:

| Assinatura | Semântica |
| --- | --- |
| `__construct(array $baseValues)` | Recebe `array<string, float>` e valida nomes e valores |
| `has(string $attribute): bool` | Indica se o atributo é aplicável e está presente |
| `value(string $attribute): float` | Lê um atributo presente |
| `values(): array` | Devolve o mapa `array<string, float>` por valor |

`value()` de atributo ausente deve falhar com `InvalidArgumentException`, sem presumir zero.
O mapa só contém atributos aplicáveis ao objeto, com nomes canônicos da §7.2 e valores
finitos válidos. Inteiros dos JSON são convertidos para `float` na carga; contagem de projéteis
continua sujeita à regra de integralidade da §7.2.

B fornece os valores base e os modificadores; A usa os valores finais no combate. O conjunto
de modificadores fica separado dos valores base: não aplicar novamente um percentual sobre
um resultado que já o inclui. A resolução será implementada na A8, não em A1. Alterar
`vidaMaxima` ou `cooldown` em `Stats` não muda por si só `Health` ou `Cooldown`; a política
de sincronização dessas instâncias é pendente A/B (§9).

### 2.3 Capacidades pequenas — sugestões condicionadas à aprovação

Proposta de namespace: `Jogo\Domain\Entity`. Ainda não existem arquivos dessas interfaces,
e `Entity` ainda não declara `implements`.

```php
interface Movable
{
    public function position(): Vector2;
    public function moveTo(Vector2 $position): void;
    public function translate(Vector2 $displacement): void;
}

interface Collidable
{
    public function id(): string;
    public function position(): Vector2;
    public function collisionRadius(): float;
    public function isActive(): bool;
}

interface Damageable extends Collidable
{
    public function health(): Health;
}
```

`Health` significa `Jogo\Domain\Value\Health`. As assinaturas de movimento e colisão
reaproveitam a API existente sem acrescentar velocidade à entidade base. `Damageable`
oferece a vida por composição apenas a quem pode receber dano; não exige que todo pickup,
baú ou projétil tenha HP. A aplica dano e elimina entidades; B controla cura e regeneração;
C fornece as entidades de conteúdo. Esses escritores devem coordenar a ordem das operações.
D e estratégias recebem cópias de leitura (§8), nunca `Damageable` nem `Health` mutável.

---

## 3. EventBus e a lista fechada de eventos

O barramento que desacopla o domínio do áudio, dos efeitos visuais e da pontuação. A lista de
eventos é **fechada**: acrescentar um evento novo é mudança de contrato.

Os sete eventos de feedback do RNF05 permanecem obrigatórios. `WaveStarted`, já previsto
neste documento, é uma sugestão da arquitetura, não um oitavo evento exigido pelo RNF05.

| Evento | Publicador proposto | Consumidores e finalidade | Estado nesta revisão |
| --- | --- | --- | --- |
| `PlayerDamaged` | A — dano aplicado | D — feedback; B — estatísticas | Payload proposto abaixo |
| `EnemyHit` | A — dano aplicado | D — feedback | Payload proposto abaixo |
| `EnemyKilled` | A — morte confirmada | B — XP e contagem; C — boss, baús e divisão de inimigos; D — feedback | Payload proposto abaixo |
| `LevelUp` | B — progressão | D — feedback e tela; Application — pausa | Payload pendente B/D, fora de A1 |
| `ChestOpened` | C — baús, em integração com B | B — recompensa; D — feedback | Payload pendente B/C/D, fora de A1 |
| `PlayerDied` | A — morte confirmada | B/Application — encerramento e resumo; D — feedback e tela | Payload proposto abaixo |
| `ItemPicked` | B/C — conforme o item, divisão a confirmar | B — efeito concedido; D — feedback | Payload pendente B/C/D, fora de A1 |
| `WaveStarted` | C — ondas | D — indicação de onda | Payload e inclusão final pendentes da equipe |

### 3.1 Barramento — proposta A1, confirmar responsáveis com B/C/D

```php
// Jogo\Domain\Event\DomainEvent — marcador dos eventos permitidos
interface DomainEvent {}

// Jogo\Domain\Event\EventBusInterface — porta usada pelo domínio
interface EventBusInterface
{
    /**
     * @template T of DomainEvent
     * @param class-string<T> $eventClass
     * @param callable(T): void $listener
     */
    public function subscribe(string $eventClass, callable $listener): void;
    public function publish(DomainEvent $event): void;
}
```

Proposta: A mantém a porta e a futura implementação `Jogo\Core\EventBus`; Application
conecta os assinantes de B, C e D na inicialização da partida. Assim o domínio importa apenas
`Jogo\Domain\Event\EventBusInterface`, sem depender de `Core` ou de apresentação.

Entrega síncrona em ordem de inscrição, uma chamada por inscrição para a classe exata do
evento; sem fila persistente, replay ou inscrição duplicada intencional. Uma publicação sem
assinantes é válida: dano e morte não dependem de áudio ou XP para funcionar. Na integração,
testes devem conferir os assinantes de feedback dos sete eventos do RNF05, conforme o plano.
Erros de assinantes não são silenciados. O ciclo de vida proposto é um barramento por partida;
trocar de partida não acumula assinantes antigos.

Assinantes não reaplicam dano nem eliminam entidades. Mudanças de mundo solicitadas por C
(por exemplo, gerar inimigos menores) devem ser aplicadas em ponto seguro fora da iteração
de combate. A/Application e C ainda precisam combinar esse ponto; A1 não cria fila de comandos.

### 3.2 Dados dos quatro eventos de combate — proposta A1

Todos são classes finais readonly em `Jogo\Domain\Event`, implementam `DomainEvent` e
expõem os campos do construtor para leitura. Não carregam referências a entidades mutáveis.

| Classe | Assinatura do construtor proposto |
| --- | --- |
| `PlayerDamaged` | `__construct(string $playerId, Vector2 $position, float $appliedDamage, float $remainingHealth, ?string $sourceEntityId, float $elapsedSeconds)` |
| `EnemyHit` | `__construct(string $enemyId, string $enemyTypeId, Vector2 $position, float $appliedDamage, float $remainingHealth, ?string $sourceEntityId, ?string $weaponId, float $elapsedSeconds)` |
| `EnemyKilled` | `__construct(string $enemyId, string $enemyTypeId, bool $isBoss, Vector2 $position, ?string $sourceEntityId, ?string $weaponId, float $elapsedSeconds)` |
| `PlayerDied` | `__construct(string $playerId, Vector2 $position, ?string $sourceEntityId, float $elapsedSeconds)` |

Regras dos campos:

| Campo | Significado |
| --- | --- |
| `playerId`, `enemyId` | ID da instância durante esta partida |
| `enemyTypeId` | Chave de conteúdo em `enemies.json`, fornecida por C; permite a B obter a regra de XP sem consultar uma entidade já removida |
| `isBoss` | Classificação fornecida por C; não inferir pelo nome ou quantidade de HP |
| `position` | Posição do alvo no momento do dano/morte, em pixels do mundo |
| `appliedDamage` | HP realmente retirado por `Health::damage()`, `> 0`; não o dano solicitado antes de limitar pela vida restante |
| `remainingHealth` | HP após o dano, `>= 0`; zero no golpe fatal |
| `sourceEntityId` | ID de quem causou o dano, quando conhecido; não o ID temporário do projétil; `null` se a origem não estiver disponível |
| `weaponId` | ID de conteúdo da arma que causou o dano, quando aplicável; `null` para outra origem |
| `elapsedSeconds` | Tempo de simulação da partida fornecido pelo loop, finito `>= 0`; não horário do sistema |

IDs presentes não podem ser vazios. O campo `weaponId` deverá aceitar também os IDs de
evolução definidos em `evolutions.json`, quando A8 existir. HP, dano e tempo são finitos.

Ordem proposta na A5: aplicar dano; publicar `EnemyHit`/`PlayerDamaged` somente se o dano
efetivo for positivo; se o alvo morreu, desativá-lo antes de publicar
`EnemyKilled`/`PlayerDied`. Uma morte é publicada uma única vez por ID na partida. Dano zero,
alvo já morto ou inativo não gera novo evento. O evento de dano fatal vem antes do evento de
morte e ambos usam o mesmo instante/posição. `Health` não publica nenhum deles sozinha.

B decide XP, bônus e pontuação a partir da morte e do tipo; A não sorteia XP, cria gemas,
contabiliza nível ou salva ranking. C decide as consequências de morte de conteúdo, incluindo
boss e drops. A morte do boss não é `PlayerDied`: o fluxo de vitória e continuação segue
`PLANO.md` §1.3. D escolhe som e efeito a partir dos dados; o combate não fornece IDs de assets.

---

## 4. Esquema dos arquivos de configuração

Esta seção descreve a estrutura **existente** de `config/weapons.json` e propõe as regras de
validação que A e B precisam confirmar. Não cria loader, JSON Schema executável ou novos
campos. A carga é I/O de `Core/Config` conforme o plano; o domínio recebe dados validados.

### 4.1 Estrutura e campos obrigatórios propostos

Raiz: objeto com `_meta` e `armas`. `_meta` contém `arquivo` (string, `weapons.json`),
`requisitos` (lista de IDs de requisitos) e `nota` (string). `armas` é um objeto não vazio
indexado por ID de conteúdo único, como `stone_sword`. O ID vem da chave, sem campo `id`
redundante. Campos começando com `_` são metadados; não viram atributos de combate.

| Campo de cada arma | Tipo JSON | Unidade / validação proposta |
| --- | --- | --- |
| `nome` | string | Não vazia, para apresentação |
| `dano` | number | HP, finito `>= 0` |
| `cooldownSegundos` | number | Segundos, finito `> 0`, compatível com `Cooldown` |
| `mira` | string | Identificador de estratégia da tabela abaixo |
| `formato` | string | Identificador de execução da tabela abaixo |
| `alcance` | number | Pixels, finito `> 0`; distância de seleção/ataque, não raio de colisão da entidade |
| `_fonte` | string | `enunciado` ou `provisorio`; preservar literalmente |
| `_nota` | string | Explica a procedência e os valores provisórios; preservar o texto existente |

| Combinação existente `mira` / `formato` | Campos adicionais obrigatórios propostos | Semântica geométrica proposta |
| --- | --- | --- |
| `direcao_do_olhar` / `meia_lua` | `anguloGraus`: number finito, `0 < valor <= 360` | `alcance` é o raio do setor; ângulo é sua abertura total, centrada no olhar |
| `inimigo_mais_proximo` / `projetil` | `projeteis`: integer `>= 1`; `velocidadeProjetil`: number finito `> 0` | `alcance` limita a seleção pelo centro do alvo e a distância de viagem; velocidade em px/s |
| `inimigo_aleatorio` / `area` | `raioExplosao`: number finito `> 0`; `atrasoDetonacaoSegundos`: number finito `>= 0` | `alcance` limita a seleção; explosão centrada na posição selecionada, com raio em px e atraso em s |
| `area_ao_redor` / `pulso` | Nenhum | `alcance` é o raio do pulso centrado no jogador |

Essas combinações são as quatro atuais, não um limite de quatro armas. Novas combinações
precisam de contrato de comportamento antes de entrar no registro de estratégias. A área
de efeito é representada por geometria (`alcance`, `anguloGraus`, `raioExplosao`), não por
um atributo novo chamado `area`. Não exigir `projeteis` ou `velocidadeProjetil` de armas sem
projétil nem criar valores fictícios para preencher esses campos.

Alcance medido até o centro para seleção é sugestão; a inclusão do raio de colisão na
detecção de impacto será acordada A/C. A unidade e a distinção entre alcance de seleção e
raio de explosão precisam ser ratificadas antes da A7.

### 4.2 Procedência preservada

| ID existente | Dados que ficam preservados | Observação de procedência existente |
| --- | --- | --- |
| `stone_sword` | Dano `2.5`, cooldown `2.0`, olhar e meia-lua | `_fonte: enunciado`; alcance `90` e ângulo `120` descritos como provisórios em `_nota` |
| `bow` | Dano `3.2`, cooldown `1.8`, alvo mais próximo e flecha | `_fonte: enunciado`; alcance `320`, quantidade `1` e velocidade `420` descritos como provisórios em `_nota` |
| `tnt` | Todos os campos atuais | `_fonte: provisorio`; conteúdo escolhido pela equipe |
| `torch` | Todos os campos atuais | `_fonte: provisorio`; conteúdo escolhido pela equipe |

A classificação acima apenas reproduz os metadados atuais. Registros com
`_fonte: enunciado` e suas notas permanecem intactos, inclusive quando a nota distingue parte dos
campos como provisória. Esta entrega não muda balanceamento nem interpreta essa distinção
como autorização para editar o JSON.

### 4.3 Referências, modificadores e falhas — proposta A1

- `characters.json.armaInicial` e as chaves de `upgrades.json.upgrades` referenciam chaves de
  `weapons.json.armas`. Em `evolutions.json`, `requer.arma.id` e `resultado.substituiArma`
  referenciam a arma base; `requer.passivo.id` referencia `passives.json.itens`.
- O ID da arma evoluída é o `id` da entrada em `evolucoes`; seu `resultado` não precisa estar
  duplicado em `weapons.json`. A8 fará o mapeamento, sem implementar elegibilidade da B.
- `mira`, `formato` e o comportamento de inimigos são chaves de registros de estratégias,
  não nomes de classes a instanciar diretamente a partir de JSON.
- O modificador existente é `{ "atributo": "cooldown", "tipo": "PERCENT", "valor": -0.15 }`.
  `origem` não está nos JSON atuais: B/loader a fornece ao criar `StatModifier`, por exemplo
  `upgrades:stone_sword:3`. Formato canônico e regra numérica estão na §7.2.
- JSON malformado, campo obrigatório ausente, tipo/faixa inválida, atributo desconhecido,
  referência inexistente ou combinação de mira/formato desconhecida devem impedir a
  inicialização com erro que indique arquivo, ID e campo. Não assumir defaults silenciosos.
  Exemplo de diagnóstico: `weapons.json: bow.cooldownSegundos deve ser maior que zero`.
- Campos desconhecidos de jogo devem ser apontados como erro de configuração; metadados
  `_...` permanecem separados. A validação cruzada depende do carregamento dos registros
  de conteúdo e estratégias de B/C; A não implementa esses registros na A1.

As regras para outros arquivos só são especificadas aqui nos pontos de integração com A.
Seus esquemas completos e a classe concreta de erro de carga continuam pendentes da equipe.

---

## 5. Repositório de pontuação e tabela `scores`

*A definir na reunião.* O esquema inicial está em
`database/migrations/001_create_scores.sql`.

- [ ] `ScoreRepositoryInterface` — salvar partida, listar ranking
- [ ] Formato do objeto de partida que entra e sai do repositório
- [ ] Critério de desempate refletido na consulta: pontuação desc, tempo asc, data asc

**Proposto por:** `______` · **Data:** `______`

---

## 6. GameState e StateStack

A máquina de estados das telas. O `LevelUpState` empilha sobre o `PlayState`, que continua
existindo, congelado, e volta intacto ao desempilhar.

*A definir na reunião.*

- [ ] `GameState` — `enter`, `update`, `render`, `exit`
- [ ] `StateStack` — empilhar, desempilhar, substituir
- [ ] Quem decide a transição: o estado ou quem o contém

**Proposto por:** `______` · **Data:** `______`

---

## 7. TargetingStrategy e StatModifier

Como uma arma escolhe alvo, e como upgrades, itens passivos e passivas de personagem alteram
números.

### 7.1 Mira — proposta A1, confirmar com B/C

```php
// Jogo\Domain\Weapon\Targeting\TargetingStrategy
interface TargetingStrategy
{
    public function select(TargetingContext $context, WorldView $world): ?TargetingResult;
}

// Mesmo namespace; ambas as estruturas são imutáveis.
final readonly class TargetingContext
{
    public function __construct(
        public Vector2 $origin,
        public Vector2 $facing,
        public float $range,
    ) {}
}

final readonly class TargetingResult
{
    public function __construct(
        public Vector2 $direction,
        public Vector2 $position,
        public ?string $entityId,
    ) {}
}
```

`WorldView` significa `Jogo\Domain\Ai\WorldView` (§8). A fornece contexto com posição do
jogador, olhar unitário e alcance efetivo finito `> 0`, já resolvido a partir de `Stats`.
A seleção não recebe a arma mutável, não causa dano, não inicia cooldown e não modifica o
mundo. Cada estratégia interpreta o resultado conforme seu formato de execução:

| Mira existente | Resultado proposto |
| --- | --- |
| Direção do olhar | Direção igual a `facing`; posição = origem + direção × alcance; `entityId = null` |
| Inimigo mais próximo | Inimigo vivo em alcance; direção normalizada até ele, posição e ID do alvo |
| Inimigo aleatório | Mesmos dados de um inimigo vivo sorteado entre os candidatos em alcance |
| Área ao redor | Direção zero, posição igual à origem e `entityId = null`; não exige inimigo individual |

Somente candidatos `kind = enemy` e `isAlive = true` da §8 são alvos de seleção individual.
Ausência de candidato retorna `null` para as miras de inimigo; olhar e área ainda podem
retornar geometria sem alvo. `null` significa ausência de seleção, não ataque com dano zero.
No alvo sobreposto à origem, propõe-se usar o olhar como direção para evitar direção zero
em projétil. A/B/C devem confirmar essa política e o consumo de cooldown quando não há alvo.

Desempate de distância proposto: ID em ordem lexicográfica. Para TNT, ordenar candidatos
por ID e usar gerador semeado injetado na estratégia; não usar sorteio global oculto. O plano
já prevê RNG semeado; a semente e a API concreta ainda precisam de acordo. A posição devolvida
é uma cópia no instante da seleção; perseguir o alvo depois disso não está implícito no contrato.
Seleção e execução das quatro armas só serão implementadas na A7.

### 7.2 Modificadores — proposta A1, confirmar com B

```php
// Jogo\Domain\Value\ModifierType
enum ModifierType: string
{
    case FLAT = 'FLAT';
    case PERCENT = 'PERCENT';
}

// Jogo\Domain\Value\StatModifier — dados imutáveis
final readonly class StatModifier
{
    public function __construct(
        public string $attribute,
        public ModifierType $type,
        public float $value,
        public string $source,
    ) {}
}
```

`attribute` é nome canônico da tabela; `value` é finito; `source` é identificação não vazia
da concessão, definida pela B, para rastrear/remover o efeito. `source` não é `_fonte`: o
primeiro identifica o efeito em execução; o segundo documenta a procedência do requisito.
O loader traduz os nomes portugueses do JSON para os campos ingleses do objeto, sem renomear
as chaves dos arquivos. Para níveis acumulados, B identifica cada concessão sem duplicá-la.

| Nome canônico em `Stats` / `attribute` | Campo base de configuração | Unidade / domínio válido final |
| --- | --- | --- |
| `dano` | `weapons.dano` | HP, `>= 0` |
| `cooldown` | `weapons.cooldownSegundos` | s, `> 0` |
| `alcance` | `weapons.alcance` | px, `> 0` |
| `projeteis` | `weapons.projeteis` | Contagem inteira, `>= 1`, quando aplicável |
| `velocidadeProjetil` | `weapons.velocidadeProjetil` | px/s, `> 0`, quando aplicável |
| `anguloGraus` | `weapons.anguloGraus` | Graus, `0 < valor <= 360`, quando aplicável |
| `raioExplosao` | `weapons.raioExplosao` | px, `> 0`, quando aplicável |
| `atrasoDetonacaoSegundos` | `weapons.atrasoDetonacaoSegundos` | s, `>= 0`, quando aplicável |
| `velocidadeJogador` | Ainda sem campo base em `characters.json` | px/s, `> 0`; base pendente A/B |
| `vidaMaxima` | `characters.vida` | HP, `> 0`; execução e sincronização pendentes B/A |
| `raioColeta` | Ainda sem campo base | px, `>= 0`; base e uso pertencem à B |

Os prefixos `weapons` e `characters` acima indicam o arquivo, não um objeto novo no JSON.
A tabela cobre os modificadores atuais de armas, personagens e passivos; não define o esquema
interno de atributos de inimigos da C (como seu campo `velocidade`).

Regra já prevista no plano, aqui explicitada:

```text
valorFinal = (valorBase + soma(valores FLAT)) × produto(1 + valor PERCENT)
```

Sem `FLAT`, soma zero; sem `PERCENT`, produto um. Cada percentual é um fator, não uma soma
de percentuais. Exemplo didático: base `10`, `FLAT +2`, `PERCENT +0.25` e `PERCENT -0.10`
resultam em `12 × 1.25 × 0.90 = 13.5`. Esse exemplo não é valor de balanceamento.

Resolver sempre a partir da base e de todos os efeitos vigentes, em ordem estável de origem
para reprodutibilidade numérica. Não alterar a base nem arredondar HP ou segundos. O valor
final precisa respeitar a faixa do atributo; a proposta é rejeitar um resultado inválido,
sem inventar limite mínimo de cooldown ou aplicar clamp silencioso. Percentuais que
produzam contagem fracionária de projéteis dependem de política de arredondamento ainda não
acordada; os dados atuais usam `FLAT` inteiro nesse atributo.

Um efeito global só alcança objetos que possuam o atributo: `raioExplosao` não cria explosão
em espada, arco ou tocha. Um upgrade específico que referencia atributo inexistente deve ser
apontado na validação. B decide elegibilidade, destino e duração dos efeitos; A aplica apenas
os efeitos de combate recebidos. A1 não implementa aplicação, inventário ou evolução.

---

## 8. BehaviorStrategy e WorldView

O comportamento dos inimigos. A `WorldView` é **somente leitura**: a IA lê o mundo e devolve
uma direção, nunca altera nada.

### Visão mínima de leitura — proposta A1, confirmar com C

```php
// Jogo\Domain\Ai\WorldView
interface WorldView
{
    public function playerPosition(): Vector2;
    /** @return list<EntityView> */
    public function neighbors(Vector2 $center, float $radius): array;
}

// Jogo\Domain\Ai\EntityView — cópia de leitura, sem métodos de mutação
final readonly class EntityView
{
    public function __construct(
        public string $id,
        public Vector2 $position,
        public float $collisionRadius,
        public string $kind,
        public ?bool $isAlive,
    ) {}
}
```

`playerPosition()` devolve a posição do jogador da partida em curso. Usar a visão depois de
encerrar/remover o jogador é inválido; o ciclo de vida deve ser controlado por Application.
`neighbors()` aceita centro e raio finito `>= 0`, em pixels, e devolve entidades **ativas**
cujos centros estão a distância `<= radius`, incluindo a fronteira. Raio zero pode devolver
entidades no mesmo ponto. Resultado vazio é `[]`; sem duplicação de IDs e em ordem estável
por ID. A consulta pode incluir quem a solicitou: o consumidor exclui seu próprio ID.

`kind` distingue `player`, `enemy`, `projectile`, `chest`, `pickup` e `breakable`, conforme as
categorias do plano. `isAlive` é `true`/`false` para quem possui vida e `null` para quem não
possui. Não expor `Entity`, `Health`, setters, callbacks de mutação ou referências a coleções
internas. `EntityView` e seus vetores são imutáveis, de modo que C não altera o mundo por meio
da consulta; entidades com vida zero não são alvos válidos mesmo antes da remoção.

A fornece a visão e o índice espacial futuramente; C fornece identidade, posição, raio,
categoria e estado de vida de seu conteúdo. A geração de cópias deve refletir o estado no
momento da consulta; não conservar visões antigas entre atualizações para executar ataques.
A grade é detalhe da implementação: a consulta por raio não pode ficar limitada a nove
células se o raio alcançar mais células. Isso será verificado na A4, sem implementá-la agora.

### Uso por comportamento — somente assinatura de integração proposta

```php
// Jogo\Domain\Ai\Behavior\BehaviorStrategy — implementação pertence à C
interface BehaviorStrategy
{
    public function direction(EntityView $enemy, WorldView $world, float $deltaSeconds): Vector2;
}
```

`EntityView` vem de `Jogo\Domain\Ai`. C recebe segundos finitos `>= 0` e devolve direção
normalizada ou zero. A aplica a velocidade e o deslocamento; C define parâmetros e estado
interno do comportamento, sem mover a entidade pelo contrato. Não ampliar `WorldView` com
spawn, dano ou drops para acomodar ações de boss: o canal dessas ações é uma pendência A/C.
A1 apenas especifica a fronteira de leitura; não implementa IA ou conteúdo da C.

---

## 9. Responsabilidades, dependências e ratificação

Todas as atribuições novas abaixo são propostas de responsabilidade, não confirmação de
integrantes ou aprovação de contratos. Nenhuma frente é representada por Codex na reunião.

| Contrato | Quem fornece / mantém, proposto | Quem precisa revisar | Dependência de implementação futura |
| --- | --- | --- | --- |
| Objetos base e capacidades | A; B/C compõem entidades | B, C, D | Capacidades só entram se aprovadas; preservar APIs existentes |
| `InputInterface` / `InputState` | A especifica dados; D implementa entrada; Application conecta | A, D | A2/A3; controle inicial e olhar |
| `Stats` / `StatModifier` | B fornece base e efeitos; A resolve efeitos de combate | A, B | A6/A8; bases faltantes e sincronização |
| `TargetingStrategy` | A | B, C | A7; alcance, ausência de alvo e RNG |
| Eventos / barramento | A propõe porta e barramento; cada frente mantém seus assinantes | A, B, C, D | A5 e integração; payloads fora de A1 continuam pendentes |
| `WorldView` / `EntityView` | A fornece consultas; C fornece dados e estratégias | A, C | A4/A7; ciclo de vida e ações de conteúdo |
| `weapons.json` / mapeamento | A especifica leitura de combate; B usa referências de upgrades | A, B, C, D | A6; validação e registros de conteúdo |

### Decisões a confirmar com a equipe

| Ponto | Frentes | Estado / proposta para discussão |
| --- | --- | --- |
| Assinaturas, namespaces, unidades e responsáveis desta revisão | Todas | Revisar antes de congelar; registrar data e participantes reais |
| Aprovação de `Movable`, `Collidable`, `Damageable` | A/B/C | Opcionais na A1; nenhuma interface adicionada ao código |
| Controle inicial, direção inicial e política de olhar | A/D | Teclado ou mouse permitido; não escolher hardware aqui |
| Bases de velocidade do jogador, raio de coleta e limites de mapa | A/B/C | Dados ausentes; não inventar números |
| Efeito da pausa em cooldowns e tempo de sobrevivência | A/B/D | Sugestão: congelar tempo de simulação; RF14 exige interromper a ação, mas não define essa política de relógio |
| Upgrade de duração de cooldown | A/B | Duração atual imutável; decidir como substituir preservando/recalculando tempo restante na A8 |
| Upgrade de vida máxima | A/B | Máximo atual imutável; B deve acordar como trocar `Health` e preservar/ajustar vida atual |
| Contato, invulnerabilidade, travessia de projéteis e pool cheio | A/C | Regras de execução pendentes; não fixadas pela A1 |
| Sem alvo, alvo sobreposto, empate de mira e semente | A/B/C | Revisar propostas da §7.1, inclusive se o cooldown é consumido sem seleção |
| Geometria de alcance e raio de colisão | A/C | Seleção por centro proposta; política de impacto e tangência ainda a confirmar |
| Arredondamento de projéteis e resultados inválidos de modificadores | A/B | Política explícita antes de aceitar dados que produzam frações ou valores fora da faixa |
| Ciclo de vida do barramento e ponto seguro para ações da C | Todas | Barramento por partida proposto; combinar aplicação de consequências sem alterar iteração de combate |
| Payloads dos outros eventos, renderer, áudio, telas e persistência | B/C/D | Fora da A1; permanecem pendentes nas respectivas seções |

### Situação do aceite da A1

- [x] Assinaturas existentes conferidas com o código.
- [x] Propostas de interfaces, dados, eventos e esquema de armas documentadas.
- [x] Unidades, responsabilidades, dependências e dúvidas identificadas.
- [ ] Reunião com B, C e D realizada e participantes registrados.
- [ ] Assinaturas e políticas ratificadas por humanos; data de congelamento registrada.

A preparação documental está pronta para revisão. O aceite que depende da reunião continua
pendente; não começar A2 nesta execução nem considerar esta revisão uma aprovação da equipe.

---

## Histórico de mudanças

| Data | Contrato | O que mudou | Quem aprovou |
| --- | --- | --- | --- |
| 09/10/2026 | A1 — §§1–4, 7–9 | APIs existentes registradas; contratos e regras de integração propostos, sem implementar sistemas ou mudar JSON | Pendente de confirmação humana; revisão preparada por Codex |
