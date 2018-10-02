<?php

namespace Seivad\Events\Tests;

class TestCase extends \PHPUnit\Framework\TestCase
{
    public function tearTown()
    {
        \Mockery::close();
    }
}
