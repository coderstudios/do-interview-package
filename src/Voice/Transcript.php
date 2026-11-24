<?php

namespace CoderStudios\DoInterview\Voice;

final readonly class Transcript
{
    /**
     * @param  list<array{speaker: 'user'|'interviewer', text: string, start_ms: int|null, end_ms: int|null}>  $turns
     */
    public function __construct(
        public array $turns,
    ) {}
}
