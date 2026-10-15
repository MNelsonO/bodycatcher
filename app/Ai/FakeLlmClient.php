<?php

namespace App\Ai;

use RuntimeException;
use Throwable;

/**
 * In-memory model for tests. It never opens a socket.
 */
class FakeLlmClient implements LlmClient
{
    /**
     * @var list<string|Throwable>
     */
    public array $script = [];

    /**
     * @var list<array{system: string, user: string}>
     */
    public array $calls = [];

    public function push(string $content): void
    {
        $this->script[] = $content;
    }

    public function pushFailure(Throwable $throwable): void
    {
        $this->script[] = $throwable;
    }

    public function complete(string $system, string $user): string
    {
        $this->calls[] = [
            'system' => $system,
            'user' => $user,
        ];

        $next = array_shift($this->script);

        if ($next instanceof Throwable) {
            throw $next;
        }

        if (! is_string($next)) {
            throw new RuntimeException('No scripted model response.');
        }

        return $next;
    }
}
