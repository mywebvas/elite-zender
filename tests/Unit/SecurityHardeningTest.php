<?php

use App\Support\CsvSanitizer;
use App\Support\SafeHost;

it('blocks cloud metadata ip in all environments', function (): void {
    expect(SafeHost::isSafe('169.254.169.254'))->toBeFalse()
        ->and(SafeHost::isSafe('169.254.1.1'))->toBeFalse()
        ->and(SafeHost::isSafe('metadata.google.internal'))->toBeFalse()
        ->and(SafeHost::isSafe('instance-data'))->toBeFalse()
        ->and(SafeHost::isSafe('fd00:ec2::254'))->toBeFalse();
});

it('blocks injection characters and options in hostnames', function (): void {
    expect(SafeHost::isSafe('-oProxyCommand=calc.exe'))->toBeFalse()
        ->and(SafeHost::isSafe('smtp.example.com; ls -la'))->toBeFalse()
        ->and(SafeHost::isSafe('smtp.example.com/test'))->toBeFalse()
        ->and(SafeHost::isSafe('smtp.example.com\test'))->toBeFalse()
        ->and(SafeHost::isSafe('smtp.example.com:25'))->toBeFalse()
        ->and(SafeHost::isSafe('smtp.example.com `id`'))->toBeFalse()
        ->and(SafeHost::isSafe(''))->toBeFalse();
});

it('accepts legitimate public hostnames', function (): void {
    expect(SafeHost::isSafe('smtp.sendgrid.net'))->toBeTrue()
        ->and(SafeHost::isSafe('smtp.mailgun.org'))->toBeTrue()
        ->and(SafeHost::isSafe('mail.example.com'))->toBeTrue()
        ->and(SafeHost::isSafe('smtp-relay.gmail.com'))->toBeTrue();
});

it('sanitizes formula injection triggers in csv rows', function (): void {
    $row = [
        '=cmd|"/C calc"!A0',
        '+1234567890',
        '-5+2',
        '@SUM(A1:A10)',
        "\tmalicious_tab",
        '%username%',
        'Normal text',
        123,
        null,
    ];

    $sanitized = CsvSanitizer::sanitizeRow($row);

    expect($sanitized[0])->toBe('\'=cmd|"/C calc"!A0')
        ->and($sanitized[1])->toBe('\'+1234567890')
        ->and($sanitized[2])->toBe('\'-5+2')
        ->and($sanitized[3])->toBe('\'@SUM(A1:A10)')
        ->and($sanitized[4])->toBe("'\tmalicious_tab")
        ->and($sanitized[5])->toBe('\'%username%')
        ->and($sanitized[6])->toBe('Normal text')
        ->and($sanitized[7])->toBe(123)
        ->and($sanitized[8])->toBeNull();
});
