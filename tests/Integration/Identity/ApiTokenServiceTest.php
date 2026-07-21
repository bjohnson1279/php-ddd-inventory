<?php

declare(strict_types=1);

namespace Tests\Integration\Identity;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Identity\ApiTokenService;
use Illuminate\Database\Capsule\Manager as DB;

require_once __DIR__ . '/../bootstrap.php';

/** @group integration */
final class ApiTokenServiceTest extends TestCase
{
    private string $userId;
    private string $tenantId = 't1';

    protected function setUp(): void
    {
        $this->userId = uuidv4();

        DB::table('tenants')->insertOrIgnore(['id' => $this->tenantId, 'name' => 'Test Tenant']);
        DB::table('users')->insertOrIgnore([
            'id' => $this->userId,
            'tenant_id' => $this->tenantId,
            'email' => "test_{$this->userId}@example.com",
            'password_hash' => 'hash',
            'name' => 'Test User'
        ]);
    }

    public function testIssueAndValidateToken(): void
    {
        $service = new ApiTokenService();
        $token = $service->issue($this->userId, $this->tenantId);

        $this->assertNotEmpty($token);

        $data = $service->validate($token);
        $this->assertNotNull($data);
        $this->assertEquals($this->userId, $data->user_id);
        $this->assertEquals($this->tenantId, $data->tenant_id);
    }

    public function testInvalidTokenReturnsNull(): void
    {
        $service = new ApiTokenService();
        $this->assertNull($service->validate('some-fake-token'));
    }

    public function testRevokeAllDeletesTokens(): void
    {
        $service = new ApiTokenService();
        $token = $service->issue($this->userId, $this->tenantId);

        $service->revokeAll($this->userId);

        $this->assertNull($service->validate($token));
    }
}
