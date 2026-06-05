<?php

use App\Enums\MeetingType;

test('fromCommitteeName returns Council for raadsvergadering match', function (): void {
    expect(MeetingType::fromCommitteeName('Besluitvormende raadsvergadering', 'raadsvergadering'))
        ->toBe(MeetingType::Council);
});

test('fromCommitteeName returns Other for non-matching name', function (): void {
    expect(MeetingType::fromCommitteeName('Overige evenementen', 'raadsvergadering'))
        ->toBe(MeetingType::Other);
});

test('fromCommitteeName returns Other for null', function (): void {
    expect(MeetingType::fromCommitteeName(null, 'raadsvergadering'))
        ->toBe(MeetingType::Other);
});

test('fromCommitteeName returns Committee for commissie name', function (): void {
    expect(MeetingType::fromCommitteeName('Commissie Sociaal Domein', 'raadsvergadering'))
        ->toBe(MeetingType::Committee);
});

test('fromCommitteeName is case-insensitive', function (): void {
    expect(MeetingType::fromCommitteeName('RAADSVERGADERING', 'raadsvergadering'))
        ->toBe(MeetingType::Council);
});
