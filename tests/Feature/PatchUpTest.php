<?php

namespace Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;
use SimoneBianco\Patches\Facades\Patches;
use SimoneBianco\Patches\Tests\TestCase;

#[RunTestsInSeparateProcesses]
class PatchUpTest extends TestCase
{
    protected string $migrationFileName = '2024_01_01_000000_seed_admin_user.php';

    protected function putPatchContent(string $path): void
    {
        if (!File::isDirectory($path)) {
            File::makeDirectory($path, 0755, true);
        }

        file_put_contents("$path\\$this->migrationFileName", <<<PHP
<?php

namespace Database\Patches;

class SeedAdminUser
{
    /**
     * Run the data patch.
     *
     * @return void
     */
    public function up(): void
    {
        \Illuminate\Support\Facades\Cache::add('admin_user', [
            'name' => 'Admin',
            'email' => 'admin@email.it',
            'password' => bcrypt('password'),
        ], 3600);
    }

    /**
     * Reverse the data patch.
     *
     * @return void
     */
    public function down(): void
    {
        \Illuminate\Support\Facades\Cache::forget('admin_user');
    }
}

PHP);
    }

    protected function setUp(): void
    {
        parent::setUp();

        File::deleteDirectory(database_path('patches'));
        File::makeDirectory(database_path('patches'));

        Cache::flush();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(database_path('patches'));

        Cache::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_can_run_single_file()
    {
        $patchPath = database_path('patches');
        $this->putPatchContent($patchPath);

        $filesCount = $this->filePartialMatchesCount($patchPath, $this->migrationFileName);
        $this->assertTrue($filesCount === 1, 'Patch file not found in patches directory');

        Patches::runPatches();

        $this->assertTrue(Cache::has('admin_user'), 'Cache key admin_user not found after patching');
        $this->assertEquals('Admin', data_get(Cache::get('admin_user'), 'name'), 'Cache key admin_user has wrong name value');
    }

    #[Test]
    public function it_can_rollback()
    {
        $patchPath = database_path('patches');
        $this->putPatchContent($patchPath);

        Patches::runPatches();
        Patches::rollback();

        $this->assertFalse(Cache::has('admin_user'), 'Cache key admin_user found after rollback');
    }

    #[Test]
    public function it_can_run_nested_file()
    {
        $patchPath = database_path('patches\users\admins');
        $this->putPatchContent($patchPath);

        $filesCount = $this->filePartialMatchesCount($patchPath, $this->migrationFileName);
        $this->assertTrue($filesCount === 1, 'Patch file not found in patches directory');

        Patches::runPatches();

        $this->assertTrue(Cache::has('admin_user'), 'Cache key admin_user not found after patching');
        $this->assertEquals('Admin', data_get(Cache::get('admin_user'), 'name'), 'Cache key admin_user has wrong name value');
    }

    #[Test]
    public function it_can_rollback_nested_file()
    {
        $patchPath = database_path('patches\users\admins');
        $this->putPatchContent($patchPath);

        Patches::runPatches();
        Patches::rollback();

        $this->assertFalse(Cache::has('admin_user'), 'Cache key admin_user found after rollback');
    }

    #[Test]
    public function it_can_use_up_callbacks()
    {
        $patchPath = database_path('patches');
        $this->putPatchContent($patchPath);

        Patches::runPatches(null,
            function () {
                Cache::put('before_up', true);
            },
            function () {
                Cache::put('after_up', true);
            });

        $this->assertTrue(Cache::has('admin_user'), 'Cache key admin_user not found after patching');
        $this->assertTrue(Cache::has('before_up'), 'Cache key before_up not found after up callback');
        $this->assertTrue(Cache::has('after_up'), 'Cache key after_up not found after up callback');
    }

    #[Test]
    public function it_can_use_down_callbacks()
    {
        $patchPath = database_path('patches');
        $this->putPatchContent($patchPath);

        Patches::runPatches();
        Patches::rollback([], null,
            function () {
                Cache::put('before_up', true);
            },
            function () {
                Cache::put('after_up', true);
            });

        $this->assertFalse(Cache::has('admin_user'), 'Cache key admin_user found after rollback');
        $this->assertTrue(Cache::has('before_up'), 'Cache key before_up not found after up callback');
        $this->assertTrue(Cache::has('after_up'), 'Cache key after_up not found after up callback');
    }
}
