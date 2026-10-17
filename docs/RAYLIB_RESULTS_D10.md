# D10 - Game Over, Vitoria, Nome e Ranking

Implementa as telas finais de resultado da Frente D.

## Telas

- GameOverState
- VictoryState
- RankingState

As telas apresentam personagem, nivel, inimigos
derrotados, tempo e pontuacao.

A pontuacao chega pronta da Frente B.
A D nao calcula nem altera esses valores.

## Entrada do nome

O binding D1 passa a suportar:
- GetCharPressed
- SetExitKey

A interface suporta digitar, apagar e navegar.

A validacao definitiva de nome pertence a Frente B.
O limite visual de entrada e de 24 caracteres.

## Salvamento

ResultsGatewayInterface define a fronteira de:
- Salvamento do resumo associado a um nome
- Consulta paginada ao ranking

SaveOutcome representa sucesso e mensagem de erro.

A tela so marca o registro como salvo quando
o gateway confirma sucesso.

Repetir ENTER depois de salvar nao duplica
a solicitacao.

## Ranking

O RankingState consulta paginas de seis registros.

A D nao altera a ordem dos registros recebidos.

O gateway definitivo deve fornecer ordenacao,
desempate e persistencia conforme o plano da B.

## Demonstracao independente

tools/demo_results_sqlite.php usa SQLite
temporariamente apenas para testar o fluxo.

O banco demonstrativo e armazenado em:
diretorio temporario do sistema operacional.

Ele permanece disponivel ao fechar e reabrir
a demonstracao enquanto o arquivo existir.

Nao e o repositorio definitivo da Frente B.

Pontuacoes e resultados do smoke test sao ficticios.

## Validacao

composer check

php tools/smoke_raylib_results.php

Na demonstracao:
- Digitar o nome
- Setas esquerda/direita: escolher acao
- ENTER: confirmar
- Mouse: clicar nos botoes
- SALVAR: registrar a pontuacao
- RANKING: consultar e paginar
- NOVA PARTIDA: voltar a selecao de personagem
- MENU: retornar ao menu
- F1: exibir uma derrota demonstrativa
- F2: exibir uma vitoria demonstrativa
- ESC: voltar
- X: fechar a janela

A integracao final com B8/B9/B10 e D12
permanece pendente.