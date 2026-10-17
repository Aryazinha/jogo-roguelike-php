<?php

declare(strict_types=1);

namespace Jogo\Tests\Unit\Infrastructure\Ffi;

use FilesystemIterator;
use Jogo\Infrastructure\Ffi\RaylibBinding;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;

final class RaylibBindingTest extends TestCase
{
    public function test_it_reports_missing_raylib_library(): void
    {
        $path = __DIR__.'/missing-raylib-for-test.dll';

        self::assertFileDoesNotExist($path);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Biblioteca Raylib nao encontrada'
        );

        new RaylibBinding($path);
    }

    public function test_ffi_is_isolated_in_raylib_binding(): void
    {
        $sourceDirectory = dirname(__DIR__, 4).'/src';

        $bindingPath = $sourceDirectory
            .'/Infrastructure/Ffi/RaylibBinding.php';

        $bindingPath = str_replace('\\', '/', $bindingPath);

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(
                $sourceDirectory,
                FilesystemIterator::SKIP_DOTS
            )
        );

        $violations = [];

        foreach ($files as $file) {
            // Garante ao PHPStan que o arquivo e um objeto valido.
            if (! ($file instanceof SplFileInfo)) {
                continue;
            }

            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = $file->getPathname();

            $normalizedPath = str_replace('\\', '/', $path);

            // Somente RaylibBinding pode acessar diretamente a FFI.
            if ($normalizedPath === $bindingPath) {
                continue;
            }

            $content = file_get_contents($path);

            if ($content === false) {
                throw new RuntimeException(
                    "Nao foi possivel ler: {$path}"
                );
            }

            // Identifica chamadas diretas a FFI nas outras classes.
            if (
                preg_match(
                    '/\b(?:FFI\s*::|use\s+FFI\s*;)/',
                    $content
                ) === 1
            ) {
                $violations[] = $path;
            }
        }

        self::assertSame(
            [],
            $violations,
            'FFI deve ser utilizada somente em RaylibBinding.'
        );
    }

    public function test_binding_exposes_required_window_operations(): void
    {
        $methods = [
            'openWindow',
            'closeWindow',
            'shouldClose',
            'beginDrawing',
            'endDrawing',
            'setTargetFps',
            'clearBackground',
            'drawText',
            'drawRectangle',
            'drawCircle',
            'isKeyDown',
            'getFps',
        ];

        foreach ($methods as $method) {
            self::assertTrue(
                method_exists(RaylibBinding::class, $method),
                "Metodo obrigatorio ausente: {$method}"
            );
        }
    }
}
