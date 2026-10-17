# D6 - Menu, instrucoes e selecao

A D6 implementa a navegacao visual da Frente D
na janela Raylib.

## Telas

Menu principal:
- Iniciar
- Ranking
- Instrucoes
- Sair

Selecao:
- Steve
- Alex

Instrucoes:
- Movimento e olhar
- Ataques automaticos
- Experiencia e level up
- Objetivo e boss
- Pausa

Ranking:
- Tela acessivel com retorno
- A consulta real ao SQLite pertence a D10

## Controles

- Setas ou WASD: navegar
- Enter ou espaco: confirmar
- Esc: voltar ou sair do menu principal
- Clique esquerdo: escolher opcoes

## Dados dos personagens

CharacterChoiceCatalog le os dados existentes de
config/characters.json.

Os valores nao sao redefinidos nesta entrega.

A tela apenas apresenta:
- Nome
- Vida
- Regeneracao
- Arma inicial
- Descricao da passiva

Ao confirmar a escolha, a tela envia somente o ID
do personagem ao callback fornecido pela Application.

Criacao do jogador e aplicacao dos atributos continuam
sob responsabilidade da Frente B.

## Integracao com D5

MenuState, CharacterSelectState, InstructionsState
e RankingState reutilizam StateStack.

As telas podem solicitar push/pop e as transicoes
sao aplicadas nos pontos seguros da pilha.

A entrada dos menus deve ser consultada uma vez
por quadro, fora das atualizacoes de gameplay.

## Janela

O smoke test utiliza janela fixa de 800x450.

Redimensionamento dinamico depende de suporte
adicional no binding e de acordo da equipe.

## Pendencias futuras

- Ranking real e persistencia: D10
- Inicio real da partida: D7/D12
- Assets visuais definitivos: D11
- Integracao completa das telas: D12

## Validacao

composer check

php tools/smoke_raylib_menu.php

O teste grafico abre o menu para navegar por teclado
ou mouse. A confirmacao de Steve/Alex mostra o ID
selecionado e retorna ao menu de demonstracao.