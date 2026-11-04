<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_check_rejects_insecure_configuration_without_leaking_secrets(): void
    {
        config(['app.debug' => true, 'app.url' => 'http://localhost', 'session.secure' => false, 'app.backup_directory' => null]);
        $this->artisan('motel:production-check')->expectsOutput('FAIL Debug disabled')->expectsOutput('FAIL HTTPS application URL')->expectsOutput('FAIL Secure session cookie')->expectsOutput('FAIL Backup directory configured')->assertFailed();
    }
}
