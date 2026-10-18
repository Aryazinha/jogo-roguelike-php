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
    // Assinaturas e layouts do raylib.h v5.5 (64 bits).
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
        typedef struct Rectangle {
            float x;
            float y;
            float width;
            float height;
        } Rectangle;
        typedef struct Texture {
            unsigned int id;
            int width;
            int height;
            int mipmaps;
            int format;
        } Texture;
        typedef Texture Texture2D;
        typedef struct GlyphInfo GlyphInfo;
        typedef struct Font {
            int baseSize;
            int glyphCount;
            int glyphPadding;
            Texture2D texture;
            Rectangle *recs;
            GlyphInfo *glyphs;
        } Font;
        typedef struct AudioStream {
            void *buffer;
            void *processor;
            unsigned int sampleRate;
            unsigned int sampleSize;
            unsigned int channels;
        } AudioStream;
        typedef struct Sound {
            AudioStream stream;
            unsigned int frameCount;
        } Sound;
        typedef struct Music {
            AudioStream stream;
            unsigned int frameCount;
            bool looping;
            int ctxType;
            void *ctxData;
        } Music;

        void InitWindow(int width, int height, const char *title);
        bool IsWindowReady(void);
        bool WindowShouldClose(void);
        void CloseWindow(void);
        void SetTargetFPS(int fps);
        int GetFPS(void);
        double GetTime(void);
        float GetFrameTime(void);
        void BeginDrawing(void);
        void EndDrawing(void);
        void ClearBackground(Color color);
        void DrawText(const char *text, int x, int y, int size, Color color);
        void DrawRectangle(int x, int y, int width, int height, Color color);
        void DrawCircleV(Vector2 center, float radius, Color color);
        bool IsKeyDown(int key);
        bool IsKeyPressed(int key);
        bool IsMouseButtonDown(int button);
        bool IsMouseButtonPressed(int button);
        int GetMouseX(void);
        int GetMouseY(void);
        int GetCharPressed(void);
        void SetExitKey(int key);

        Texture2D LoadTexture(const char *fileName);
        bool IsTextureValid(Texture2D texture);
        void DrawTexture(Texture2D texture, int x, int y, Color tint);
        void DrawTexturePro(Texture2D texture, Rectangle source, Rectangle dest, Vector2 origin, float rotation, Color tint);
        void UnloadTexture(Texture2D texture);
        Font LoadFont(const char *fileName);
        bool IsFontValid(Font font);
        void DrawTextEx(Font font, const char *text, Vector2 position, float size, float spacing, Color tint);
        void UnloadFont(Font font);

        void InitAudioDevice(void);
        bool IsAudioDeviceReady(void);
        void CloseAudioDevice(void);
        Sound LoadSound(const char *fileName);
        bool IsSoundValid(Sound sound);
        void PlaySound(Sound sound);
        void UnloadSound(Sound sound);
        Music LoadMusicStream(const char *fileName);
        bool IsMusicValid(Music music);
        void PlayMusicStream(Music music);
        void UpdateMusicStream(Music music);
        void StopMusicStream(Music music);
        void UnloadMusicStream(Music music);
    C;

    private FFI $raylib;

    private bool $windowOpen = false;

    private bool $drawing = false;

    private bool $audioOpen = false;

    private int $nextResourceId = 1;

    /** @var array<string, CData> */
    private array $textures = [];

    /** @var array<string, CData> */
    private array $fonts = [];

    /** @var array<string, CData> */
    private array $sounds = [];

    /** @var array<string, CData> */
    private array $musics = [];

    public function __construct(?string $libraryPath = null)
    {
        $libraryPath ??= dirname(__DIR__, 3).'/lib/raylib.dll';

        if (! is_file($libraryPath)) {
            throw new RuntimeException(
                'Biblioteca Raylib nao encontrada: '.$libraryPath
            );
        }

        try {
            $this->raylib = FFI::cdef(self::HEADER, $libraryPath);
        } catch (Throwable $error) {
            throw new RuntimeException(
                'Falha ao carregar Raylib ou simbolo da versao 5.5: '.$error->getMessage(),
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

    public function shouldClose(): bool
    {
        $this->requireWindow();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        return $this->raylib->WindowShouldClose();
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

    public function getFps(): int
    {
        $this->requireWindow();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        return $this->raylib->GetFPS();
    }

    public function getTime(): float
    {
        $this->requireWindow();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        return $this->raylib->GetTime();
    }

    public function getFrameTime(): float
    {
        $this->requireWindow();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        return $this->raylib->GetFrameTime();
    }

    public function isKeyDown(int $key): bool
    {
        $this->requireWindow();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        return $this->raylib->IsKeyDown($key);
    }

    public function isKeyPressed(int $key): bool
    {
        $this->requireWindow();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        return $this->raylib->IsKeyPressed($key);
    }

    public function isMouseButtonDown(int $button): bool
    {
        $this->requireWindow();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        return $this->raylib->IsMouseButtonDown($button);
    }

    public function isMouseButtonPressed(int $button): bool
    {
        $this->requireWindow();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        return $this->raylib->IsMouseButtonPressed($button);
    }

    public function getMouseX(): int
    {
        $this->requireWindow();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        return $this->raylib->GetMouseX();
    }

    public function getMouseY(): int
    {
        $this->requireWindow();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        return $this->raylib->GetMouseY();
    }

    public function getCharPressed(): int
    {
        $this->requireWindow();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        return $this->raylib->GetCharPressed();
    }

    public function setExitKey(int $key): void
    {
        $this->requireWindow();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->SetExitKey($key);
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
            throw new InvalidArgumentException('Circulo invalido.');
        }

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->DrawCircleV(
            $this->point($x, $y),
            $radius,
            $this->color($r, $g, $b)
        );
    }

    public function loadTexture(string $path): string
    {
        $this->requireWindow();
        $this->requireFile($path);

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $texture = $this->raylib->LoadTexture($path);

        if (! $texture instanceof CData) {
            throw new RuntimeException(
                'Retorno invalido ao carregar textura.'
            );
        }

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        if (! $this->raylib->IsTextureValid($texture)) {
            throw new RuntimeException('Textura invalida: '.$path);
        }

        $id = $this->resourceId('texture');
        $this->textures[$id] = $texture;

        return $id;
    }

    public function drawTexture(
        string $id,
        int $x,
        int $y
    ): void {
        $this->requireDrawing();

        $texture = $this->textures[$id]
            ?? throw new InvalidArgumentException(
                'Textura desconhecida: '.$id
            );

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->DrawTexture(
            $texture,
            $x,
            $y,
            $this->color(255, 255, 255)
        );
    }

    /**
     * Desenha uma textura com origem no centro.
     * O tamanho visual corresponde ao diametro da entidade.
     */
    public function drawTextureCentered(
        string $id,
        float $x,
        float $y,
        float $size,
    ): void {
        $this->requireDrawing();

        if (
            ! is_finite($x)
            || ! is_finite($y)
            || ! is_finite($size)
            || $size <= 0.0
        ) {
            throw new InvalidArgumentException(
                'Posicao ou tamanho da textura invalido.'
            );
        }

        $texture = $this->textures[$id]
            ?? throw new InvalidArgumentException(
                'Textura desconhecida: '.$id
            );

        $source = $this->rectangle(
            0.0,
            0.0,
            // @phpstan-ignore property.notFound
            (float) $texture->width,
            // @phpstan-ignore property.notFound
            (float) $texture->height,
        );

        $destination = $this->rectangle(
            $x,
            $y,
            $size,
            $size,
        );

        $origin = $this->point($size / 2.0, $size / 2.0);

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->DrawTexturePro(
            $texture,
            $source,
            $destination,
            $origin,
            0.0,
            $this->color(255, 255, 255)
        );
    }

    public function unloadTexture(string $id): void
    {
        $this->requireWindow();

        $texture = $this->textures[$id]
            ?? throw new InvalidArgumentException(
                'Textura desconhecida: '.$id
            );

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->UnloadTexture($texture);

        unset($this->textures[$id]);
    }

    public function loadFont(string $path): string
    {
        $this->requireWindow();
        $this->requireFile($path);

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $font = $this->raylib->LoadFont($path);

        if (! $font instanceof CData) {
            throw new RuntimeException(
                'Retorno invalido ao carregar fonte.'
            );
        }

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        if (! $this->raylib->IsFontValid($font)) {
            throw new RuntimeException('Fonte invalida: '.$path);
        }

        $id = $this->resourceId('font');
        $this->fonts[$id] = $font;

        return $id;
    }

    public function drawFontText(
        string $id,
        string $text,
        float $x,
        float $y,
        float $size,
        float $spacing = 1.0
    ): void {
        $this->requireDrawing();

        if (
            ! is_finite($size) ||
            ! is_finite($spacing) ||
            $size <= 0 ||
            $spacing < 0
        ) {
            throw new InvalidArgumentException(
                'Tamanho ou espacamento de fonte invalido.'
            );
        }

        $font = $this->fonts[$id]
            ?? throw new InvalidArgumentException(
                'Fonte desconhecida: '.$id
            );

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->DrawTextEx(
            $font,
            $text,
            $this->point($x, $y),
            $size,
            $spacing,
            $this->color(255, 255, 255)
        );
    }

    public function unloadFont(string $id): void
    {
        $this->requireWindow();

        $font = $this->fonts[$id]
            ?? throw new InvalidArgumentException(
                'Fonte desconhecida: '.$id
            );

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->UnloadFont($font);

        unset($this->fonts[$id]);
    }

    public function initAudio(): bool
    {
        $this->requireWindow();

        if ($this->audioOpen) {
            return true;
        }

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->InitAudioDevice();

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->audioOpen = $this->raylib->IsAudioDeviceReady();

        return $this->audioOpen;
    }

    public function loadSound(string $path): string
    {
        $this->requireAudio();
        $this->requireFile($path);

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $sound = $this->raylib->LoadSound($path);

        if (! $sound instanceof CData) {
            throw new RuntimeException(
                'Retorno invalido ao carregar som.'
            );
        }

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        if (! $this->raylib->IsSoundValid($sound)) {
            throw new RuntimeException('Som invalido: '.$path);
        }

        $id = $this->resourceId('sound');
        $this->sounds[$id] = $sound;

        return $id;
    }

    public function playSound(string $id): void
    {
        $this->requireAudio();

        $sound = $this->sounds[$id]
            ?? throw new InvalidArgumentException(
                'Som desconhecido: '.$id
            );

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->PlaySound($sound);
    }

    public function unloadSound(string $id): void
    {
        $this->requireAudio();

        $sound = $this->sounds[$id]
            ?? throw new InvalidArgumentException(
                'Som desconhecido: '.$id
            );

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->UnloadSound($sound);

        unset($this->sounds[$id]);
    }

    public function loadMusic(string $path): string
    {
        $this->requireAudio();
        $this->requireFile($path);

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $music = $this->raylib->LoadMusicStream($path);

        if (! $music instanceof CData) {
            throw new RuntimeException(
                'Retorno invalido ao carregar musica.'
            );
        }

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        if (! $this->raylib->IsMusicValid($music)) {
            throw new RuntimeException('Musica invalida: '.$path);
        }

        $id = $this->resourceId('music');
        $this->musics[$id] = $music;

        return $id;
    }

    public function playMusic(string $id): void
    {
        $this->requireAudio();

        $music = $this->musics[$id]
            ?? throw new InvalidArgumentException(
                'Musica desconhecida: '.$id
            );

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->PlayMusicStream($music);
    }

    public function updateMusic(string $id): void
    {
        $this->requireAudio();

        $music = $this->musics[$id]
            ?? throw new InvalidArgumentException(
                'Musica desconhecida: '.$id
            );

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->UpdateMusicStream($music);
    }

    public function unloadMusic(string $id): void
    {
        $this->requireAudio();

        $music = $this->musics[$id]
            ?? throw new InvalidArgumentException(
                'Musica desconhecida: '.$id
            );

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->StopMusicStream($music);

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->UnloadMusicStream($music);

        unset($this->musics[$id]);
    }

    public function closeAudio(): void
    {
        if (! $this->audioOpen) {
            return;
        }

        foreach (array_keys($this->musics) as $id) {
            $this->unloadMusic($id);
        }

        foreach (array_keys($this->sounds) as $id) {
            $this->unloadSound($id);
        }

        // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
        $this->raylib->CloseAudioDevice();

        $this->audioOpen = false;
    }

    public function closeWindow(): void
    {
        if (! $this->windowOpen) {
            return;
        }

        try {
            if ($this->drawing) {
                $this->endDrawing();
            }

            $this->closeAudio();

            foreach (array_keys($this->fonts) as $id) {
                $this->unloadFont($id);
            }

            foreach (array_keys($this->textures) as $id) {
                $this->unloadTexture($id);
            }
        } finally {
            // @phpstan-ignore method.notFound (funcao nativa declarada no HEADER)
            $this->raylib->CloseWindow();

            $this->windowOpen = false;
            $this->drawing = false;
        }
    }

    private function resourceId(string $type): string
    {
        return $type.'-'.($this->nextResourceId++);
    }

    private function requireFile(string $path): void
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException(
                'Arquivo de recurso nao encontrado: '.$path
            );
        }
    }

    private function requireAudio(): void
    {
        $this->requireWindow();

        if (! $this->audioOpen) {
            throw new LogicException(
                'Dispositivo de audio nao esta disponivel.'
            );
        }
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

    private function point(float $x, float $y): CData
    {
        if (! is_finite($x) || ! is_finite($y)) {
            throw new InvalidArgumentException(
                'Coordenada nao finita.'
            );
        }

        $point = $this->raylib->new('Vector2');

        if ($point === null) {
            throw new RuntimeException(
                'Falha ao alocar Vector2.'
            );
        }

        FFI::memcpy(
            $point,
            pack('g2', $x, $y),
            8
        );

        return $point;
    }

    private function rectangle(
        float $x,
        float $y,
        float $width,
        float $height,
    ): CData {
        $rectangle = $this->raylib->new('Rectangle');

        if ($rectangle === null) {
            throw new RuntimeException(
                'Falha ao alocar Rectangle.'
            );
        }

        FFI::memcpy(
            $rectangle,
            pack('g4', $x, $y, $width, $height),
            16
        );

        return $rectangle;
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

        FFI::memcpy(
            $color,
            pack('C4', $r, $g, $b, $a),
            4
        );

        return $color;
    }
}
