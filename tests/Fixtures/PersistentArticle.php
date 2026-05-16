<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Parselynk\AiAttributes\Concerns\HasAIAttributes;

class PersistentArticle extends Model
{
    use HasAIAttributes;

    protected $table = 'articles';

    protected $guarded = [];

    protected $aiAttributes = [
        'summary' => 'Summarize in one sentence.',
    ];
}
