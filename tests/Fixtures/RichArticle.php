<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Parselynk\AiAttributes\Concerns\HasAIAttributes;

class RichArticle extends Model
{
    use HasAIAttributes;

    protected $guarded = [];

    protected $aiAttributes = [
        'summary' => 'Summarize in 2 sentences.',

        'tags' => [
            'prompt' => 'Return 3-5 topic tags as a JSON array of strings.',
            'format' => 'json',
        ],

        'sentiment' => [
            'prompt' => 'Rate sentiment from -100 (negative) to 100 (positive). Return only the number.',
            'format' => 'number',
        ],

        'is_clickbait' => [
            'prompt' => 'Is the title clickbait? Answer "yes" or "no".',
            'format' => 'bool',
        ],

        'meta_description' => [
            'prompt' => 'Write an SEO meta description in under 160 chars.',
            'persona' => 'You are an SEO expert.',
        ],

        'translated' => [
            'prompt' => 'Translate the body to French.',
            'driver' => 'openai',
            'model' => 'gpt-4o',
            'max_tokens' => 500,
        ],
    ];
}
