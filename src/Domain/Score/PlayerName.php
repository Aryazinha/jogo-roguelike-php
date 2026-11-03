<?php

declare(strict_types=1);

namespace Jogo\Domain\Score;

use InvalidArgumentException;

/** Nome informado ao fim da partida (RF33): espaços normalizados, sem controle, até 20 letras. */
final readonly class PlayerName
{
    public const MAX_LENGTH = 20;

    public string $value;

    public function __construct(string $input)
    {
        if (! mb_check_encoding($input, 'UTF-8')) {
            throw new InvalidArgumentException('O nome deve estar em UTF-8.');
        }
        $normalized = trim((string) preg_replace('/\s+/u', ' ', $input));
        if (preg_match('/\p{Cc}/u', $normalized) === 1) {
            throw new InvalidArgumentException('O nome não pode conter caracteres de controle.');
        }
        if ($normalized === '') {
            throw new InvalidArgumentException('O nome do jogador não pode ficar vazio.');
        }
        if (mb_strlen($normalized) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('O nome pode ter no máximo '.self::MAX_LENGTH.' caracteres.');
        }
        $this->value = $normalized;
    }
}
