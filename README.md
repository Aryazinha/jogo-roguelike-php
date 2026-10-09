# Jogo Roguelike em PHP

Jogo roguelike de sobrevivência com ataque automático, no molde de *Vampire Survivors*, com
ambientação inspirada em Minecraft. O jogador controla apenas a movimentação; as armas atacam
sozinhas conforme seus cooldowns, enquanto ondas progressivamente mais fortes de inimigos
surgem no mapa.

Trabalho da disciplina de Paradigmas de Programação — 2026.2. Escrito **exclusivamente em
PHP**.

> **Estado atual do repositório: implementação iniciada.** O domínio já contém os objetos
> fundamentais de posição, vida, cooldown e entidade, acompanhados por testes unitários.
> O jogo executável e a integração com o Raylib ainda serão construídos nas próximas etapas.

---

## Leia nesta ordem

Se você acabou de clonar, siga esta sequência — leva cerca de uma hora e você termina sabendo
o que o jogo precisa ter, como o código está organizado e como enviar o seu trabalho.

| # | Arquivo | Por quê |
| --- | --- | --- |
| 1 | [docs/enunciado/](docs/enunciado/) | O enunciado do professor. **É a fonte da verdade dos requisitos** — o resto do repositório apenas o organiza |
| 2 | [docs/PLANO.md](docs/PLANO.md) | Escopo fechado, arquitetura, padrões de projeto e pendências |
| 3 | [docs/AMBIENTE.md](docs/AMBIENTE.md) | Como deixar sua máquina pronta. **Faça antes da primeira reunião** |
| 4 | [docs/CONTRATOS.md](docs/CONTRATOS.md) | As assinaturas compartilhadas, congeladas na semana 1 |
| 5 | [docs/GIT.md](docs/GIT.md) | Como abrir um Pull Request e os comandos de Git do dia a dia |

---

## Requisitos de máquina

| Item | Versão | Como conferir |
| --- | --- | --- |
| PHP | 8.3 ou superior | `php -v` |
| Extensão FFI | habilitada | `php -m` deve listar `FFI` |
| Extensão PDO SQLite | habilitada | `php -m` deve listar `pdo_sqlite` |
| Composer | 2.x | `composer --version` |
| `libraylib` | versão fixada pela equipe | ver [docs/AMBIENTE.md](docs/AMBIENTE.md) |

O passo a passo de instalação e configuração, incluindo o `php.ini`, está em
[docs/AMBIENTE.md](docs/AMBIENTE.md). Não pule: o projeto usa FFI, que vem desabilitado em
boa parte das instalações.

## Como rodar

```bash
git clone <url-do-repositorio>
cd projeto_paradigmas
composer install
# baixe o libraylib e coloque em lib/ — ver docs/AMBIENTE.md
php jogo.php
```

## Comandos úteis

| Comando | O que faz |
| --- | --- |
| `composer test` | Roda a suíte de testes (PHPUnit) |
| `composer stan` | Análise estática (PHPStan) |
| `composer fmt` | Formata o código no padrão PSR-12 (Pint) |
| `composer sim` | Roda uma partida sem abrir janela, para balanceamento |

---

## Estrutura do repositório

```
jogo.php              ponto de entrada (a criar)
composer.json         dependências e autoload PSR-4
CLAUDE.md             instruções lidas automaticamente pelo Claude Code
.editorconfig         indentação e fim de linha, do lado do editor
.gitattributes        fim de linha, do lado do repositório
.github/              modelo de Pull Request
config/               JSON de balanceamento: armas, inimigos, personagens, ondas
assets/               sprites, áudio e fontes + CREDITOS.md
lib/                  binário do libraylib (fora do Git — cada um baixa o seu)
database/migrations/  esquema do banco, versionado em SQL
docs/                 plano, ambiente, contratos, guia de Git e enunciado
  docs/enunciado/     o documento do professor — fonte da verdade
  docs/historico/     versões superadas, guardadas só para consulta
src/                  código do jogo; o domínio fundamental já está implementado
tests/                testes unitários e, futuramente, integração e simulação
```

A primeira entrega da Frente A está explicada em
[docs/FRONTE_A.md](docs/FRONTE_A.md), incluindo exemplos de uso e as invariantes adotadas.
O trabalho restante está organizado em
[docs/PLANO_FRENTE_A.md](docs/PLANO_FRENTE_A.md), com cronograma e critérios de aceite.

### O que não entra no Git, e por quê

- **`vendor/`** — reconstruído por `composer install`.
- **`lib/`** — binário de plataforma, pesado e diferente em cada sistema operacional.
- **`database/*.sqlite`** — arquivo binário; dois commits no mesmo banco geram um conflito de
  merge que não se resolve. Versionamos as migrações em SQL, e cada um recria o banco local.

---

## Como trabalhamos

O passo a passo completo, com os comandos e como abrir um Pull Request, está em
[docs/GIT.md](docs/GIT.md). O resumo é este:

**Branches.** O `main` está sempre executável. Nada é commitado direto nele. Cada tarefa vive
em uma branch nomeada por frente e assunto:

```
frente-a/colisao-grade-espacial
frente-b/formula-de-nivel
frente-c/boss-em-fases
frente-d/tela-de-level-up
```

**Pull requests.** Toda mudança entra por PR, com **revisão de alguém de outra frente**. Não
é burocracia: é o mecanismo pelo qual as quatro pessoas acabam conhecendo o código inteiro,
que é o que a apresentação vai cobrar. O PR indica qual requisito cobre, por exemplo
`cobre RF19`.

**Commits.** Mensagem no imperativo, com prefixo do tipo:

```
feat: adiciona mira por inimigo mais próximo (RF07)
fix: corrige cooldown zerando ao subir de nível (RF20)
docs: atualiza AMBIENTE.md com a versão do libraylib
test: cobre a fórmula de nível das três faixas (RF13)
```

**Estilo de código.** PSR-12, verificado por `composer fmt`. Nomes de classes e métodos em
inglês; comentários e documentação em português.

**Push diário.** Nada de trabalho parado só na sua máquina por mais de um dia.

Nunca usou Git em grupo? Comece por [docs/GIT.md](docs/GIT.md), que explica desde o começo.

### Usando Claude Code

O [CLAUDE.md](CLAUDE.md) é lido automaticamente pelo Claude Code ao abrir o projeto: ele já
conhece as regras do projeto e de versionamento. Na primeira mensagem de cada sessão, basta
dizer quem você é e o que vai fazer:

```
Sou da frente B. Minha tarefa agora: implementar a fórmula de nível do RF13, com testes.
```

Ele responde primeiro com o que entendeu e o que ficou ambíguo, antes de escrever código. Ele
commita e envia a sua branch sozinho, mas **não abre PR nem mexe no `main` sem você pedir** —
e o merge continua sendo no GitHub, com revisão de outra frente.

---

## Equipe

**Quem fica com qual frente ainda será decidido.** As quatro divisões abaixo já estão
definidas; falta combinar os nomes.

| Frente | Responsabilidade | Integrante |
| --- | --- | --- |
| A — Núcleo e combate | Loop, entrada, movimentação, colisão, dano, armas | *a definir* |
| B — Progressão e dados | XP, níveis, upgrades, personagens, pontuação, ranking | *a definir* |
| C — Conteúdo e dificuldade | Inimigos, boss, ondas, baús, eventos | *a definir* |
| D — Apresentação | Renderização, menus, HUD, áudio, feedback | *a definir* |

A frente define responsabilidade, não propriedade: qualquer um pode mexer em qualquer parte,
desde que abra PR e a revisão venha de outra frente.

---

## Assets

Não usamos arquivos originais do Minecraft. Sprites são próprios ou de pacotes de domínio
público. **Todo asset que entra no repositório é registrado em
[assets/CREDITOS.md](assets/CREDITOS.md) no mesmo commit**, com origem e licença.
