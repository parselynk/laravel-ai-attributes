<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Parselynk\AiAttributes\Concerns\HasAIAttributes;

class TestArticle extends Model
{
    use HasAIAttributes;

    protected $guarded = [];

    protected $aiAttributes = [
        'summary' => 'Summarize this in 2 sentences',
        'tags' => 'Return 3-5 topic tags as JSON array',
    ];
}
