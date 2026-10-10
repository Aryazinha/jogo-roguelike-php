<?php

declare(strict_types=1);

namespace Jogo\Core\Config;

use InvalidArgumentException;

/** @internal Verifica chaves duplicadas em JSON cuja sintaxe já foi validada por json_decode. */
final class JsonDuplicateKeyValidator
{
    public function validate(string $json, string $source): void
    {
        // Só strings e estrutura: não substitui o parser JSON nem interpreta valores de gameplay.
        $pattern = <<<'REGEX'
        /"(?:\\.|[^"\\])*"|[{}\[\]:,]/s
        REGEX;
        if (preg_match_all($pattern, $json, $matches) === false) {
            throw new InvalidArgumentException("{$source}: não foi possível verificar chaves duplicadas: ".preg_last_error_msg());
        }
        /** @var list<array{object: bool, expectKey: bool, keys: array<string, true>, path: string, pendingKey: ?string}> $stack */
        $stack = [];
        foreach ($matches[0] as $token) {
            if ($token === '{' || $token === '[') {
                $parent = $stack === [] ? null : $stack[array_key_last($stack)];
                $path = $parent === null ? '' : $parent['path'].($parent['pendingKey'] === null ? '[]' : '.'.$parent['pendingKey']);
                $stack[] = ['object' => $token === '{', 'expectKey' => true, 'keys' => [],
                    'path' => ltrim($path, '.'), 'pendingKey' => null];

                continue;
            }
            if ($token === '}' || $token === ']') {
                array_pop($stack);

                continue;
            }
            if ($stack === []) {
                continue;
            }
            $index = array_key_last($stack);
            if ($token === ':') {
                $stack[$index]['expectKey'] = false;
            } elseif ($token === ',') {
                $stack[$index]['expectKey'] = true;
                $stack[$index]['pendingKey'] = null;
            } elseif ($stack[$index]['object'] && $stack[$index]['expectKey']) {
                $key = json_decode($token, flags: JSON_THROW_ON_ERROR);
                if (! is_string($key)) {
                    throw new InvalidArgumentException("{$source}: chave de objeto inválida.");
                }
                $identity = 'key:'.$key;
                if (isset($stack[$index]['keys'][$identity])) {
                    $path = ltrim($stack[$index]['path'].'.'.$key, '.');
                    throw new InvalidArgumentException("{$source}: {$path} contém chave/ID duplicado.");
                }
                $stack[$index]['keys'][$identity] = true;
                $stack[$index]['pendingKey'] = $key;
            }
        }
    }
}
