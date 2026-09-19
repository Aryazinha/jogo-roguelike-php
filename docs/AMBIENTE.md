# Preparando a máquina

Faça isto **antes da primeira reunião**. O projeto usa FFI, que vem desabilitado na maioria
das instalações de PHP, e depende de um binário externo que não está no repositório. Deixar
isso para a hora da reunião custa a reunião inteira.

Ao terminar, rode a [validação final](#5-validação-final) e avise no grupo se algum passo
falhar.

---

## 1. PHP 8.3 ou superior

```bash
php -v
```

Precisa mostrar 8.3 ou mais. O FFI existe desde o 8.1, mas a equipe fixou 8.3 para que todos
resolvam as mesmas versões de dependência.

**Windows.** Baixe em [windows.php.net/download](https://windows.php.net/download/) a versão
**x64 Thread Safe**, extraia em `C:\php` e acrescente essa pasta ao `PATH`.

**Linux.** Pelo gerenciador de pacotes da distribuição, ou pelo PPA `ondrej/php` no Ubuntu.

**macOS.** `brew install php@8.3`.

## 2. Extensões

```bash
php -m
```

A lista precisa conter **`FFI`**, **`pdo_sqlite`**, `json` e `mbstring`.

Se faltar alguma, ela é habilitada no `php.ini`. Descubra qual arquivo o PHP está usando:

```bash
php --ini
```

Use o caminho mostrado em `Loaded Configuration File`. No Windows, pode ser que só exista
`php.ini-development` — copie para `php.ini` na mesma pasta.

Dentro do `php.ini`, remova o `;` do começo destas linhas:

```ini
extension=ffi
extension=pdo_sqlite
extension=mbstring
```

No Windows, confira também se `extension_dir` aponta para a pasta `ext` da sua instalação:

```ini
extension_dir = "C:\php\ext"
```

## 3. Habilitando o FFI

Ter a extensão carregada não basta: o FFI tem um controle próprio de permissão.

```ini
ffi.enable = true
```

> **Por que forçar `true`.** O valor padrão é `preload`, que libera o FFI apenas em arquivos
> pré-carregados — e, a depender da versão e da build, também no CLI. Em vez de depender
> desse detalhe, a equipe fixa `true` no CLI e elimina a dúvida. Se a sua instalação já
> funcionar sem essa linha, ótimo; deixe-a assim mesmo, para que as quatro máquinas fiquem
> iguais.

Confirme:

```bash
php -r "var_dump(extension_loaded('FFI'), ini_get('ffi.enable'));"
```

## 4. Desempenho: OPcache com JIT

O RNF06 pede taxa de atualização estável com muitos inimigos. O JIT acelera justamente laços
aritméticos, que é o perfil do laço de colisão. No mesmo `php.ini`:

```ini
zend_extension = opcache
opcache.enable = 1
opcache.enable_cli = 1
opcache.jit_buffer_size = 64M
opcache.jit = tracing
```

O `opcache.enable_cli` é o que importa aqui: o jogo roda pelo CLI, e sem essa linha o OPcache
fica desligado por padrão nesse contexto.

Confirme:

```bash
php -r "var_dump(opcache_get_status(false)['jit']['enabled'] ?? 'jit indisponivel');"
```

> **A combinar no spike.** JIT e FFI juntos precisam ser testados, não presumidos. Se
> aparecer instabilidade, o primeiro teste é desligar o JIT (`opcache.jit = disable`) e ver
> se o problema some. Registre o resultado na seção 6.

## 5. O binário do Raylib

O `libraylib` **não está no repositório** — é binário de plataforma, pesado e diferente em
cada sistema. Cada um baixa o seu.

1. Baixe em [github.com/raysan5/raylib/releases](https://github.com/raysan5/raylib/releases)
   o pacote pré-compilado da sua plataforma, 64 bits.
2. Extraia e copie o arquivo da biblioteca para a pasta `lib/` do projeto:

| Sistema | Arquivo |
| --- | --- |
| Windows | `raylib.dll` |
| Linux | `libraylib.so` |
| macOS | `libraylib.dylib` |

3. Guarde também o `raylib.h` da **mesma versão**: as assinaturas do FFI são conferidas
   contra ele, e usar um cabeçalho de versão diferente da biblioteca é causa clássica de
   falha de segmentação.

> **Versão fixada pela equipe:** `_______` ← preencher no spike da semana 1, e todos usam
> exatamente esta.

## 6. Validação final

Os seis passos abaixo são o spike da semana 1. Marque o que passou e leve o resultado para a
reunião — **quem trava aqui trava o projeto inteiro**, então avise cedo.

- [ ] `php -v` mostra 8.3 ou superior
- [ ] `php -m` lista `FFI` e `pdo_sqlite`
- [ ] Uma janela do Raylib abre e fecha sem erro
- [ ] Uma textura PNG é carregada e desenhada
- [ ] Uma função que recebe `Vector2` e `Color` **por valor** funciona (ex.: `DrawTextEx`)
- [ ] Um `.wav` toca e o teclado é lido
- [ ] A janela roda 60 segundos a 60 FPS sem vazamento de memória

O quinto item é o mais importante: o Raylib passa `struct` por valor em quase toda a API de
desenho, e é o ponto onde bindings por FFI costumam quebrar. É a razão de o spike existir.

---

## Problemas comuns

Esta seção cresce conforme a equipe encontra coisas. Ao resolver um problema, registre aqui.

**`Class "FFI" not found`**
A extensão não está carregada. Reveja a seção 2 e confira se editou o `php.ini` certo, com
`php --ini`.

**`FFI API is restricted`**
A extensão está carregada, mas bloqueada. Falta `ffi.enable = true` — seção 3.

**A biblioteca não é encontrada (`cannot load library`)**
O caminho passado ao FFI é relativo ao diretório de execução, não ao arquivo PHP. Rode o jogo
a partir da raiz do projeto. No Windows, confirme que baixou a versão 64 bits, compatível com
o seu PHP x64.

**Falha de segmentação ao desenhar**
Quase sempre assinatura de FFI divergente do `raylib.h`. Confira o tipo de retorno e a ordem
dos parâmetros contra o cabeçalho **da mesma versão** da biblioteca.

**`php` não é reconhecido como comando**
A pasta do PHP não está no `PATH`. No Windows, acrescente `C:\php` nas variáveis de ambiente
e abra um terminal novo.
