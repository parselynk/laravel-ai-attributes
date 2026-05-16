<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Contracts;

interface AIDriver
{
    /**
     * Generate a response from the AI provider.
     *
     * @param  array<string, mixed>  $context  Arbitrary contextual data appended to the prompt (typically the model's attributes).
     * @param  array<string, mixed>  $options  Per-call overrides: persona, model, max_tokens.
     */
    public function generate(string $prompt, array $context = [], array $options = []): string;
}
