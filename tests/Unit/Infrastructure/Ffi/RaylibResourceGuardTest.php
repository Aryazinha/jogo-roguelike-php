<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Infrastructure\Ffi;

use InvalidArgumentException;
use Jogo\Infrastructure\Ffi\RaylibBinding;
use LogicException;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class RaylibResourceGuardTest extends TestCase
{
    public function test_it_rejects_invalid_window_dimensions_before_using_native_code(): void
    {
        $binding = $this->bindingWithoutLibrary();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dimensoes e titulo invalidos.');

        $binding->openWindow(0, 450, 'Teste');
    }

    public function test_it_rejects_an_empty_window_title_before_using_native_code(): void
    {
        $binding = $this->bindingWithoutLibrary();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Dimensoes e titulo invalidos.');

        $binding->openWindow(800, 450, '  ');
    }

    public function test_it_does_not_draw_before_opening_a_window(): void
    {
        $binding = $this->bindingWithoutLibrary();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A janela nao esta aberta.');

        $binding->beginDrawing();
    }

    public function test_it_does_not_load_a_texture_before_opening_a_window(): void
    {
        $binding = $this->bindingWithoutLibrary();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A janela nao esta aberta.');

        $binding->loadTexture(__FILE__);
    }

    public function test_it_does_not_load_a_font_before_opening_a_window(): void
    {
        $binding = $this->bindingWithoutLibrary();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A janela nao esta aberta.');

        $binding->loadFont(__FILE__);
    }

    public function test_it_does_not_start_audio_before_opening_a_window(): void
    {
        $binding = $this->bindingWithoutLibrary();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A janela nao esta aberta.');

        $binding->initAudio();
    }

    public function test_it_does_not_load_sound_without_an_audio_device(): void
    {
        $binding = $this->bindingWithoutLibrary();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A janela nao esta aberta.');

        $binding->loadSound(__FILE__);
    }

    public function test_it_does_not_load_music_without_an_audio_device(): void
    {
        $binding = $this->bindingWithoutLibrary();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A janela nao esta aberta.');

        $binding->loadMusic(__FILE__);
    }

    public function test_it_does_not_read_mouse_before_opening_a_window(): void
    {
        $binding = $this->bindingWithoutLibrary();

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('A janela nao esta aberta.');

        $binding->getMouseX();
    }

    public function test_closing_an_unopened_window_is_safe(): void
    {
        $binding = $this->bindingWithoutLibrary();

        $this->expectNotToPerformAssertions();

        $binding->closeWindow();
        $binding->closeWindow();
        $binding->closeAudio();
    }

    private function bindingWithoutLibrary(): RaylibBinding
    {
        // Testa as protecoes sem precisar abrir uma janela.
        $reflection = new ReflectionClass(RaylibBinding::class);

        return $reflection->newInstanceWithoutConstructor();
    }
}
