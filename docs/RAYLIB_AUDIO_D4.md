# D4 - Audio

A Frente D fornece musica e efeitos sonoros sem colocar
Raylib ou FFI nas classes de dominio.

## Componentes

- AudioInterface: contrato de apresentacao sonora
- AudioDeviceInterface: contrato para testar sem hardware
- RaylibAudioDevice: adaptador de RaylibBinding
- RaylibAudio: efeitos, musicas e cache
- NullAudio: implementacao silenciosa
- AudioEventSubscriber: integracao com EventBus

## Efeitos

As chaves previstas sao:

- player_damaged
- enemy_hit
- enemy_killed
- level_up
- player_died
- item_picked
- chest_opened

Os seis primeiros possuem eventos de dominio concretos
confirmados na base utilizada.

O evento ChestOpened ainda depende do contrato das
outras frentes. A chave chest_opened ja pode ser usada
pela Application quando sua origem estiver definida.

## Musicas

RaylibAudio aceita os nomes logicos menu e game.
Os caminhos reais sao injetados na construcao.

Arquivos WAV e OGG devem ser fornecidos na integracao
com os assets definitivos.

A musica ativa e atualizada uma vez por quadro.
Mudar a faixa libera a anterior.
Encerrar o audio descarrega os recursos utilizados.

## Falhas

A ausencia de dispositivo ou arquivo nao interrompe
a partida. Erros ficam disponiveis por failures().

O registro de listeners e feito uma vez por EventBus.
Os listeners tratam falhas de apresentacao sem bloquear
a publicacao de eventos.

## Assets

O smoke test gera um WAV temporario e o remove ao final.
Esse arquivo nao e incluido no repositorio.

Assets definitivos devem ser registrados em
assets/CREDITOS.md com origem, autor e licenca.

## Pendencias

- Receber os assets finais e suas licencas.
- Integrar o evento de abertura de bau.
- Decidir volume e mute com a equipe, se desejados.
- Ligar musica e efeitos ao fluxo real do jogo na D12.

## Validacao

composer check

php tools/smoke_raylib_audio.php

Na janela:

1 = efeito de dano
2 = efeito de level up
M = musica do menu
G = musica da partida
S = parar musica

A janela fecha pelo X ou apos 30 segundos.