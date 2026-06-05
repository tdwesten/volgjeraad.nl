<?php

use App\Enums\VideoStatus;

test('video status has the expected string values', function (): void {
    expect(VideoStatus::Pending->value)->toBe('pending');
    expect(VideoStatus::NeedsConfirmation->value)->toBe('needs_confirmation');
    expect(VideoStatus::Matched->value)->toBe('matched');
    expect(VideoStatus::Transcribed->value)->toBe('transcribed');
    expect(VideoStatus::NotFound->value)->toBe('not_found');
    expect(VideoStatus::Failed->value)->toBe('failed');
});
