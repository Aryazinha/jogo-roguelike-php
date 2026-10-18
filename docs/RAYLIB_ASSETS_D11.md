# D11 — Assets e Créditos

## Conteúdo

A Frente D11 inclui:
- 20 sprites PNG originais de 32x32 pixels
- 7 efeitos sonoros WAV
- 2 faixas WAV para menu e partida
- Catálogo central de recursos
- Verificação automatizada de arquivos e créditos
- Integração opcional com renderer, HUD e menus
- Demonstração gráfica e sonora

## Geração

php tools/generate_d11_assets.php

O gerador utiliza somente PHP e zlib.
Não exige downloads de terceiros nem extensão GD.

Os arquivos são gerados em:
- assets/sprites/
- assets/audio/
- assets/manifest.json
- assets/CREDITOS.md

O manifesto associa identificadores lógicos aos caminhos.

## Proveniência

Todos os recursos gerados são originais e estão
registrados em assets/CREDITOS.md.

Nenhum arquivo original do Minecraft foi usado.

Os sons e desenhos são simples, mas funcionais.
A direção de arte final ainda pode ser refinada
sem modificar as regras do domínio.

## Fonte

A fonte é a padrão embutida na Raylib 5.5.
Nenhum arquivo TTF externo é distribuído.

## Posicionamento

As texturas usam origem central.

O diâmetro visual corresponde a:
2 x raio informado pela entidade visual.

O DrawTexturePro permite redimensionamento
sem alterar os tamanhos lógicos do domínio.

## Integração

AssetCatalog verifica:
- recursos obrigatórios
- arquivos existentes
- créditos
- caminhos registrados
- formato do manifesto

PlayFrameFactory pode receber AssetCatalog.
Sem catálogo, mantém as formas geométricas D7.

RaylibRenderer usa texturas escaladas e centralizadas.

RaylibAudio recebe os efeitos e faixas do catálogo.

A integração final com os estados reais será
feita na D12.

## Validação

php tools/generate_d11_assets.php

composer check

php tools/smoke_raylib_assets.php

Na janela:
- G: visualizar cena com sprites
- M: retornar ao menu
- 1 a 7: testar efeitos sonoros
- X: sair

## Pendências

- Revisar a identidade visual com a equipe.
- Validar os sprites na partida real da D12.
- Refinar músicas e efeitos, se necessário.
- Registrar eventuais recursos adicionais e licenças.