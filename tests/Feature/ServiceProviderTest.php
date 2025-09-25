<?php

namespace SimoneBianco\Patches\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use SimoneBianco\Patches\Patches;
use SimoneBianco\Patches\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_the_patches_singleton()
    {
        $patchesInstance = $this->app->make('patches');

        $this->assertInstanceOf(Patches::class, $patchesInstance);
    }
}
