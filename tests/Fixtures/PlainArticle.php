<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent model WITHOUT the HasAIAttributes trait — used to test that the
 * regenerate command refuses to run against models that aren't AI-enabled.
 */
class PlainArticle extends Model
{
    protected $table = 'articles';

    protected $guarded = [];
}
