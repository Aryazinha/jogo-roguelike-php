# D2 - Entrada real

Implementacao de RaylibInput conforme o contrato InputInterface
da Frente A.

## Controles

- W: cima
- A: esquerda
- S: baixo
- D: direita
- Setas: direcao do olhar
- P: solicitar pausa

Teclas opostas se cancelam.

O movimento diagonal sera normalizado pela Frente A.
A direcao do olhar e normalizada pela D2.
Sem setas pressionadas, facing permanece null.

Nao existe comando manual de ataque.

O mapeamento ainda precisa ser alinhado com a Frente A.

## Arquivos

- src/Infrastructure/Input/RaylibInput.php
- tests/Unit/Infrastructure/Input/RaylibInputTest.php
- tools/smoke_raylib_input.php

## Validacao

composer check

php tools/smoke_raylib_input.php

A D2 interpreta entrada, mas nao movimenta diretamente
entidades nem altera regras do jogo.