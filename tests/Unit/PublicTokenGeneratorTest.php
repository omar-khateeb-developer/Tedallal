<?php

namespace Tests\Unit;

use App\Services\TokenGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicTokenGeneratorTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_unique_tokens(): void
    {
        $generator = app(TokenGenerator::class);
        $a = $generator->generatePublicToken();
        $b = $generator->generatePublicToken();

        $this->assertNotSame($a, $b);
        $this->assertGreaterThan(20, strlen($a));
    }
}
