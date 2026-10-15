<?php

namespace App\Ai;

interface LlmClient
{
    public function complete(string $system, string $user): string;
}
