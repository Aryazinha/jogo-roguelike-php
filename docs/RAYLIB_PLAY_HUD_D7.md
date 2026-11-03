# D7 - Partida e HUD

A D7 implementa a apresentacao visual da partida
por snapshots imutaveis, usando o renderer da D3
e a maquina de estados da D5.

## Estrutura

- PlayEntityView: dados visuais imutaveis
- PlayWorldSnapshot: jogador e elementos visuais
- PlayHudSnapshot: informacoes do HUD
- WeaponHudSnapshot: arma e cooldown
- BossHudSnapshot: vida do boss
- PlaySnapshot: quadro de apresentacao
- PlaySnapshotProviderInterface: origem dos dados
- PlayFrameFactory: converte snapshots em RenderItems
- PlayFramePresenter: coordena a renderizacao
- WorldViewPlayAdapter: leitura da WorldView da Frente A

## Elementos visuais

O renderer suporta:

- Mapa e cenario
- Jogador
- Inimigos comuns
- Inimigos especiais
- Boss
- Projeteis
- TNT
- Espada
- Pulso
- Baus
- Pickups
- Objetos quebraveis
- Efeitos

As cores e formas atuais sao representacoes temporarias.
Sprites definitivos entram na D11.

## HUD

- Vida atual e maxima
- Barra de vida
- XP atual e XP para o proximo nivel
- Barra de experiencia
- Nivel do jogador
- Tempo de sobrevivencia
- Armas equipadas
- Cooldown das armas
- Passivos
- Barra de vida do boss
- Identificador de onda/evento, quando fornecido

Os valores devem vir da Application/Frente B.
A camada visual nao calcula experiencia, dano
ou pontuacao.

## Integracao com Frente A

WorldViewPlayAdapter converte os EntityView readonly
da Frente A em snapshots visuais basicos.

A WorldView distingue categorias de entidades,
mas nao identifica tipos especiais ou bosses.
Esses metadados devem ser fornecidos pela C
na composicao real da partida.

A interpolacao de movimento depende de receber
posicoes anteriores e atuais no snapshot.

Os sprites definitivos dependem da D11.

## Teste grafico

php tools/smoke_raylib_play.php

Abre uma cena de demonstracao por ate 30 segundos.
Os valores e movimentos dessa cena sao ficticios,
usados exclusivamente para verificar a apresentacao.

## Testes automatizados

composer check

Validam:
- Campos do HUD
- Formatacao do tempo
- Barras de vida e XP
- Vida do boss
- Conversao de tipos e camadas
- Imutabilidade dos snapshots
- Integracao com PlayState
- Leitura de WorldView
- Rejeicao de valores invalidos

## Pendencias

A composicao completa com B/C e os dados reais
dos ataques, inimigos, boss, armas e passivos
devera ocorrer na integracao do jogo.

D7 nao implementa regras de gameplay.