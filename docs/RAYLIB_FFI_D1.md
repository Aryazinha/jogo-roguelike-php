# D1 — Integracao Raylib com PHP

## Objetivo

Isolar a biblioteca Raylib dentro de RaylibBinding,
permitindo que o restante do jogo utilize uma interface
PHP para acessar os recursos graficos e sonoros.

## Requisitos

- PHP 8.3 ou superior
- Extensao FFI habilitada
- Composer instalado
- Biblioteca Raylib 64 bits
- raylib.dll dentro da pasta lib/

Compatibilidade prevista: Raylib 5.5.
A versao efetiva da DLL deve ser confirmada com a equipe.

## Arquivos implementados

- src/Infrastructure/Ffi/RaylibBinding.php
- tests/Unit/Infrastructure/Ffi/RaylibBindingTest.php
- tests/Unit/Infrastructure/Ffi/RaylibResourceGuardTest.php
- tools/smoke_raylib.php
- tools/smoke_raylib_resources.php

## Funcionalidades

- Criacao e fechamento da janela
- Inicio e fim de quadros
- Controle de FPS e tempo
- Leitura de teclado e mouse
- Desenho de textos e formas
- Carregamento e desenho de texturas
- Carregamento e desenho de fontes
- Inicializacao do dispositivo de audio
- Carregamento e reproducao de sons e musicas
- Descarregamento dos recursos registrados

## Testes

Executar todas as verificacoes:

    composer check

Testar a janela:

    php tools/smoke_raylib.php

Testar recursos graficos e sonoros:

    php tools/smoke_raylib_resources.php

## Arquitetura

Somente RaylibBinding acessa diretamente a extensao FFI.

Classes de dominio nao devem importar FFI ou Raylib.

A camada grafica nao modifica regras de jogo.

## Observacoes

Os testes graficos foram realizados no Windows,
utilizando a biblioteca Raylib instalada localmente.

A DLL nao deve ser enviada ao GitHub.

Os recursos temporarios utilizados no smoke test
sao gerados durante a execucao.

A liberacao dos recursos ocorre no fechamento da janela,
mas a ausencia de vazamentos de memoria ainda precisa
ser validada por medicao.

O desempenho do jogo completo sera validado na D12.
