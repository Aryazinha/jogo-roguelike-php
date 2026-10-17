<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Results;

use Jogo\Infrastructure\Ffi\RaylibBinding;

final readonly class RaylibResultsInput implements ResultsInputInterface
{
    public function __construct(
        private RaylibBinding $raylib,
    ) {}

    /** @phpstan-impure */
    public function poll(): ResultsInputEvent
    {
        $text = '';

        for ($i = 0; $i < 48; $i++) {
            $codepoint = $this->raylib->getCharPressed();

            if ($codepoint === 0) {
                break;
            }

            if ($codepoint < 32 || $codepoint === 127) {
                continue;
            }

            $character = mb_chr($codepoint, 'UTF-8');

            $text .= $character;
        }

        $left = $this->raylib->isKeyPressed(263);
        $right = $this->raylib->isKeyPressed(262);

        return new ResultsInputEvent(
            text: $text,
            horizontal: (int) $right - (int) $left,
            confirm: $this->raylib->isKeyPressed(257),
            back: $this->raylib->isKeyPressed(256),
            backspace: $this->raylib->isKeyPressed(259),
            click: $this->raylib->isMouseButtonPressed(0),
            mouseX: $this->raylib->getMouseX(),
            mouseY: $this->raylib->getMouseY(),
        );
    }
}
