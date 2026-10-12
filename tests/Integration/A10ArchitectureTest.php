<?php

declare(strict_types=1);

namespace Jogo\Tests\Integration;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class A10ArchitectureTest extends TestCase
{
    public function test_benchmark_has_no_content_or_presentation_and_uses_real_systems(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/src/Application/Benchmark', FilesystemIterator::SKIP_DOTS)) as $file) {
            self::assertInstanceOf(SplFileInfo::class, $file);
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            self::assertIsString($source);
            foreach (token_get_all($source) as $token) {
                if (! is_array($token) || ! in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                    continue;
                }
                $name = strtolower($token[1]);
                self::assertDoesNotMatchRegularExpression('/raylib|renderer|hud|audio|\\\\xpsystem|\\\\wavedirector|\\\\enemyfactory|\\\\scorecalculator|\\\\repository|levelup/', $name);
                self::assertNotSame('ffi', $name);
                self::assertNotContains($name, ['sleep', 'usleep', 'rand', 'mt_rand', 'microtime']);
            }
        }
        $composition = file_get_contents($root.'/src/Application/Benchmark/BenchmarkSession.php');
        self::assertIsString($composition);
        foreach (['GameLoop', 'ScriptedInput', 'MovementSystem', 'SpatialHash', 'SpatialWorldView', 'CollisionSystem', 'DamageSystem', 'EventBus', 'WeaponAttackSystem', 'ProjectilePool', 'WeaponProgressionService'] as $system) {
            self::assertStringContainsString($system, $composition);
        }
        $composer = json_decode((string) file_get_contents($root.'/composer.json'), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($composer);
        self::assertIsArray($composer['scripts']);
        self::assertSame(['@fmt:check', '@stan', '@test'], $composer['scripts']['check']);
        self::assertSame('@php -d opcache.enable_cli=0 tools/simulate.php', $composer['scripts']['sim']);
    }
}
