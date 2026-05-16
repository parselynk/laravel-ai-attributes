<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Support;

class PromptFormatter
{
    protected const DEFAULT_SYSTEM = <<<'TXT'
You are an AI assistant that processes structured data for an Eloquent model attribute.
Follow the instruction precisely against the data provided.
Return ONLY the requested output — no preamble, no explanation, no surrounding text, no markdown fences unless explicitly requested.
TXT;

    public static function systemMessage(?string $persona = null): string
    {
        if ($persona === null || trim($persona) === '') {
            return self::DEFAULT_SYSTEM;
        }

        return $persona."\n\n".self::DEFAULT_SYSTEM;
    }

    public static function userMessage(string $prompt, array $context): string
    {
        if (empty($context)) {
            return $prompt;
        }

        $json = json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return <<<TXT
Instruction:
{$prompt}

Data (JSON):
{$json}
TXT;
    }
}
