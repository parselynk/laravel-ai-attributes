<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Parselynk\AiAttributes\Concerns\HasAIAttributes;

class CachedArticle extends Model
{
    use HasAIAttributes;

    protected $guarded = [];

    protected $aiAttributes = [
        'summary' => 'Summarize this',
    ];
}
