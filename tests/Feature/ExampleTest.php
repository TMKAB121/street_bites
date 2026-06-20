<?php

/*
 | Pest feature test. Files in tests/Feature ARE bound to Tests\TestCase
 | (see tests/Pest.php), so the full Laravel app is booted and helpers like
 | $this->get() are available — same capabilities as a PHPUnit feature test,
 | far less boilerplate.
 */

it('returns a successful response for the home page', function (): void {
    $this->get('/')->assertOk();
});
