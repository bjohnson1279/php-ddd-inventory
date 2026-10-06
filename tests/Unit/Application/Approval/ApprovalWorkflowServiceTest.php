<?php

namespace InventoryApp\Tests\Unit\Application\Approval;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Approval\ApprovalWorkflowService;
use InventoryApp\Domain\Shared\Events\EventDispatcher;
use InventoryApp\Infrastructure\Models\ApprovalWorkflowModel;
use InventoryApp\Infrastructure\Models\ApprovalRequestModel;
use InventoryApp\Infrastructure\Models\ApprovalDecisionModel;
use Illuminate\Database\Capsule\Manager as Capsule;
use Exception;
use DateTimeImmutable;

class ApprovalWorkflowServiceTest extends TestCase
{
    private ApprovalWorkflowService $service;
    private $eventDispatcherMock;

    private string $tenantId = 'tenant-123';
    private string $workflowId = 'wf-123';
    private string $requestId = 'req-123';
    private string $userId = 'user-123';

    public static function setUpBeforeClass(): void
    {
        $capsule = new Capsule;
        $capsule->addConnection([
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        Capsule::schema()->create('approval_workflows', function ($table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('trigger_event');
            $table->json('config');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Capsule::schema()->create('approval_requests', function ($table) {
            $table->string('id')->primary();
            $table->string('tenant_id');
            $table->string('workflow_id');
            $table->string('reference_type');
            $table->string('reference_id');
            $table->string('requester_id');
            $table->string('status');
            $table->integer('current_step')->default(0);
            $table->json('payload');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });

        Capsule::schema()->create('approval_decisions', function ($table) {
            $table->string('id')->primary();
            $table->string('request_id');
            $table->integer('step_index');
            $table->string('decider_id');
            $table->string('decision');
            $table->text('notes')->nullable();
            $table->timestamp('decided_at');
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        try {
            ApprovalDecisionModel::query()->delete();
            ApprovalRequestModel::query()->delete();
            ApprovalWorkflowModel::query()->delete();
        } catch (\Throwable $e) {}

        $this->eventDispatcherMock = $this->createMock(EventDispatcher::class);
        $this->service = new ApprovalWorkflowService($this->eventDispatcherMock);
    }

    public function testEvaluateAndInterceptReturnsNotInterceptedWhenWorkflowNotFound(): void
    {
        $result = $this->service->evaluateAndIntercept(
            $this->tenantId,
            'PO_CREATED',
            'PURCHASE_ORDER',
            'PO-1',
            $this->userId,
            ['amount' => 1000]
        );

        $this->assertFalse($result['intercepted']);
    }

    public function testEvaluateAndInterceptReturnsNotInterceptedWhenInactive(): void
    {
        ApprovalWorkflowModel::create([
            'id' => $this->workflowId,
            'tenant_id' => $this->tenantId,
            'name' => 'PO Approval',
            'trigger_event' => 'PO_CREATED',
            'config' => [
                'steps' => [
                    ['approverRoles' => ['manager'], 'timeoutHours' => 24]
                ]
            ],
            'is_active' => false,
        ]);

        $result = $this->service->evaluateAndIntercept(
            $this->tenantId,
            'PO_CREATED',
            'PURCHASE_ORDER',
            'PO-1',
            $this->userId,
            ['amount' => 1000]
        );

        $this->assertFalse($result['intercepted']);
    }

    public function testEvaluateAndInterceptReturnsNotInterceptedWhenShouldTriggerIsFalse(): void
    {
        ApprovalWorkflowModel::create([
            'id' => $this->workflowId,
            'tenant_id' => $this->tenantId,
            'name' => 'PO Approval High Value',
            'trigger_event' => 'PO_CREATED',
            'config' => [
                'thresholds' => [
                    ['field' => 'amount', 'operator' => '>=', 'value' => 5000]
                ],
                'steps' => [
                    ['approverRoles' => ['manager']]
                ]
            ],
            'is_active' => true,
        ]);

        $result = $this->service->evaluateAndIntercept(
            $this->tenantId,
            'PO_CREATED',
            'PURCHASE_ORDER',
            'PO-1',
            $this->userId,
            ['amount' => 1000]
        );

        $this->assertFalse($result['intercepted']);
    }

    public function testEvaluateAndInterceptCreatesPendingRequest(): void
    {
        ApprovalWorkflowModel::create([
            'id' => $this->workflowId,
            'tenant_id' => $this->tenantId,
            'name' => 'PO Approval',
            'trigger_event' => 'PO_CREATED',
            'config' => [
                'steps' => [
                    ['approverRoles' => ['manager'], 'timeoutHours' => 48]
                ]
            ],
            'is_active' => true,
        ]);

        $result = $this->service->evaluateAndIntercept(
            $this->tenantId,
            'PO_CREATED',
            'PURCHASE_ORDER',
            'PO-1',
            $this->userId,
            ['amount' => 1000]
        );

        $this->assertTrue($result['intercepted']);
        $this->assertArrayHasKey('requestId', $result);

        $createdRequest = ApprovalRequestModel::find($result['requestId']);
        $this->assertNotNull($createdRequest);
        $this->assertEquals('PENDING', $createdRequest->status);
        $this->assertEquals(0, $createdRequest->current_step);
        $this->assertNotNull($createdRequest->expires_at);
    }

    public function testProcessDecisionThrowsExceptionWhenRequestNotFound(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Approval request non-existent-id not found.');

        $this->service->processDecision('non-existent-id', $this->userId, 'APPROVED');
    }

    public function testProcessDecisionApprove(): void
    {
        ApprovalWorkflowModel::create([
            'id' => $this->workflowId,
            'tenant_id' => $this->tenantId,
            'name' => 'PO Approval',
            'trigger_event' => 'PO_CREATED',
            'config' => [
                'steps' => [
                    ['approverRoles' => ['manager'], 'requiredCount' => 1]
                ]
            ],
            'is_active' => true,
        ]);

        ApprovalRequestModel::create([
            'id' => $this->requestId,
            'tenant_id' => $this->tenantId,
            'workflow_id' => $this->workflowId,
            'reference_type' => 'PURCHASE_ORDER',
            'reference_id' => 'PO-1',
            'requester_id' => $this->userId,
            'status' => 'PENDING',
            'current_step' => 0,
            'payload' => ['amount' => 1000],
        ]);

        $result = $this->service->processDecision($this->requestId, 'approver-1', 'APPROVED', 'Looks good');

        $this->assertEquals('APPROVED', $result['status']);
        $this->assertEquals('PURCHASE_ORDER', $result['referenceType']);
        $this->assertEquals('PO-1', $result['referenceId']);

        $updatedRequest = ApprovalRequestModel::find($this->requestId);
        $this->assertEquals('APPROVED', $updatedRequest->status);

        $decision = ApprovalDecisionModel::where('request_id', $this->requestId)->first();
        $this->assertNotNull($decision);
        $this->assertEquals('approver-1', $decision->decider_id);
        $this->assertEquals('APPROVED', $decision->decision);
        $this->assertEquals('Looks good', $decision->notes);
    }

    public function testProcessDecisionReject(): void
    {
        ApprovalWorkflowModel::create([
            'id' => $this->workflowId,
            'tenant_id' => $this->tenantId,
            'name' => 'PO Approval',
            'trigger_event' => 'PO_CREATED',
            'config' => [
                'steps' => [
                    ['approverRoles' => ['manager']]
                ]
            ],
            'is_active' => true,
        ]);

        ApprovalRequestModel::create([
            'id' => $this->requestId,
            'tenant_id' => $this->tenantId,
            'workflow_id' => $this->workflowId,
            'reference_type' => 'PURCHASE_ORDER',
            'reference_id' => 'PO-1',
            'requester_id' => $this->userId,
            'status' => 'PENDING',
            'current_step' => 0,
            'payload' => ['amount' => 1000],
        ]);

        $result = $this->service->processDecision($this->requestId, 'approver-1', 'REJECTED', 'Too expensive');

        $this->assertEquals('REJECTED', $result['status']);

        $updatedRequest = ApprovalRequestModel::find($this->requestId);
        $this->assertEquals('REJECTED', $updatedRequest->status);

        $decision = ApprovalDecisionModel::where('request_id', $this->requestId)->first();
        $this->assertNotNull($decision);
        $this->assertEquals('REJECTED', $decision->decision);
        $this->assertEquals('Too expensive', $decision->notes);
    }

    public function testCheckExpiredRequests(): void
    {
        ApprovalWorkflowModel::create([
            'id' => $this->workflowId,
            'tenant_id' => $this->tenantId,
            'name' => 'PO Approval Multi Step',
            'trigger_event' => 'PO_CREATED',
            'config' => [
                'steps' => [
                    ['approverRoles' => ['manager'], 'timeoutHours' => 12],
                    ['approverRoles' => ['director'], 'timeoutHours' => 24]
                ]
            ],
            'is_active' => true,
        ]);

        $past = (new DateTimeImmutable('-2 hours'))->format('Y-m-d H:i:s');
        ApprovalRequestModel::create([
            'id' => $this->requestId,
            'tenant_id' => $this->tenantId,
            'workflow_id' => $this->workflowId,
            'reference_type' => 'PURCHASE_ORDER',
            'reference_id' => 'PO-1',
            'requester_id' => $this->userId,
            'status' => 'PENDING',
            'current_step' => 0,
            'payload' => ['amount' => 1000],
            'expires_at' => $past,
        ]);

        $processed = $this->service->checkExpiredRequests();

        $this->assertEquals(1, $processed);

        $updatedRequest = ApprovalRequestModel::find($this->requestId);
        $this->assertEquals(1, $updatedRequest->current_step);
        $this->assertEquals('ESCALATED', $updatedRequest->status);
        $this->assertNotNull($updatedRequest->expires_at);
    }

    public function testListPendingRequests(): void
    {
        ApprovalWorkflowModel::create([
            'id' => $this->workflowId,
            'tenant_id' => $this->tenantId,
            'name' => 'PO Approval',
            'trigger_event' => 'PO_CREATED',
            'config' => [
                'steps' => [
                    ['approverRoles' => ['role-manager', 'role-admin']]
                ]
            ],
            'is_active' => true,
        ]);

        ApprovalRequestModel::create([
            'id' => $this->requestId,
            'tenant_id' => $this->tenantId,
            'workflow_id' => $this->workflowId,
            'reference_type' => 'PURCHASE_ORDER',
            'reference_id' => 'PO-1',
            'requester_id' => $this->userId,
            'status' => 'PENDING',
            'current_step' => 0,
            'payload' => ['amount' => 1000],
        ]);

        $allPending = $this->service->listPendingRequests($this->tenantId);
        $this->assertCount(1, $allPending);

        $matchingRoleRequests = $this->service->listPendingRequests($this->tenantId, ['role-manager']);
        $this->assertCount(1, $matchingRoleRequests);

        $nonMatchingRoleRequests = $this->service->listPendingRequests($this->tenantId, ['role-finance']);
        $this->assertCount(0, $nonMatchingRoleRequests);
    }
}
