<?php

declare(strict_types=1);

namespace Jogo\Application\Benchmark;

use DateTimeImmutable;
use DateTimeZone;

final class BenchmarkEnvironment
{
    /** @return array<string, mixed> */
    public static function capture(string $root): array
    {
        $status = function_exists('opcache_get_status') ? opcache_get_status(false) : false;
        $files = [];
        foreach (['weapons.json', 'upgrades.json', 'evolutions.json'] as $file) {
            $files['config/'.$file] = hash_file('sha256', $root.'/config/'.$file);
        }
        $tracked = self::git($root, ['status', '--porcelain', '--untracked-files=no']);
        $untracked = self::git($root, ['ls-files', '--others', '--exclude-standard']);

        return ['executedAtUtc' => (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_ATOM),
            'os' => PHP_OS_FAMILY, 'osRelease' => php_uname('r'), 'architecture' => php_uname('m'),
            'processorIdentifier' => getenv('PROCESSOR_IDENTIFIER') ?: null, 'logicalProcessors' => getenv('NUMBER_OF_PROCESSORS') ?: null,
            'physicalMemoryBytes' => null, 'phpMemoryLimit' => ini_get('memory_limit'), 'phpVersion' => PHP_VERSION,
            'phpBinary' => PHP_BINARY, 'sapi' => PHP_SAPI, 'composerVersion' => null,
            'opcacheEnableCli' => ini_get('opcache.enable_cli'), 'opcacheActive' => $status !== false && $status['opcache_enabled'],
            'jitConfigured' => ini_get('opcache.jit'), 'jitActive' => $status !== false && ($status['jit']['on'] ?? false),
            'jitBufferSize' => ini_get('opcache.jit_buffer_size'), 'opcacheCacheId' => ini_get('opcache.cache_id'),
            'ffiEnable' => ini_get('ffi.enable'), 'ffiExtensionLoaded' => extension_loaded('FFI'),
            'gitCommit' => self::git($root, ['rev-parse', 'HEAD']), 'gitBranch' => self::git($root, ['branch', '--show-current']),
            'trackedWorkingTree' => $tracked, 'trackedDirty' => $tracked === null ? null : $tracked !== '',
            'workingTreeDirty' => $tracked === null || $untracked === null ? null : $tracked !== '' || $untracked !== '',
            'untrackedFiles' => $untracked, 'configSha256' => $files];
    }

    /** @param list<string> $arguments */
    private static function git(string $root, array $arguments): ?string
    {
        if (! function_exists('proc_open')) {
            return null;
        }
        $process = proc_open(['git', ...$arguments], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $root);
        if (! is_resource($process)) {
            return null;
        }
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return proc_close($process) === 0 && $output !== false ? trim($output) : null;
    }
}
