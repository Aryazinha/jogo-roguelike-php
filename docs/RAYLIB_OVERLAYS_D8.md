# D8 - Level Up e Pausa

Implementa overlays interativos para a partida.

## Pausa

PauseState apresenta o comando CONTINUAR.
A Application pode empilhar esse estado quando receber
a solicitacao de pausa da entrada.

O StateLoopSynchronizer pausa o GameLoop.

A entrada visual deve continuar sendo processada
fora das atualizacoes logicas, uma vez por quadro.

## Level Up

LevelUpState recebe LevelUpScreenContext, contendo
ate tres opcoes validas da Frente B.

Cada opcao apresenta:
- ID
- Nome
- Tipo
- Descricao
- Efeito
- Necessidade de substituir arma

A tela retorna o ID escolhido e o ID opcional do slot.
Nao aplica dano, XP, passivos ou melhorias diretamente.

A callback da Application retorna true quando a
escolha foi aplicada com sucesso.

Se retornar false ou falhar, a tela permanece aberta.

Apos confirmacao valida, o overlay e removido e
a partida pode continuar sem recuperar tempo de pausa.

## Substituicao de arma

Quando uma opcao exige substituicao, a tela solicita
ao jogador qual dos slots fornecidos sera substituido.

ESC na selecao de slots volta a lista de melhorias.

O algoritmo de substituicao pertence a Frente B.
A D apenas coleta os identificadores.

## Sem opcoes

A ausencia de opcoes e exibida explicitamente.
Por padrao, a tela nao pode ser cancelada.

Se a Application permitir cancelamento, deve informar
allowCancel=true. Essa politica depende do fluxo da B.

## Renderizacao

OverlayFramePresenter utiliza PlaySnapshotProviderInterface
e PlayFrameFactory da D7.

O renderer recebe um FrameSnapshot com overlay e desenha
a partida e o painel no mesmo BeginDrawing/EndDrawing.

Isso evita abrir dois quadros Raylib separados.

## Controles

- Setas ou WASD: navegar
- ENTER ou espaco: confirmar
- Mouse: clicar em uma opcao
- ESC: voltar na selecao de slots
- P: abrir e fechar a pausa na demonstracao
- L: abrir level up demonstrativo
- N: abrir caso demonstrativo sem opcoes

O ESC tambem pode ser tratado pela Raylib como
comando de fechar a janela. A politica de fechamento
sera alinhada na integracao final.

## Integracao pendente

Os IDs de upgrades e slots apresentados pela Application
devem ser mapeados aos contratos definitivos da Frente B.

A D8 nao escolhe melhorias elegiveis e nao modifica
as regras do dominio.

## Testes

composer check

php tools/smoke_raylib_overlay.php

O teste grafico usa dados ilustrativos.
A janela encerra pelo X ou apos 90 segundos.