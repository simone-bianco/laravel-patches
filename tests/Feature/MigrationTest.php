<?php

namespace SimoneBianco\Patches\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use SimoneBianco\Patches\Tests\TestCase;

class MigrationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_runs_the_patches_table_migration()
    {
        $this->assertTrue(Schema::hasTable('sb_patches'));

        $this->assertTrue(Schema::hasColumns('sb_patches', [
            'id',
            'patch',
            'batch',
            'created_at',
            'updated_at'
        ]));
    }
}
