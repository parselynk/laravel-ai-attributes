<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Exceptions;

use RuntimeException;

class AIAttributeException extends RuntimeException
{
    public static function missingApiKey(string $driver): self
    {
        return new self(sprintf(
            'Missing API key for AI driver [%s]. Set the corresponding environment variable.',
            $driver,
        ));
    }

    public static function providerFailed(string $driver, int $status, string $body): self
    {
        return new self(sprintf(
            'AI provider [%s] returned HTTP %d: %s',
            $driver,
            $status,
            mb_strimwidth($body, 0, 500, '...'),
        ));
    }

    public static function malformedResponse(string $driver, mixed $payload): self
    {
        return new self(sprintf(
            'AI provider [%s] returned a malformed response: %s',
            $driver,
            is_array($payload) ? json_encode($payload) : (string) $payload,
        ));
    }

    public static function undefinedAttribute(string $modelClass, string $attribute): self
    {
        return new self(sprintf(
            'No AI attribute defined for [%s] on %s. Add it to the $aiAttributes array.',
            $attribute,
            $modelClass,
        ));
    }

    public static function invalidAttributeConfig(string $modelClass, string $attribute): self
    {
        return new self(sprintf(
            'Invalid AI attribute config for [%s] on %s. Expected a string (prompt) or an array with a "prompt" key.',
            $attribute,
            $modelClass,
        ));
    }

    public static function unsupportedFormat(string $format): self
    {
        return new self(sprintf(
            'Unsupported AI attribute format [%s]. Use one of: text, json, number, bool.',
            $format,
        ));
    }

    public static function invalidJson(string $raw): self
    {
        return new self(sprintf(
            'AI returned invalid JSON: %s',
            mb_strimwidth($raw, 0, 200, '...'),
        ));
    }

    public static function invalidNumber(string $raw): self
    {
        return new self(sprintf(
            'AI returned a non-numeric value when number was expected: %s',
            mb_strimwidth($raw, 0, 200, '...'),
        ));
    }

    public static function invalidBool(string $raw): self
    {
        return new self(sprintf(
            'AI returned a value that could not be cast to bool: %s',
            mb_strimwidth($raw, 0, 200, '...'),
        ));
    }

    public static function cannotQueueUnsavedModel(string $modelClass): self
    {
        return new self(sprintf(
            'Cannot queue an AI attribute for unsaved %s — save the model first so the job can rehydrate it on the worker.',
            $modelClass,
        ));
    }

    public static function modelMissingTrait(string $modelClass): self
    {
        return new self(sprintf(
            'Model [%s] does not use the HasAIAttributes trait.',
            $modelClass,
        ));
    }
}
