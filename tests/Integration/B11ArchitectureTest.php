<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class B11ArchitectureTest extends TestCase
{
    public function test_domain_does_not_depend_on_persistence_configuration_or_presentation(): void
    {
        $root = dirname(__DIR__, 2).'/src/Domain';
        $forbidden = ['PDO', 'Jogo\\Infrastructure', 'Jogo\\Core\\Config', 'Jogo\\Application', 'FFI', 'Raylib', 'sqlite'];
        $violations = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)) as $file) {
            self::assertInstanceOf(SplFileInfo::class, $file);
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            foreach ($forbidden as $dependency) {
                if (preg_match('/\b'.preg_quote($dependency, '/').'\b/i', $source) === 1) {
                    $violations[] = substr($file->getPathname(), strlen($root) + 1).' usa '.$dependency;
                }
            }
        }

        self::assertSame([], $violations);
    }

    public function test_score_repository_implementation_stays_in_infrastructure(): void
    {
        $root = dirname(__DIR__, 2).'/src';

        self::assertFileExists($root.'/Domain/Score/ScoreRepositoryInterface.php');
        self::assertFileExists($root.'/Infrastructure/Persistence/SqliteScoreRepository.php');
        $interface = file_get_contents($root.'/Domain/Score/ScoreRepositoryInterface.php');
        self::assertIsString($interface);
        self::assertStringNotContainsString('PDO', $interface);
    }
}
