<?php

use CoderStudios\DoInterview\Brief\InterviewBrief;
use CoderStudios\DoInterview\Brief\Renderers\GptLiveBriefRenderer;

it('loads the spike brief', function () {
    $brief = spikeBrief();

    expect($brief->scenario)->toBe('apprenticeship_interview')
        ->and($brief->questions)->toHaveCount(4)
        ->and($brief->questions[0]['probes'])->not->toBeEmpty()
        ->and($brief->durationMinutes)->toBe(5);
});

it('rejects a brief with a missing field', function () {
    $data = json_decode(file_get_contents(__DIR__.'/../../resources/examples/scenarios/sample-apprenticeship.json'), true);
    unset($data['disclosure']);

    InterviewBrief::fromArray($data);
})->throws(InvalidArgumentException::class, 'missing "disclosure"');

it('rejects a brief without questions', function () {
    $data = json_decode(file_get_contents(__DIR__.'/../../resources/examples/scenarios/sample-apprenticeship.json'), true);
    $data['questions'] = [];

    InterviewBrief::fromArray($data);
})->throws(InvalidArgumentException::class, 'at least one question');

it('rejects a blank AI disclosure', function () {
    $data = json_decode(file_get_contents(__DIR__.'/../../resources/examples/scenarios/sample-apprenticeship.json'), true);
    $data['disclosure'] = '  ';

    InterviewBrief::fromArray($data);
})->throws(InvalidArgumentException::class, 'AI disclosure');

it('renders GPT-Live instructions that carry the whole brief', function () {
    $brief = spikeBrief();
    $instructions = app(GptLiveBriefRenderer::class)->render($brief);

    expect($instructions)
        ->toContain($brief->disclosure)
        ->toContain('British English')
        ->toContain('Never claim to be human')
        ->toContain('stop the interview');

    foreach ($brief->questions as $question) {
        expect($instructions)->toContain($question['text']);
        foreach ($question['probes'] as $probe) {
            expect($instructions)->toContain($probe);
        }
    }
});

it('keeps the marking scheme out of the interviewer instructions', function () {
    $instructions = app(GptLiveBriefRenderer::class)->render(spikeBrief());

    expect($instructions)->not->toContain('Specific, personal reasons linked to the role');
});

it('stays well inside the GPT-Live instruction limit', function () {
    $instructions = app(GptLiveBriefRenderer::class)->render(spikeBrief());

    // About four characters a token; the limit is 16,384 tokens.
    expect(strlen($instructions) / 4)->toBeLessThan(16384 / 4);
});
