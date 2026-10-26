<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * The page needs nothing that web/nginx/snippets/security-headers.conf blocks: no inline
     * styles, inline scripts, inline event handlers or resources from other sites.
     */
    protected function assertCspSafe(string $html): void
    {
        $this->assertDoesNotMatchRegularExpression('/<[^>]+\sstyle\s*=/i', $html);
        $this->assertDoesNotMatchRegularExpression('/<[^>]+\son[a-z]+\s*=/i', $html);
        $this->assertDoesNotMatchRegularExpression('/<script(?![^>]*\ssrc=)[^>]*>/i', $html);
        $this->assertDoesNotMatchRegularExpression('#\s(src|href)="(https?:)?//(?!localhost[/:"\#?])#i', $html);
    }
}
