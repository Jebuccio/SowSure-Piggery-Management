<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/validation.php';

$passed = 0;
$failed = 0;

function test(string $name, callable $assertion): void
{
    global $passed, $failed;
    try {
        if (!$assertion()) {
            throw new RuntimeException('Assertion returned false.');
        }
        echo "PASS: {$name}\n";
        $passed++;
    } catch (Throwable $exception) {
        echo "FAIL: {$name} — {$exception->getMessage()}\n";
        $failed++;
    }
}

test('standard 114-day farrowing calculation', fn (): bool =>
    expected_farrowing_date('2024-01-01') === '2024-04-24'
);
test('calculation crosses a year boundary', fn (): bool =>
    expected_farrowing_date('2024-11-01') === '2025-02-23'
);
test('calculation handles a leap year', fn (): bool =>
    expected_farrowing_date('2023-12-31') === '2024-04-23'
);
test('calculation accepts leap day', fn (): bool =>
    expected_farrowing_date('2024-02-29') === '2024-06-22'
);
test('password policy accepts all required character classes', fn (): bool =>
    valid_password('FarmCare9!')
);
test('password policy rejects missing uppercase', fn (): bool =>
    !valid_password('farmcare9!')
);
test('password policy rejects missing digit', fn (): bool =>
    !valid_password('FarmCare!')
);
test('password policy rejects missing special character', fn (): bool =>
    !valid_password('FarmCare9')
);
test('username normalization format accepts documented characters', fn (): bool =>
    valid_username('mara.santos_2')
);
test('username format rejects spaces and uppercase', fn (): bool =>
    !valid_username('Mara Santos')
);
test('ISO date validation rejects impossible dates', fn (): bool =>
    !valid_iso_date('2025-02-30')
);

echo "\n{$passed} passed, {$failed} failed.\n";
exit($failed === 0 ? 0 : 1);
