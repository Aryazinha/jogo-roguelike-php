# Git — o fluxo do projeto

**A regra:** o `main` está sempre executável. Ninguém commita direto nele. Cada pessoa
trabalha na sua branch e o trabalho entra por Pull Request, revisado por alguém de outra
frente.

---

## Uma vez só

```bash
git config --global user.name "Seu Nome"
git config --global user.email "seu@email.com"

git clone <url-do-repositorio>
cd projeto_paradigmas
```

Use o mesmo e-mail da sua conta do GitHub, senão os commits não aparecem como seus.

---

## 1. Criar sua branch

Sempre a partir do `main` atualizado:

```bash
git switch main
git pull
git switch -c frente-a/colisao-grade-espacial
```

**Nome da branch:** `frente-<letra>/<assunto>`, minúsculo, sem acento, com hifens.

```
frente-a/colisao-grade-espacial
frente-b/formula-de-nivel
frente-c/boss-em-fases
frente-d/tela-de-level-up
```

Cada tarefa é uma branch nova. Não reaproveite a branch de uma tarefa que já foi mergeada.

---

## 2. Commitar

Veja o que mudou, escolha o que entra, commite:

```bash
git status
git add src/Domain/System/CollisionSystem.php
git commit -m "feat: adiciona grade espacial na deteccao de colisao (RNF06)"
```

**Formato da mensagem:** tipo, dois pontos, o que faz no imperativo, e o requisito entre
parênteses quando houver.

```
feat: adiciona mira por inimigo mais proximo (RF07)
fix: corrige cooldown zerando ao subir de nivel (RF20)
test: cobre a formula de nivel das tres faixas (RF13)
docs: atualiza AMBIENTE.md com a versao do libraylib
```

Commits pequenos e frequentes são melhores que um commit gigante no fim do dia.

---

## 3. Enviar para o GitHub

Primeira vez nessa branch:

```bash
git push -u origin frente-a/colisao-grade-espacial
```

Depois, só:

```bash
git push
```

---

## 4. Abrir o Pull Request

1. Entre no repositório no GitHub. Vai aparecer uma faixa com **"Compare & pull request"** —
   clique nela. Se não aparecer, vá em **Pull requests** → **New pull request** e escolha a
   sua branch.
2. Confira no topo: `base: main` ← `compare: sua-branch`.
3. **Título:** o que a mudança faz, curto.
4. **Descrição:** use o modelo abaixo.
5. Em **Reviewers**, marque **alguém de outra frente**.
6. **Create pull request**.

### Modelo de descrição

A descrição já abre preenchida com este modelo, vindo de `.github/pull_request_template.md`.
Complete os campos e apague os comentários entre `<!-- -->`.

```markdown
## O que faz
Substitui a verificação de todos contra todos por uma grade espacial de células de 64 px.

## Requisito
Cobre RNF06.

## Como testar
1. `composer test`
2. Rodar o jogo com 200 inimigos e conferir que não cai abaixo de 60 FPS.
```

### Antes de marcar o revisor

- [ ] O código roda — você testou
- [ ] `composer fmt` e `composer test` passam
- [ ] A descrição diz qual requisito a mudança cobre

Se alguém pedir mudanças, é só commitar e dar `git push` na mesma branch. O PR se atualiza
sozinho — não abra outro.

---

## 5. Merge

Depois de aprovado, clique em **Merge pull request** no GitHub. Em seguida, na sua máquina:

```bash
git switch main
git pull
git branch -d frente-a/colisao-grade-espacial
```

O merge acontece no GitHub, não no terminal.

---

## Se a sua branch ficar para trás

Quando alguém mergeia algo antes de você e o GitHub avisa que há conflito:

```bash
git switch main
git pull
git switch sua-branch
git merge main
```

Se aparecer conflito, o arquivo fica com marcas `<<<<<<<`, `=======` e `>>>>>>>`. Escolha o
que fica, **apague as três marcas**, e então:

```bash
git add arquivo-resolvido.php
git commit
git push
```
