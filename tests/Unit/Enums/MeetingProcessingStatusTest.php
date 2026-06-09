<?php

use App\Enums\MeetingProcessingStatus;

test('each case has a non-empty admin label and public message', function (MeetingProcessingStatus $status): void {
    expect($status->adminLabel())->toBeString()->not->toBe('');
    expect($status->publicMessage())->toBeString();
})->with(MeetingProcessingStatus::cases());

test('no_source explains the missing besluitenlijst publicly', function (): void {
    expect(MeetingProcessingStatus::NoSource->publicMessage())
        ->toContain('besluitenlijst');
});

test('published has no public message (summary is shown instead)', function (): void {
    expect(MeetingProcessingStatus::Published->publicMessage())->toBe('');
});

test('scheduled is hidden from the public list', function (): void {
    expect(MeetingProcessingStatus::Scheduled->isPubliclyVisible())->toBeFalse();
    expect(MeetingProcessingStatus::Processing->isPubliclyVisible())->toBeTrue();
});
