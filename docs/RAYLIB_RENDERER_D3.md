# D3 - Renderer, recursos e camera

Implementacao da apresentacao grafica da Frente D.

## Componentes

- RendererInterface: contrato de renderizacao
- RaylibRenderer: implementacao grafica
- NullRenderer: implementacao sem janela
- Camera: coordenadas e limites do mapa
- FrameSnapshot: estado visual imutavel
- RenderItem: elemento visual imutavel
- RaylibResourceCache: cache de texturas e fontes

## Funcionamento

O renderer recebe apenas snapshots visuais.

O calculo da camera considera a posicao do jogador
e os limites do mapa. Mapas menores que a janela
sao centralizados.

A interpolacao usa o alpha fornecido pelo GameLoop.

Ordem das camadas:

1. map
2. scenery
3. pickups
4. entities
5. attacks
6. effects
7. HUD, desenhado por ultimo

Os elementos podem ser circulos, retangulos ou texturas.
Texturas sao desenhadas no tamanho original da imagem.

A camera usa coordenadas do mundo em pixels.
A conversao entre mundo e tela e explicita.

O cache evita carregar repetidamente a mesma textura
ou fonte. O descarregamento e explicito.

## Integracao futura

Os snapshots reais deverao ser montados por adaptadores
da Application, usando dados das frentes A, B e C.

O smoke test usa somente uma cena demonstrativa.
Ele nao simula regras de gameplay.

As informacoes completas do HUD serao integradas na D7.

## Validacao

composer check

php tools/smoke_raylib_renderer.php

Nenhuma classe de dominio acessa Raylib ou FFI.