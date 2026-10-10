<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class A3ArchitectureTest extends TestCase
{
    public function test_domain_and_abstract_input_have_no_graphics_or_io_dependencies(): void
    {
        $root = dirname(__DIR__, 2);
        // Adaptadores concretos futuros da D podem usar o binding; esta regra protege a A3.
        $files = [$root.'/src/Core/GameLoop.php', $root.'/src/Infrastructure/Input/InputInterface.php',
            $root.'/src/Infrastructure/Input/ScriptedInput.php'];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root.'/src/Domain', FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            self::assertInstanceOf(SplFileInfo::class, $file);

            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        $violations = [];
        $ioFunctions = ['file_get_contents', 'file_put_contents', 'fopen', 'fwrite', 'readline',
            'getenv', 'hrtime', 'microtime', 'sleep', 'usleep', 'curl_init'];

        foreach ($files as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);

            foreach (token_get_all($source) as $token) {
                if (! is_array($token)) {
                    continue;
                }

                [$type, $text, $line] = $token;

                if ($type === T_ECHO || $type === T_PRINT || $type === T_INCLUDE
                    || $type === T_INCLUDE_ONCE || $type === T_REQUIRE || $type === T_REQUIRE_ONCE) {
                    $violations[] = "$file:$line: $text";
                }

                if (! in_array($type, [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    continue;
                }

                $name = strtolower(ltrim($text, '\\'));

                if ($name === 'ffi' || str_starts_with($name, 'ffi\\')
                    || str_contains($name, 'raylib') || in_array($name, $ioFunctions, true)
                    || (str_contains($source, 'namespace Jogo\\Domain\\')
                        && (str_starts_with($name, 'jogo\\infrastructure\\') || str_starts_with($name, 'jogo\\core\\')))) {
                    $violations[] = "$file:$line: $text";
                }
            }
        }

        self::assertSame([], $violations, implode("\n", $violations));
    }
}
