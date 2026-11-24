<?php

namespace CoderStudios\DoInterview\Brief;

use InvalidArgumentException;

/**
 * The product's own, provider-neutral description of one interview (build.md section 4).
 * Each voice driver renders it into that provider's instructions.
 */
final readonly class InterviewBrief
{
    /**
     * @param  array{name: string, style: string}  $persona
     * @param  list<array{id: string, text: string, probes: list<string>, rubric_tags: list<string>}>  $questions
     * @param  list<array{tag: string, levels: array<string, string>}>  $rubric
     * @param  list<string>  $boundaries
     * @param  list<array{title: string, url: string}>  $sources
     */
    public function __construct(
        public string $scenario,
        public string $organisation,
        public string $role,
        public array $persona,
        public string $disclosure,
        public int $durationMinutes,
        public array $questions,
        public array $rubric = [],
        public array $boundaries = [],
        public array $sources = [],
    ) {
        if ($this->durationMinutes < 1) {
            throw new InvalidArgumentException('An interview brief needs a duration of at least one minute.');
        }
        if ($this->questions === []) {
            throw new InvalidArgumentException('An interview brief needs at least one question.');
        }
        if (trim($this->disclosure) === '') {
            throw new InvalidArgumentException('An interview brief needs an AI disclosure (build.md design principle 5).');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        foreach (['scenario', 'organisation', 'role', 'persona', 'disclosure', 'duration_minutes', 'questions'] as $key) {
            if (! array_key_exists($key, $data)) {
                throw new InvalidArgumentException("An interview brief is missing \"{$key}\".");
            }
        }

        $questions = [];
        foreach ((array) $data['questions'] as $i => $q) {
            if (! is_array($q) || ! isset($q['text']) || ! is_string($q['text'])) {
                throw new InvalidArgumentException("Question {$i} in the interview brief has no text.");
            }
            $questions[] = [
                'id' => (string) ($q['id'] ?? 'q'.($i + 1)),
                'text' => $q['text'],
                'probes' => array_values(array_map('strval', (array) ($q['probes'] ?? []))),
                'rubric_tags' => array_values(array_map('strval', (array) ($q['rubric_tags'] ?? []))),
            ];
        }

        $persona = (array) $data['persona'];

        return new self(
            scenario: (string) $data['scenario'],
            organisation: (string) $data['organisation'],
            role: (string) $data['role'],
            persona: ['name' => (string) ($persona['name'] ?? 'the interviewer'), 'style' => (string) ($persona['style'] ?? 'friendly')],
            disclosure: (string) $data['disclosure'],
            durationMinutes: (int) $data['duration_minutes'],
            questions: $questions,
            rubric: array_values((array) ($data['rubric'] ?? [])),
            boundaries: array_values(array_map('strval', (array) ($data['boundaries'] ?? []))),
            sources: array_values((array) ($data['sources'] ?? [])),
        );
    }

    public static function fromJsonFile(string $path): self
    {
        if (! is_file($path)) {
            throw new InvalidArgumentException("No interview brief at {$path}.");
        }

        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($data)) {
            throw new InvalidArgumentException("The interview brief at {$path} is not a JSON object.");
        }

        return self::fromArray($data);
    }
}
