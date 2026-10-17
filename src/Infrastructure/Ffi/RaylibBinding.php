<?php

declare(strict_types=1);

namespace Jogo\Infrastructure\Ffi;

use FFI;
use FFI\CData;
use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

final class RaylibBinding
{
    private const HEADER = <<<'C'
        typedef struct Color {
            unsigned char r;
            unsigned char g;
            unsigned char b;
            unsigned char a;
        } Color;

        typedef struct Vector2 {
            float x;
            float y;
        } Vector2;

        void InitWindow(int width, int height, const char *title);
        bool IsWindowReady(void);
        bool WindowShouldClose(void);
        void CloseWindow(void);
        void SetTargetFPS(int fps);
        int GetFPS(void);
        void BeginDrawing(void);
        void EndDrawing(void);
        void ClearBackground(Color color);
        void DrawText(const char *text, int x, int y, int fontSize, Color color);
        void DrawRectangle(int x, int y, int width, int height, Color color);
        void DrawCircleV(Vector2 center, float radius, Color color);
        bool IsKeyDown(int key);
    C;

    private FFI $raylib;

    private bool $windowOpen = false;

    private bool $drawing = false;

    public function __construct(?string $libraryPath = null)
    {
        $libraryPath ??= dirname(__DIR__, 3).'/lib/raylib.dll';

        if (! is_file($libraryPath)) {
            throw new RuntimeException(
                "Biblioteca Raylib nao encontrada: {$libraryPath}"
            );
        }

        try {
            $this->raylib = FFI::cdef(self::HEADER, $libraryPath);
        } catch (Throwable $error) {
            throw new RuntimeException(
                'Falha ao carregar Raylib: '.$error->getMessage(),
                0,
                $error
            );
        }
    }

    public function openWindow(
        int $width,
        int $height,
        string $title
    ): void {
        if ($this->windowOpen) {
            throw new LogicException('A janela ja esta aberta.');
        }

        if ($width <= 0 || $height <= 0 || trim($title) === '') {
            throw new InvalidArgumentException(
                'Dimensoes e titulo invalidos.'
            );
        }

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->InitWindow($width, $height, $title);

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        if (! $this->raylib->IsWindowReady()) {
            // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
            $this->raylib->CloseWindow();

            throw new RuntimeException(
                'Nao foi possivel abrir a janela.'
            );
        }

        $this->windowOpen = true;
    }

    public function setTargetFps(int $fps): void
    {
        $this->requireWindow();

        if ($fps <= 0) {
            throw new InvalidArgumentException(
                'FPS deve ser positivo.'
            );
        }

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->SetTargetFPS($fps);
    }

    public function shouldClose(): bool
    {
        $this->requireWindow();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        return $this->raylib->WindowShouldClose();
    }

    public function beginDrawing(): void
    {
        $this->requireWindow();

        if ($this->drawing) {
            throw new LogicException('Quadro ja iniciado.');
        }

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->BeginDrawing();

        $this->drawing = true;
    }

    public function endDrawing(): void
    {
        if (! $this->drawing) {
            throw new LogicException('Nenhum quadro iniciado.');
        }

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->EndDrawing();

        $this->drawing = false;
    }

    public function clearBackground(
        int $r,
        int $g,
        int $b,
        int $a = 255
    ): void {
        $this->requireDrawing();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->ClearBackground(
            $this->color($r, $g, $b, $a)
        );
    }

    public function drawText(
        string $text,
        int $x,
        int $y,
        int $size,
        int $r = 255,
        int $g = 255,
        int $b = 255
    ): void {
        $this->requireDrawing();

        if ($size <= 0) {
            throw new InvalidArgumentException(
                'Tamanho da fonte deve ser positivo.'
            );
        }

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->DrawText(
            $text,
            $x,
            $y,
            $size,
            $this->color($r, $g, $b)
        );
    }

    public function drawRectangle(
        int $x,
        int $y,
        int $width,
        int $height,
        int $r,
        int $g,
        int $b
    ): void {
        $this->requireDrawing();

        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException(
                'Dimensoes do retangulo invalidas.'
            );
        }

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->DrawRectangle(
            $x,
            $y,
            $width,
            $height,
            $this->color($r, $g, $b)
        );
    }

    public function drawCircle(
        float $x,
        float $y,
        float $radius,
        int $r,
        int $g,
        int $b
    ): void {
        $this->requireDrawing();

        if (
            ! is_finite($x) ||
            ! is_finite($y) ||
            ! is_finite($radius) ||
            $radius <= 0
        ) {
            throw new InvalidArgumentException(
                'Circulo invalido.'
            );
        }

        $center = $this->raylib->new('Vector2');

        if ($center === null) {
            throw new RuntimeException(
                'Falha ao alocar Vector2.'
            );
        }

        // Preenche os dois floats da estrutura sem
        // acessar propriedades dinamicas da FFI.
        FFI::memcpy(
            $center,
            pack('g2', $x, $y),
            8
        );

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->DrawCircleV(
            $center,
            $radius,
            $this->color($r, $g, $b)
        );
    }

    public function isKeyDown(int $key): bool
    {
        $this->requireWindow();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        return $this->raylib->IsKeyDown($key);
    }

    public function getFps(): int
    {
        $this->requireWindow();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        return $this->raylib->GetFPS();
    }

    public function closeWindow(): void
    {
        if (! $this->windowOpen) {
            return;
        }

        try {
            if ($this->drawing) {
                // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
                $this->raylib->EndDrawing();
            }
        } finally {
            // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
            $this->raylib->CloseWindow();

            $this->windowOpen = false;
            $this->drawing = false;
        }
    }

    private function color(
        int $r,
        int $g,
        int $b,
        int $a = 255
    ): CData {
        foreach ([$r, $g, $b, $a] as $value) {
            if ($value < 0 || $value > 255) {
                throw new InvalidArgumentException(
                    'Canais de cor devem ficar entre 0 e 255.'
                );
            }
        }

        $color = $this->raylib->new('Color');

        if ($color === null) {
            throw new RuntimeException(
                'Falha ao alocar Color.'
            );
        }

        // Preenche os quatro canais RGBA
        // sem acessar propriedades dinamicas da FFI.
        FFI::memcpy(
            $color,
            pack('C4', $r, $g, $b, $a),
            4
        );

        return $color;
    }

    private function requireWindow(): void
    {
        if (! $this->windowOpen) {
            throw new LogicException(
                'A janela nao esta aberta.'
            );
        }
    }

    private function requireDrawing(): void
    {
        $this->requireWindow();

        if (! $this->drawing) {
            throw new LogicException(
                'Desenhe entre beginDrawing() e endDrawing().'
            );
        }
    }
}
