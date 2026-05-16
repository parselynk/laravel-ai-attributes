<?php

declare(strict_types=1);

namespace Parselynk\AiAttributes\Console;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Parselynk\AiAttributes\Concerns\HasAIAttributes;
use ReflectionClass;

class RegenerateAttributesCommand extends Command
{
    protected $signature = 'ai:regenerate
        {model : Fully-qualified Eloquent model class (e.g. "App\\Models\\Article")}
        {--attribute=* : Regenerate only these attributes (default: every key in $aiAttributes)}
        {--queue : Dispatch to the queue instead of generating synchronously}
        {--chunk=100 : Database chunk size}';

    protected $description = 'Clear and regenerate AI attribute caches for every record of a model.';

    public function handle(): int
    {
        $modelClass = (string) $this->argument('model');

        if (! class_exists($modelClass)) {
            $this->error("Model class [{$modelClass}] does not exist.");

            return self::FAILURE;
        }

        if (! is_subclass_of($modelClass, Model::class)) {
            $this->error("Model [{$modelClass}] does not extend Eloquent Model.");

            return self::FAILURE;
        }

        if (! in_array(HasAIAttributes::class, class_uses_recursive($modelClass), true)) {
            $this->error("Model [{$modelClass}] does not use the HasAIAttributes trait.");

            return self::FAILURE;
        }

        $declared = array_keys((array) (
            (new ReflectionClass($modelClass))
                ->getDefaultProperties()['aiAttributes'] ?? []
        ));

        $requested = (array) $this->option('attribute');
        $attributes = ! empty($requested) ? $requested : $declared;

        if (empty($attributes)) {
            $this->error("No AI attributes are defined on [{$modelClass}].");

            return self::FAILURE;
        }

        $unknown = array_diff($attributes, $declared);
        if (! empty($unknown)) {
            $this->error('Unknown attributes: '.implode(', ', $unknown));

            return self::FAILURE;
        }

        $useQueue = (bool) $this->option('queue');
        $chunk = (int) $this->option('chunk');

        $this->info(sprintf(
            'Regenerating %d attribute(s) for %s%s.',
            count($attributes),
            $modelClass,
            $useQueue ? ' (via queue)' : '',
        ));

        $count = 0;

        $modelClass::query()->chunkById($chunk, function ($records) use (&$count, $attributes, $useQueue) {
            foreach ($records as $record) {
                foreach ($attributes as $attribute) {
                    $record->forgetAiAttribute($attribute);

                    if ($useQueue) {
                        $record->generateAiAttributeAsync($attribute);
                    } else {
                        $record->generateAiAttribute($attribute);
                    }

                    $count++;
                }
            }
        });

        $this->info("Done. Processed {$count} attribute generation(s).");

        return self::SUCCESS;
    }
}
