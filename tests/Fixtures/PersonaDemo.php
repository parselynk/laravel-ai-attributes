<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Parselynk\AiAttributes\Concerns\HasAIAttributes;

/**
 * Same data + same prompt → different personas → very different outputs.
 * Lets you see what `persona` actually does.
 */
class PersonaDemo extends Model
{
    use HasAIAttributes;

    protected $guarded = [];

    protected $aiAttributes = [
        // No persona — neutral default
        'plain' => 'Describe this in one sentence.',

        'shakespeare' => [
            'prompt' => 'Describe this in one sentence.',
            'persona' => 'You are William Shakespeare. Write in Early Modern English with poetic flourish.',
        ],

        'kid' => [
            'prompt' => 'Describe this in one sentence.',
            'persona' => 'You are a 5-year-old child. Use simple words and be excited.',
        ],

        'pirate' => [
            'prompt' => 'Describe this in one sentence.',
            'persona' => 'You are a pirate captain. Use pirate slang.',
        ],

        'academic' => [
            'prompt' => 'Describe this in one sentence.',
            'persona' => 'You are a strict academic professor. Be formal and use technical terms.',
        ],

        'comedian' => [
            'prompt' => 'Describe this in one sentence as a stand-up joke.',
            'persona' => 'You are a stand-up comedian. Be witty and observational.',
        ],
    ];
}
