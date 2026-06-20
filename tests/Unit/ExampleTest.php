<?php

declare(strict_types=1);

/*
| Pest unit test. Files in tests/Unit are NOT bound to the Laravel TestCase
| (see tests/Pest.php), so they stay fast and framework-free — pure PHP logic.
|
| `test()` and its alias `it()` define a test; `expect()` makes assertions.
*/
test('true is true', function (): void {
    expect(true)->toBeTrue();
});
