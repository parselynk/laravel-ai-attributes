<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GenerateAiAttributeJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Model $model,
        public string $attribute,
        public ?string $personaOverride = null,
    ) {}

    public function handle(): void
    {
        if ($this->personaOverride !== null && method_exists($this->model, 'aiPersona')) {
            $this->model->aiPersona($this->personaOverride);
        }

        $this->model->generateAiAttribute($this->attribute);
    }

    public function uniqueId(): string
    {
        return sprintf(
            '%s:%s:%s:%s',
            $this->model::class,
            (string) $this->model->getKey(),
            $this->attribute,
            $this->personaOverride ?? '',
        );
    }
}
