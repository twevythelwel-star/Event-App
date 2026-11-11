<?php

namespace Tests\Feature;

use App\Notifications\TransferSubmittedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

/**
 * Production infrastructure: the configuration guard (strict at runtime, silent during build steps), security
 * headers, trusted proxies and the notifications queue name.
 */
class ProductionInfrastructureTest extends TestCase
{
    use RefreshDatabase;

    /** A complete, safe production environment for a child artisan process (values are placeholders). */
    private function productionEnv(array $overrides = []): array
    {
        return $overrides + [
            'APP_ENV' => 'production', 'APP_DEBUG' => 'false', 'APP_URL' => 'https://events.example.test',
            'APP_KEY' => 'base64:' . base64_encode(str_repeat('k', 32)),
            'SESSION_SECURE_COOKIE' => 'true', 'SESSION_DRIVER' => 'database',
            'DB_CONNECTION' => 'mysql', 'DB_DATABASE' => 'eventbite', 'DB_USERNAME' => 'eventbite', 'DB_PASSWORD' => 'placeholder',
            'MAIL_MAILER' => 'smtp', 'QUEUE_CONNECTION' => 'database', 'CACHE_STORE' => 'database',
            'LOG_CHANNEL' => 'stack', 'LOG_STACK' => 'daily,slack', 'LOG_SLACK_WEBHOOK_URL' => 'https://hooks.slack.example.test/x',
            'MEDIA_DISK' => 's3', 'HOST_ID_STORAGE_DISK' => 's3', 'AWS_BUCKET' => 'eventbite-media',
            'AWS_DEFAULT_REGION' => 'us-east-1', 'AWS_ACCESS_KEY_ID' => 'placeholder', 'AWS_SECRET_ACCESS_KEY' => 'placeholder',
        ];
    }

    private function runArtisan(string $command, array $env)
    {
        return Process::path(base_path())->env($env)->timeout(120)->run([PHP_BINARY, 'artisan', $command]);
    }

    public function test_build_steps_run_without_a_production_environment_but_runtime_commands_do_not(): void
    {
        // CI and Laravel Cloud's build run composer's package:discover before the real environment exists.
        $unsafe = $this->productionEnv(['APP_URL' => 'http://events.example.test']);
        $this->assertTrue($this->runArtisan('package:discover', $unsafe)->successful(), 'build steps are not blocked');

        $run = $this->runArtisan('env', $unsafe);
        $this->assertFalse($run->successful(), 'a runtime command with unsafe settings is refused');
        $this->assertStringContainsString('APP_URL to use HTTPS', $run->output() . $run->errorOutput());
    }

    public function test_a_safe_production_configuration_with_bucket_storage_boots(): void
    {
        $run = $this->runArtisan('env', $this->productionEnv());
        $this->assertTrue($run->successful(), $run->output() . $run->errorOutput());

        // Media on an S3 disk with no bucket is refused (the files would be lost).
        $run = $this->runArtisan('env', $this->productionEnv(['AWS_BUCKET' => '']));
        $this->assertFalse($run->successful());
        $this->assertStringContainsString("storage disk 's3'", $run->output() . $run->errorOutput());
    }

    public function test_responses_carry_security_headers(): void
    {
        $response = $this->get('/')->assertOk();
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('Permissions-Policy');
        $response->assertHeaderMissing('Strict-Transport-Security'); // never on plain HTTP or in testing
    }

    public function test_https_is_recognised_behind_the_load_balancer(): void
    {
        $this->app['env'] = 'staging'; // HSTS is skipped only in local and testing
        $response = $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.5'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
            ->get('/');
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_the_notifications_queue_name_is_configurable(): void
    {
        $this->assertSame('notifications', (new TransferSubmittedNotification(['orderNumber' => 'A1']))->queue);

        // One managed queue on Laravel Cloud: an empty NOTIFICATIONS_QUEUE sends messages to the default queue.
        config(['queue.names.notifications' => null]);
        $this->assertNull((new TransferSubmittedNotification(['orderNumber' => 'A2']))->queue);
    }
}
