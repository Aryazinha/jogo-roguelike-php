# D9 - Feedback visual e sonoro

A D9 implementa feedback de eventos relevantes do jogo
sem modificar regras de dominio.

## Eventos cobertos

1. player_damaged
2. enemy_hit
3. enemy_killed
4. level_up
5. chest_opened
6. player_died
7. item_picked

AudioEventSubscriber usa RaylibAudio da D4.

VisualFeedbackSubscriber e separado do audio e recebe
os eventos tipados por meio do EventBus.

Ambos podem ser registrados por FeedbackSubscribers.
Repetir o registro no mesmo barramento nao duplica
as notificacoes.

## Abertura de bau

ChestOpenedNotice e um aviso provisório de apresentacao.

O dominio ainda nao possui o evento ChestOpened
definitivo nesta base.

Quando a Frente C publicar esse evento, o adaptador
deverá ser atualizado para escuta-lo diretamente.

## Efeitos visuais

VisualFeedbackPool gerencia slots reutilizaveis.

Cada evento cria:
- Um flash colorido
- Quatro particulas em movimento
- Um texto flutuante ou numero de dano

A capacidade padrao e de 96 efeitos simultaneos.

Quando cheia, a estrutura reutiliza slots em vez
de crescer indefinidamente.

Os efeitos expiram pelo relogio visual monotônico.
Nao aumentam o tempo logico do jogo.

## Integracao

PlayFrameFactory recebe opcionalmente o pool de efeitos.

FrameSnapshot transporta os elementos visuais e
textos flutuantes ate o RaylibRenderer.

O renderer usa Camera.worldToScreen para desenhar
os textos na posicao correta da cena.

O overlay da D8 tambem preserva esses textos.

Apos a composicao final da partida, a Application
deve registrar os assinantes no EventBus da partida.

Para LevelUp e ItemPicked, a Application fornece
a posicao atual do jogador ao assinante visual.

## Testes

composer check

php tools/smoke_raylib_feedback.php

Teclas na demonstracao:
1 - Personagem recebe dano
2 - Inimigo atingido
3 - Inimigo derrotado
4 - Level up
5 - Bau aberto
6 - Morte do jogador
7 - Item coletado

Os sons sao WAV temporarios gerados durante o teste.
Os recursos definitivos entram na D11 com creditos
e licencas em assets/CREDITOS.md.

A demonstracao usa eventos ficticios, nao aplica
dano, XP ou recompensas reais.