<?php

namespace SimoneBianco\Patches\Tests\Feature;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use SimoneBianco\Patches\Tests\TestCase;

class MakePatchCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(database_path('patches'));
        File::makeDirectory(database_path('patches'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(database_path('patches'));
        parent::tearDown();
    }

    #[Test]
    public function it_can_create_a_new_patch_file()
    {
        $patchPath = database_path('patches');

        $this->artisan('make:patch', ['name' => 'MyFirstPatch'])
            ->assertExitCode(0);

        $filesCount = $this->filePartialMatchesCount($patchPath);
        $this->assertTrue($filesCount === 1, 'Patch file not found in patches directory');
    }
}
