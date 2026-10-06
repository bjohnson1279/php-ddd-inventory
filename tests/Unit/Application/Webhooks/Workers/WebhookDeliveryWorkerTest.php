<?php

namespace Tests\Unit\Application\Webhooks\Workers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Webhooks\Workers\WebhookDeliveryWorker;
use InventoryApp\Infrastructure\Models\WebhookDeliveryModel;
use InventoryApp\Infrastructure\Models\WebhookSubscriptionModel;
use Illuminate\Database\Capsule\Manager as DB;

class WebhookDeliveryWorkerTest extends TestCase
{
    protected function setUp(): void
    {
        try { DB::connection(); } catch (\Throwable $e) {
            $capsule = new DB();
            $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
            $capsule->setAsGlobal();
            $capsule->bootEloquent();
        }

        DB::schema()->dropIfExists('webhook_deliveries');
        DB::schema()->dropIfExists('webhook_subscriptions');
        DB::schema()->dropIfExists('notifications');

        DB::schema()->create('webhook_deliveries', function ($table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('subscription_id');
            $table->string('event_type');
            $table->text('payload');
            $table->string('status');
            $table->integer('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->dateTime('next_attempt_at')->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->dateTime('created_at')->nullable();
        });

        DB::schema()->create('webhook_subscriptions', function ($table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('target_url');
            $table->string('secret');
            $table->text('event_types')->nullable();
            $table->boolean('is_active')->default(true);
            $table->dateTime('created_at')->nullable();
        });

        DB::schema()->create('notifications', function ($table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('title');
            $table->text('message');
            $table->string('type');
            $table->boolean('is_read')->default(false);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function testSsrfProtectionAndBackoff(): void
    {
        WebhookSubscriptionModel::create([
            'id' => 'sub-1', 'tenant_id' => 't-1', 'target_url' => 'http://127.0.0.1/', 'secret' => 'x', 'is_active' => true
        ]);
        WebhookDeliveryModel::create([
            'id' => 'del-1', 'tenant_id' => 't-1', 'subscription_id' => 'sub-1', 'event_type' => 'e',
            'payload' => '{}', 'status' => 'Pending', 'attempts' => 0, 'created_at' => new \DateTime()
        ]);

        ob_start();
        (new WebhookDeliveryWorker())->run(true);
        ob_end_clean();

        $delivery = WebhookDeliveryModel::find('del-1');
        $this->assertEquals(1, $delivery->attempts);
        $this->assertStringContainsString('private or reserved range', $delivery->last_error);
        $this->assertEquals('Pending', $delivery->status);
        $this->assertNotNull($delivery->next_attempt_at);
        $this->assertGreaterThan(new \DateTime(), $delivery->next_attempt_at);
    }

    public function testInactiveSubscriptionFailure(): void
    {
        WebhookSubscriptionModel::create([
            'id' => 'sub-2', 'tenant_id' => 't-1', 'target_url' => 'http://example.com/', 'secret' => 'x', 'is_active' => false
        ]);
        WebhookDeliveryModel::create([
            'id' => 'del-2', 'tenant_id' => 't-1', 'subscription_id' => 'sub-2', 'event_type' => 'e',
            'payload' => '{}', 'status' => 'Pending', 'attempts' => 4, 'created_at' => new \DateTime()
        ]);

        ob_start();
        (new WebhookDeliveryWorker())->run(true);
        ob_end_clean();

        $delivery = WebhookDeliveryModel::find('del-2');
        $this->assertEquals(5, $delivery->attempts);
        $this->assertEquals('Failed', $delivery->status);
        $this->assertStringContainsString('Subscription not found or inactive', $delivery->last_error);
    }

    public function testBatchUpsertSuccessfulDeliveries(): void
    {
        WebhookDeliveryModel::create([
            'id' => 'del-3', 'tenant_id' => 't-1', 'subscription_id' => 'sub-1', 'event_type' => 'e',
            'payload' => '{}', 'status' => 'Pending', 'attempts' => 0, 'created_at' => new \DateTime()
        ]);
        WebhookDeliveryModel::create([
            'id' => 'del-4', 'tenant_id' => 't-1', 'subscription_id' => 'sub-1', 'event_type' => 'e',
            'payload' => '{}', 'status' => 'Pending', 'attempts' => 1, 'created_at' => new \DateTime()
        ]);

        $deliveriesToUpsert = [
            [
                'id' => 'del-3',
                'tenant_id' => 't-1',
                'subscription_id' => 'sub-1',
                'event_type' => 'e',
                'payload' => '{}',
                'status' => 'Success',
                'attempts' => 1,
                'last_error' => null,
                'next_attempt_at' => null,
                'processed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ],
            [
                'id' => 'del-4',
                'tenant_id' => 't-1',
                'subscription_id' => 'sub-1',
                'event_type' => 'e',
                'payload' => '{}',
                'status' => 'Success',
                'attempts' => 2,
                'last_error' => null,
                'next_attempt_at' => null,
                'processed_at' => (new \DateTime())->format('Y-m-d H:i:s'),
                'created_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]
        ];

        WebhookDeliveryModel::upsert(
            $deliveriesToUpsert,
            ['id'],
            ['status', 'attempts', 'processed_at']
        );

        $d3 = WebhookDeliveryModel::find('del-3');
        $d4 = WebhookDeliveryModel::find('del-4');

        $this->assertEquals('Success', $d3->status);
        $this->assertEquals(1, $d3->attempts);
        $this->assertEquals('Success', $d4->status);
        $this->assertEquals(2, $d4->attempts);
    }
}
