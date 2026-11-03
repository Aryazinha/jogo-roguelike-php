<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Results;

use Jogo\Infrastructure\Assets\AssetCatalog;
use Jogo\Infrastructure\Ffi\RaylibBinding;
use Jogo\Infrastructure\Rendering\RaylibResourceCache;

final readonly class RaylibResultsRenderer implements ResultsScreenRendererInterface
{
    public function __construct(
        private RaylibBinding $raylib,
        private ?AssetCatalog $catalog = null,
        private ?RaylibResourceCache $assets = null,
    ) {}

    public function drawFinish(
        FinalRunView $run,
        string $name,
        string $message,
        bool $saved,
        int $selected,
    ): void {
        $this->raylib->clearBackground(22, 28, 37);

        $this->raylib->drawRectangle(
            0,
            0,
            800,
            9,
            $run->victory ? 235 : 190,
            $run->victory ? 193 : 65,
            55
        );

        if ($this->catalog !== null && $this->assets !== null) {
            $icon = $run->victory ? 'victory' : 'defeat';

            $this->raylib->drawTextureCentered(
                $this->assets->texture(
                    $this->catalog->sprite($icon)
                ),
                710.0,
                76.0,
                76.0
            );
        }

        $this->raylib->drawText(
            $run->victory ? 'VITORIA!' : 'GAME OVER',
            42,
            32,
            37
        );

        $this->raylib->drawText(
            'PERSONAGEM: '.$run->character,
            45,
            97,
            23
        );

        $this->raylib->drawText(
            'NIVEL: '.$run->level.'     DERROTADOS: '.$run->kills,
            45,
            132,
            22
        );

        $this->raylib->drawText(
            'TEMPO: '.$run->formattedTime(),
            45,
            166,
            22
        );

        $this->raylib->drawText(
            'PONTUACAO: '.$run->score,
            45,
            207,
            26,
            245,
            210,
            95
        );

        $this->raylib->drawText(
            'NOME DO JOGADOR',
            45,
            265,
            18
        );

        $this->raylib->drawRectangle(
            45,
            291,
            706,
            45,
            42,
            52,
            63
        );

        $this->raylib->drawText(
            mb_strimwidth($name, 0, 38, '...').($saved ? '' : '_'),
            58,
            303,
            21
        );

        $this->raylib->drawText(
            mb_strimwidth($message, 0, 75, '...'),
            46,
            349,
            16,
            195,
            218,
            190
        );

        $buttons = ['SALVAR', 'RANKING', 'NOVA PARTIDA', 'MENU'];

        foreach ($buttons as $index => $button) {
            $active = $index === $selected;

            $this->raylib->drawRectangle(
                22 + $index * 195,
                393,
                180,
                40,
                $active ? 70 : 40,
                $active ? 143 : 62,
                $active ? 88 : 75
            );

            $this->raylib->drawText(
                $button,
                32 + $index * 195,
                404,
                17
            );
        }
    }

    public function drawRanking(
        RankingPage $page,
        int $offset,
        string $message,
    ): void {
        $this->raylib->clearBackground(22, 28, 37);
        $this->raylib->drawRectangle(0, 0, 800, 9, 80, 168, 108);
        $this->raylib->drawText('RANKING', 42, 30, 36);

        $this->raylib->drawText(
            'POS  NOME               PONTOS     TEMPO',
            45,
            91,
            19,
            230,
            210,
            135
        );

        foreach ($page->rows as $index => $row) {
            $y = 124 + $index * 39;

            $this->raylib->drawRectangle(
                41,
                $y - 3,
                716,
                34,
                $index % 2 === 0 ? 39 : 47,
                56,
                65
            );

            $time = (int) $row->elapsedSeconds;
            $formatted = sprintf(
                '%02d:%02d',
                intdiv($time, 60),
                $time % 60
            );

            $this->raylib->drawText(
                sprintf(
                    '%02d   %-18s %7d     %s',
                    $offset + $index + 1,
                    mb_strimwidth($row->name, 0, 17, '...'),
                    $row->score,
                    $formatted
                ),
                49,
                $y + 4,
                18
            );
        }

        $this->raylib->drawText(
            $message !== '' ? $message : 'Setas: paginas | ESC: voltar',
            45,
            362,
            17
        );

        $buttons = [
            ['ANTERIOR', 80],
            ['PROXIMA', 315],
            ['VOLTAR', 550],
        ];

        foreach ($buttons as [$label, $x]) {
            $this->raylib->drawRectangle(
                $x,
                393,
                170,
                40,
                67,
                120,
                83
            );

            $this->raylib->drawText(
                $label,
                $x + 17,
                403,
                19
            );
        }
    }
}
