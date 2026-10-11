<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class A9ArchitectureTest extends TestCase
{
    public function test_simulation_and_pool_are_separate_from_graphics_and_other_fronts(): void
    {
        $root = dirname(__DIR__, 2);
        $files = [$root.'/src/Infrastructure/Input/NeutralInput.php'];
        foreach (['src/Application/Simulation', 'src/Domain/Pool'] as $directory) {
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, FilesystemIterator::SKIP_DOTS)) as $file) {
                self::assertInstanceOf(SplFileInfo::class, $file);
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }
        $violations = [];
        foreach ($files as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            foreach (token_get_all($source) as $token) {
                if (! is_array($token) || ! in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    continue;
                }
                $name = strtolower($token[1]);
                if (str_contains($name, 'raylib') || $name === 'ffi' || str_contains($name, 'renderer')
                    || $name === 'xp' || str_contains($name, '\\xpsystem') || str_contains($name, 'levelup') || str_contains($name, 'wave')
                    || $name === 'rand' || $name === 'mt_rand' || $name === 'microtime'
                    || ($name === 'hrtime' && basename($file) !== 'MonotonicMeasurement.php')) {
                    $violations[] = $file.':'.$token[2].': '.$name;
                }
            }
        }
        self::assertSame([], $violations);
        $system = file_get_contents($root.'/src/Domain/System/WeaponAttackSystem.php');
        self::assertIsString($system);
        self::assertStringNotContainsString('new LogicalProjectile', $system);
        self::assertStringNotContainsString('clone ', $system);
    }
}
