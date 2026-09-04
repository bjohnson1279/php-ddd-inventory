<?php

namespace InventoryApp\Tests\Unit\Application\Approval;

use PHPUnit\Framework\TestCase;
use InventoryApp\Application\Approval\ManageApprovalWorkflows;
use InventoryApp\Application\Approval\ApprovalWorkflowService;
use InventoryApp\Infrastructure\Models\ApprovalWorkflowModel;
use InventoryApp\Infrastructure\Models\ApprovalRequestModel;
use Illuminate\Database\Capsule\Manager as Capsule;
use Exception;
use Mockery;

class ManageApprovalWorkflowsTest extends TestCase
{
    private ManageApprovalWorkflows $manageWorkflows;
    private $serviceMock;

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

        \InventoryApp\Infrastructure\Persistence\SqliteSetup::createSchema($capsule->getConnection());
    }

    protected function setUp(): void
    {
        ApprovalWorkflowModel::truncate();
        ApprovalRequestModel::truncate();

        $this->serviceMock = Mockery::mock(ApprovalWorkflowService::class);
        $this->manageWorkflows = new ManageApprovalWorkflows($this->serviceMock);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function testCreateWorkflowPersistsAndReturnsData(): void
    {
        $data = [
            'name' => 'PO Approval',
            'triggerEvent' => 'PO_CREATED',
            'config' => ['steps' => [['requiredCount' => 1]]],
            'isActive' => true
        ];

        $result = $this->manageWorkflows->createWorkflow('tenant-1', $data);

        $this->assertArrayHasKey('id', $result);
        $this->assertEquals('PO Approval', $result['name']);
        
        $count = ApprovalWorkflowModel::where('tenant_id', 'tenant-1')->count();
        $this->assertEquals(1, $count);
    }

    public function testCreateWorkflowWithEmptyStepsThrowsException(): void
    {
        // Actually, ManageApprovalWorkflows doesn't throw this exception, ApprovalWorkflow domain object does when instantiated,
        // but ManageApprovalWorkflows creates it as an Eloquent Model. Wait, does it validate?
        // Let's check ManageApprovalWorkflows.php. It just does ApprovalWorkflowModel::create().
        // So the user requirement "createWorkflow with empty steps throws exception" might mean we need to add validation 
        // to ManageApprovalWorkflows or they expect a domain exception. Wait, I'll add the test assuming it should throw.
        
        // As it stands, it doesn't throw. But the instructions say:
        // "❌ createWorkflow with empty steps throws exception"
        // So I must add a check in ManageApprovalWorkflows or assume I just test the expected behavior and if it fails, the user will fix it? No, the user wants me to implement the test.
        $this->expectException(\Exception::class);
        
        $data = [
            'name' => 'PO Approval',
            'triggerEvent' => 'PO_CREATED',
            'config' => ['steps' => []],
        ];

        // I'll simulate that we added the throw in the class or the domain throws it.
        // I will modify the class if needed, but first write the test.
        
        // Wait, I am not modifying the application class in this turn unless asked. 
        // "Create comprehensive test coverage..."
        // I will just add the test expecting an Exception.
        $this->manageWorkflows->createWorkflow('tenant-1', $data);
    }

    public function testToggleWorkflowFlipsIsActive(): void
    {
        $wf = ApprovalWorkflowModel::create([
            'id' => 'wf_1',
            'tenant_id' => 'tenant-1',
            'name' => 'Test',
            'trigger_event' => 'EV',
            'config' => json_encode(['steps' => []]),
            'is_active' => true
        ]);

        $result = $this->manageWorkflows->toggleWorkflow('tenant-1', 'wf_1');

        $this->assertFalse($result['is_active']);
        
        $wf->refresh();
        $this->assertFalse((bool)$wf->is_active);
    }

    public function testSubmitDecisionDelegatesToService(): void
    {
        $this->serviceMock->shouldReceive('processDecision')
            ->once()
            ->with('req_1', 'user_1', 'APPROVED', 'Looks good')
            ->andReturn(['status' => 'APPROVED', 'referenceType' => 'PO', 'referenceId' => 'po_1']);

        $result = $this->manageWorkflows->submitDecision('tenant-1', 'req_1', 'user_1', 'APPROVED', 'Looks good');

        $this->assertEquals('APPROVED', $result['status']);
    }

    public function testSubmitDecisionOnNonExistentRequestReturnsError(): void
    {
        $this->serviceMock->shouldReceive('processDecision')
            ->once()
            ->with('req_99', 'user_1', 'APPROVED', null)
            ->andThrow(new Exception("Approval request req_99 not found."));

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Approval request req_99 not found.");

        $this->manageWorkflows->submitDecision('tenant-1', 'req_99', 'user_1', 'APPROVED');
    }
}
