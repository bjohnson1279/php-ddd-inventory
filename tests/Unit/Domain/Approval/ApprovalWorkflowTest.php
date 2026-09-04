<?php

namespace InventoryApp\Tests\Unit\Domain\Approval;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Approval\ApprovalWorkflow;
use DomainException;
use DateTimeImmutable;

class ApprovalWorkflowTest extends TestCase
{
    private function createValidConfig(): array
    {
        return [
            'steps' => [
                ['approverRoles' => ['MANAGER'], 'requiredCount' => 1]
            ]
        ];
    }

    public function testShouldTriggerReturnsTrueWhenAllThresholdsMet(): void
    {
        $config = $this->createValidConfig();
        $config['thresholds'] = [
            ['field' => 'amount', 'operator' => '>=', 'value' => 1000]
        ];

        $workflow = new ApprovalWorkflow(
            'wf_1', 't_1', 'Test', 'PO_CREATED', true, $config
        );

        $this->assertTrue($workflow->shouldTrigger(['amount' => 1000]));
        $this->assertTrue($workflow->shouldTrigger(['amount' => 1500]));
    }

    public function testShouldTriggerReturnsTrueWhenThresholdsEmpty(): void
    {
        $config = $this->createValidConfig();
        
        $workflow = new ApprovalWorkflow(
            'wf_1', 't_1', 'Test', 'PO_CREATED', true, $config
        );

        $this->assertTrue($workflow->shouldTrigger(['amount' => 100]));
    }

    public function testShouldTriggerReturnsFalseWhenInactive(): void
    {
        $config = $this->createValidConfig();
        
        $workflow = new ApprovalWorkflow(
            'wf_1', 't_1', 'Test', 'PO_CREATED', false, $config
        );

        $this->assertFalse($workflow->shouldTrigger([]));
    }

    public function testShouldTriggerReturnsFalseWhenAnyThresholdFails(): void
    {
        $config = $this->createValidConfig();
        $config['thresholds'] = [
            ['field' => 'amount', 'operator' => '>=', 'value' => 1000],
            ['field' => 'category', 'operator' => '==', 'value' => 'IT']
        ];

        $workflow = new ApprovalWorkflow(
            'wf_1', 't_1', 'Test', 'PO_CREATED', true, $config
        );

        // Fails first threshold
        $this->assertFalse($workflow->shouldTrigger(['amount' => 500, 'category' => 'IT']));
        // Fails second threshold
        $this->assertFalse($workflow->shouldTrigger(['amount' => 1500, 'category' => 'HR']));
    }

    public function testShouldTriggerReturnsFalseWhenPayloadFieldIsNull(): void
    {
        $config = $this->createValidConfig();
        $config['thresholds'] = [
            ['field' => 'amount', 'operator' => '>=', 'value' => 1000]
        ];

        $workflow = new ApprovalWorkflow(
            'wf_1', 't_1', 'Test', 'PO_CREATED', true, $config
        );

        // Missing field
        $this->assertFalse($workflow->shouldTrigger(['other' => 1000]));
    }

    public function testConstructorThrowsWhenTriggerEventIsEmpty(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Approval workflow trigger event cannot be empty.');
        
        new ApprovalWorkflow(
            'wf_1', 't_1', 'Test', '   ', true, $this->createValidConfig()
        );
    }

    public function testConstructorThrowsWhenStepsArrayIsEmpty(): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Approval workflow must define at least one approval step.');
        
        new ApprovalWorkflow(
            'wf_1', 't_1', 'Test', 'PO_CREATED', true, ['steps' => []]
        );
    }

    public function testAllComparisonOperatorsWorkCorrectly(): void
    {
        $config = $this->createValidConfig();
        
        // >=
        $config['thresholds'] = [['field' => 'val', 'operator' => '>=', 'value' => 10]];
        $w = new ApprovalWorkflow('wf_1', 't_1', 'Test', 'EV', true, $config);
        $this->assertTrue($w->shouldTrigger(['val' => 10]));
        $this->assertFalse($w->shouldTrigger(['val' => 9]));

        // >
        $config['thresholds'] = [['field' => 'val', 'operator' => '>', 'value' => 10]];
        $w = new ApprovalWorkflow('wf_1', 't_1', 'Test', 'EV', true, $config);
        $this->assertTrue($w->shouldTrigger(['val' => 11]));
        $this->assertFalse($w->shouldTrigger(['val' => 10]));

        // <=
        $config['thresholds'] = [['field' => 'val', 'operator' => '<=', 'value' => 10]];
        $w = new ApprovalWorkflow('wf_1', 't_1', 'Test', 'EV', true, $config);
        $this->assertTrue($w->shouldTrigger(['val' => 10]));
        $this->assertFalse($w->shouldTrigger(['val' => 11]));

        // <
        $config['thresholds'] = [['field' => 'val', 'operator' => '<', 'value' => 10]];
        $w = new ApprovalWorkflow('wf_1', 't_1', 'Test', 'EV', true, $config);
        $this->assertTrue($w->shouldTrigger(['val' => 9]));
        $this->assertFalse($w->shouldTrigger(['val' => 10]));

        // ==
        $config['thresholds'] = [['field' => 'val', 'operator' => '==', 'value' => 10]];
        $w = new ApprovalWorkflow('wf_1', 't_1', 'Test', 'EV', true, $config);
        $this->assertTrue($w->shouldTrigger(['val' => 10]));
        $this->assertFalse($w->shouldTrigger(['val' => 11]));

        // !=
        $config['thresholds'] = [['field' => 'val', 'operator' => '!=', 'value' => 10]];
        $w = new ApprovalWorkflow('wf_1', 't_1', 'Test', 'EV', true, $config);
        $this->assertTrue($w->shouldTrigger(['val' => 11]));
        $this->assertFalse($w->shouldTrigger(['val' => 10]));
        
        // unknown
        $config['thresholds'] = [['field' => 'val', 'operator' => 'UNKNOWN', 'value' => 10]];
        $w = new ApprovalWorkflow('wf_1', 't_1', 'Test', 'EV', true, $config);
        $this->assertFalse($w->shouldTrigger(['val' => 10]));
    }
}
