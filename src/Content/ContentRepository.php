<?php

namespace CoderStudios\DoInterview\Content;

use CoderStudios\DoInterview\Brief\InterviewBrief;
use InvalidArgumentException;

/**
 * Finds the host's scenarios. Scenarios and prompt templates are the product's own content and
 * live in the host app, not in this package: each path in `do-interview.content.paths` is
 * searched in order, and the package's examples come last so a fresh install still runs.
 *
 * Layout under each path:
 *   scenarios/{name}.json                 an InterviewBrief
 *   prompts/{driver}/{template}.blade.php a prompt template (see GptLiveBriefRenderer)
 */
final class ContentRepository
{
    /** @var list<string> */
    private array $paths;

    /**
     * @param  list<string>  $paths
     */
    public function __construct(array $paths)
    {
        $this->paths = array_values(array_unique(array_filter(array_map(
            fn (mixed $p) => rtrim((string) $p, '/'),
            $paths,
        ))));
    }

    /**
     * @return list<string>
     */
    public function paths(): array
    {
        return $this->paths;
    }

    /**
     * @return list<string>
     */
    public function promptPaths(): array
    {
        return array_map(fn (string $p) => $p.'/prompts', $this->paths);
    }

    public function scenario(string $name): InterviewBrief
    {
        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $name) !== 1) {
            throw new InvalidArgumentException("\"{$name}\" is not a valid scenario name: use lower-case letters, digits, - and _.");
        }

        foreach ($this->paths as $path) {
            $file = "{$path}/scenarios/{$name}.json";
            if (is_file($file)) {
                return InterviewBrief::fromJsonFile($file);
            }
        }

        throw new InvalidArgumentException("No scenario called \"{$name}\" in: ".implode(', ', $this->paths).'.');
    }

    /**
     * Every scenario name, the first path winning where two share a name.
     *
     * @return list<string>
     */
    public function scenarioNames(): array
    {
        $names = [];
        foreach ($this->paths as $path) {
            foreach (glob("{$path}/scenarios/*.json") ?: [] as $file) {
                $names[basename($file, '.json')] = true;
            }
        }
        ksort($names);

        return array_keys($names);
    }
}
