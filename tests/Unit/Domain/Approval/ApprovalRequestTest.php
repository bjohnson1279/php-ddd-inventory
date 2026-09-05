<?php

namespace InventoryApp\Tests\Unit\Domain\Approval;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Approval\ApprovalRequest;
use InventoryApp\Domain\Approval\ApprovalDecisionRecord;
use DomainException;
use DateTimeImmutable;

class ApprovalRequestTest extends TestCase
{
    private function createRequest(int $totalSteps = 2, string $status = ApprovalRequest::STATUS_PENDING, int $currentStep = 0): ApprovalRequest
    {
        return ApprovalRequest::reconstruct(
            'req_1', 't_1', 'wf_1', 'PO', 'po_1', 'user_1',
            ['amount' => 1000],
            $totalSteps,
            $status,
            $currentStep,
            []
        );
    }

    private function createDecision(int $stepIndex, string $decision = 'APPROVED'): ApprovalDecisionRecord
    {
        return new ApprovalDecisionRecord(
            'dec_1', $stepIndex, 'user_2', $decision, 'notes', new DateTimeImmutable()
        );
    }

    public function testApproveStaysPendingWhenRequiredCountNotMet(): void
    {
        $request = $this->createRequest(1);
        $decision = $this->createDecision(0, 'APPROVED');

        $request->approve($decision, 2); // Requires 2 approvals

        $this->assertEquals(ApprovalRequest::STATUS_PENDING, $request->getStatus());
        $this->assertEquals(0, $request->getCurrentStep());
    }

    public function testApproveAdvancesStepWhenCountMetAtNonFinalStep(): void
    {
        $request = $this->createRequest(3, ApprovalRequest::STATUS_PENDING, 0);
        $decision = $this->createDecision(0, 'APPROVED');

        $request->approve($decision, 1);

        $this->assertEquals(ApprovalRequest::STATUS_PENDING, $request->getStatus());
        $this->assertEquals(1, $request->getCurrentStep());
    }

    public function testApproveTransitionsToApprovedWhenAllStepsComplete(): void
    {
        $request = $this->createRequest(2, ApprovalRequest::STATUS_PENDING, 1); // on final step
        $decision = $this->createDecision(1, 'APPROVED');

        $request->approve($decision, 1);

        $this->assertEquals(ApprovalRequest::STATUS_APPROVED, $request->getStatus());
    }

    public function testApproveThrowsWhenNotPending(): void
    {
        $request = $this->createRequest(2, ApprovalRequest::STATUS_APPROVED);
        $decision = $this->createDecision(0);

        $this->expectException(DomainException::class);
        $request->approve($decision, 1);
    }

    public function testApproveThrowsWhenStepIndexMismatch(): void
    {
        $request = $this->createRequest(2, ApprovalRequest::STATUS_PENDING, 0);
        $decision = $this->createDecision(1); // Mismatch, should be 0

        $this->expectException(DomainException::class);
        $request->approve($decision, 1);
    }

    public function testRejectTransitionsToRejectedImmediately(): void
    {
        $request = $this->createRequest(3, ApprovalRequest::STATUS_PENDING, 0);
        $decision = $this->createDecision(0, 'REJECTED');

        $request->reject($decision);

        $this->assertEquals(ApprovalRequest::STATUS_REJECTED, $request->getStatus());
    }

    public function testRejectThrowsWhenNotPending(): void
    {
        $request = $this->createRequest(2, ApprovalRequest::STATUS_APPROVED);
        $decision = $this->createDecision(0, 'REJECTED');

        $this->expectException(DomainException::class);
        $request->reject($decision);
    }

    public function testEscalateAdvancesStep(): void
    {
        $request = $this->createRequest(3, ApprovalRequest::STATUS_PENDING, 0);

        $request->escalate();

        $this->assertEquals(ApprovalRequest::STATUS_ESCALATED, $request->getStatus());
        $this->assertEquals(1, $request->getCurrentStep());
    }

    public function testEscalateTransitionsToExpiredAtFinalStep(): void
    {
        $request = $this->createRequest(2, ApprovalRequest::STATUS_PENDING, 1); // on final step

        $request->escalate();

        $this->assertEquals(ApprovalRequest::STATUS_EXPIRED, $request->getStatus());
    }

    public function testEscalateThrowsOnTerminalStatus(): void
    {
        $request = $this->createRequest(2, ApprovalRequest::STATUS_APPROVED);

        $this->expectException(DomainException::class);
        $request->escalate();
    }

    public function testExpireWorksOnPendingAndEscalated(): void
    {
        $request1 = $this->createRequest(2, ApprovalRequest::STATUS_PENDING);
        $request1->expire();
        $this->assertEquals(ApprovalRequest::STATUS_EXPIRED, $request1->getStatus());

        $request2 = $this->createRequest(2, ApprovalRequest::STATUS_ESCALATED);
        $request2->expire();
        $this->assertEquals(ApprovalRequest::STATUS_EXPIRED, $request2->getStatus());
    }

    public function testExpireThrowsOnApprovedOrRejected(): void
    {
        $request = $this->createRequest(2, ApprovalRequest::STATUS_APPROVED);

        $this->expectException(DomainException::class);
        $request->expire();
    }
}
