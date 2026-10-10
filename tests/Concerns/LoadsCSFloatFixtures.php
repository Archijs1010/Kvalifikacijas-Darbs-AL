<?php

namespace Tests\Concerns;

use RuntimeException;

trait LoadsCSFloatFixtures
{
    protected function csfloatSalesFixture(): array
    {
        $path = __DIR__.'/../Fixtures/csfloat_sales.json';

        if (! is_file($path)) {
            throw new RuntimeException("Missing CSFloat fixture at [{$path}].");
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("CSFloat fixture at [{$path}] is not valid JSON.");
        }

        return $decoded;
    }

    protected function csfloatSaleEntry(int $index = 0): array
    {
        $sales = $this->csfloatSalesFixture();

        if (! isset($sales[$index])) {
            throw new RuntimeException("No CSFloat fixture entry at index {$index}.");
        }

        return $sales[$index];
    }
}
