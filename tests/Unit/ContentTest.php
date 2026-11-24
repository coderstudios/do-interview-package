<?php

use CoderStudios\DoInterview\Brief\Renderers\GptLiveBriefRenderer;
use CoderStudios\DoInterview\Content\ContentRepository;
use Illuminate\Support\Facades\File;

/** A host content folder in a temp dir, put first in the content paths. */
function hostContent(): string
{
    $dir = sys_get_temp_dir().'/do-interview-content-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists("{$dir}/scenarios");
    File::ensureDirectoryExists("{$dir}/prompts/gpt-live");

    config()->set('do-interview.content.paths', [$dir, __DIR__.'/../../resources/examples']);
    app()->forgetInstance(ContentRepository::class);
    app('view')->replaceNamespace('do-interview-prompts', app(ContentRepository::class)->promptPaths());

    return $dir;
}

afterEach(function () {
    foreach (glob(sys_get_temp_dir().'/do-interview-content-*') ?: [] as $dir) {
        File::deleteDirectory($dir);
    }
});

it('falls back to the package example scenario', function () {
    expect(app(ContentRepository::class)->scenario('sample-apprenticeship')->scenario)->toBe('apprenticeship_interview')
        ->and(app(ContentRepository::class)->scenarioNames())->toContain('sample-apprenticeship');
});

it('prefers the host scenario of the same name', function () {
    $dir = hostContent();
    $data = json_decode(file_get_contents(__DIR__.'/../../resources/examples/scenarios/sample-apprenticeship.json'), true);
    $data['organisation'] = 'Host Organisation';
    file_put_contents("{$dir}/scenarios/sample-apprenticeship.json", json_encode($data));

    expect(app(ContentRepository::class)->scenario('sample-apprenticeship')->organisation)->toBe('Host Organisation');
});

it('rejects scenario names that could leave the content folder', function (string $name) {
    app(ContentRepository::class)->scenario($name);
})->with(['../secrets', 'Sample', 'a/b', ''])->throws(InvalidArgumentException::class, 'not a valid scenario name');

it('says where it looked for a missing scenario', function () {
    app(ContentRepository::class)->scenario('no-such-scenario');
})->throws(InvalidArgumentException::class, 'No scenario called "no-such-scenario"');

it('renders the example template without escaping or stray indentation', function () {
    $brief = spikeBrief();
    $instructions = app(GptLiveBriefRenderer::class)->render($brief);

    expect($instructions)
        ->toStartWith('# Role')
        ->toContain("I'm an AI interviewer")  // not &#039;
        ->not->toContain('&#')
        ->not->toContain('{!!')
        ->toContain("\n1. ".$brief->questions[0]['text'])
        ->toContain("\n   Possible follow-up: ".$brief->questions[0]['probes'][0]);
});

it('uses the host prompt template and still appends the safeguards', function () {
    $dir = hostContent();
    file_put_contents("{$dir}/prompts/gpt-live/interviewer.blade.php", "HOST PROMPT for {!! \$name !!}. Say: \"{!! \$brief->disclosure !!}\"\n");

    $instructions = app(GptLiveBriefRenderer::class)->render(spikeBrief());

    expect($instructions)
        ->toStartWith('HOST PROMPT for Sam.')
        ->not->toContain('# Role')
        ->toContain('# Safeguarding rules (always apply)')
        ->toContain('Never claim to be human')
        ->toContain('stop the interview')
        ->toContain('Stay in role as the interviewer');
});

it('refuses a host template that drops the AI disclosure', function () {
    $dir = hostContent();
    file_put_contents("{$dir}/prompts/gpt-live/interviewer.blade.php", "You are {!! \$name !!}, a human recruiter.\n");

    app(GptLiveBriefRenderer::class)->render(spikeBrief());
})->throws(RuntimeException::class, 'AI disclosure');
