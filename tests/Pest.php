<?php

use CoderStudios\DoInterview\Brief\InterviewBrief;
use CoderStudios\DoInterview\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

function spikeBrief(): InterviewBrief
{
    return InterviewBrief::fromJsonFile(__DIR__.'/../resources/examples/scenarios/sample-apprenticeship.json');
}
