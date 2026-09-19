# Instruções para agentes de IA neste repositório

Jogo roguelike em PHP, trabalho em grupo da disciplina de Paradigmas de Programação, com 4
integrantes divididos em 4 frentes. Quem estiver usando você vai dizer qual é a sua frente e
qual é a tarefa; o resto está aqui e vale em toda sessão, sem precisar de lembrete.

## Leia antes de escrever código

1. `docs/enunciado/` — o PDF do professor. **É a fonte da verdade dos requisitos.**
2. `docs/PLANO.md` — escopo fechado, arquitetura, padrões de projeto e pendências.
3. `docs/CONTRATOS.md` — as interfaces compartilhadas entre as frentes.
4. `docs/GIT.md` — o fluxo de branches e Pull Requests do time.
5. `config/*.json` — os valores de balanceamento.

Ignore `docs/historico/`: está superado.

## Regras do projeto

- **Só PHP 8.3+.** A equipe não escreve nenhuma linha de outra linguagem. Nada de JavaScript.
  HTML e CSS foram liberados só **fora** do jogo (protótipo de telas, ferramentas de apoio). A
  interface do jogo é uma janela Raylib via FFI.
- **Os requisitos do enunciado são imutáveis.** Não reescreva, não reinterprete, não
  reclassifique um requisito obrigatório como opcional e não altere valores numéricos vindos do
  enunciado. Se algo parecer errado no enunciado, avise em vez de corrigir.
- **Não invente requisitos.** O que o enunciado não pede é marcado como sugestão.
- **Nada em `src/Domain/` importa FFI, chama Raylib ou desenha na tela.** A apresentação passa
  por `RendererInterface`, `AudioInterface` e `InputInterface`. A única classe que toca em FFI
  é `src/Infrastructure/Ffi/RaylibBinding.php`.
- **Em `config/*.json`, valores com `"_fonte": "enunciado"` não mudam.** Os com
  `"_fonte": "provisorio"` podem ser ajustados para balanceamento.
- **Testes unitários são obrigatórios** para dano, cooldown, XP, evolução de nível, geração de
  inimigos e pontuação. O teste entra no mesmo commit da regra que ele cobre.
- PSR-12, autoload PSR-4 com namespace `Jogo\` em `src/`. Classes e métodos em inglês;
  comentários e documentação em português.

## Ao receber uma tarefa de implementação nova

Antes de escrever código ou criar branch, responda em no máximo 10 linhas: (1) o que você
entendeu que precisa ser construído, (2) quais requisitos do enunciado isso cobre e (3) o que
ficou ambíguo. Espere a confirmação.

## Versionamento

### 1. Branch por tarefa, a partir do `main` atualizado

Antes da primeira alteração:

    git switch main
    git pull --ff-only origin main
    git switch -c frente-<letra>/<assunto-curto>

Nome minúsculo, sem acento, com hifens — ex.: `frente-b/formula-de-nivel`. Nunca commite no
`main`; se perceber que está nele, crie a branch antes de continuar.

### 2. Commits em blocos que se sustentam sozinhos

Um commit por unidade coerente: uma classe com seu teste, uma correção, uma decisão registrada
na documentação. Não misture alterações não relacionadas e não deixe trabalho pronto sem
commitar — o que não está commitado se perde se a sessão cair. Commite também antes de
qualquer operação arriscada.

### 3. Escolha o que entra, arquivo por arquivo

Confira com `git status --short` e adicione nomeando cada arquivo. Nunca use `git add .` nem
`git add -A`. Nunca commite `vendor/`, `lib/`, `*.sqlite` nem saídas de execução — se
aparecerem como não rastreados, avise em vez de commitar.

### 4. Mensagem com o que mudou e por quê

    git commit -F - <<'EOF'
    feat: adiciona mira por inimigo mais proximo (RF07)

    - o que mudou, uma linha por ponto
    - por que mudou: o problema que resolve ou a decisao que registra
    - o que ficou de fora e por que, se for o caso
    EOF

Título: tipo (`feat`, `fix`, `test`, `docs`, `refactor`, `chore`), dois pontos, imperativo,
até 72 caracteres, com o requisito entre parênteses quando houver. O corpo é para quem ler o
histórico meses depois, sem o contexto da conversa.

### 5. Envie a branch, e pare aí

    git push -u origin frente-<letra>/<assunto-curto>

Relate o que foi commitado e **pergunte** se pode abrir o Pull Request.

### 6. Pull Request, só com autorização explícita

    gh pr create --base main --title "<titulo>" --body "<descricao>"

A descrição segue `.github/pull_request_template.md`: o que faz, requisito coberto, como
testar. Pergunte quem vai revisar — tem que ser alguém de **outra** frente. Autorização para
abrir um PR não vale para o seguinte.

### 7. Merge nunca pelo terminal

O merge no `main` é feito no GitHub, depois da aprovação de alguém de outra frente. Você não
faz merge no `main` nem push para o `main` em hipótese alguma. Quando avisarem que o PR foi
mergeado:

    git switch main
    git pull --ff-only origin main
    git branch -d frente-<letra>/<assunto-curto>

Se a branch ficar para trás do `main` durante o trabalho, atualize com `git merge main` dentro
dela — nunca rebase. Em caso de conflito, mostre os trechos antes de resolver.

### 8. Proibições

- `--no-verify` ou desativar hooks. Se um hook falhar, corrija a causa.
- `git push --force` ou reescrever histórico já enviado.
- Comandos interativos (`rebase -i`, `add -i`), que travam a sessão.
- `git reset --hard`, `git checkout --` ou descartar alterações sem autorização explícita.
- Alterar configuração de usuário ou credenciais do Git.

### 9. Relate sempre

Depois de cada commit: hash curto, branch e, em uma linha, o que entrou. Ao fim do trabalho: o
que está commitado, o que está enviado e o que continua sem commitar.
