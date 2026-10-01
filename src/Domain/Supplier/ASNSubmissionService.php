<?php

namespace App\Domain\Supplier;

use InventoryApp\Domain\Procurement\Aggregates\PurchaseOrder;
use InventoryApp\Domain\Procurement\Enums\PurchaseOrderStatus;

class ASNSubmissionService
{
    public function validateAndSubmitASN(PurchaseOrder $po, ASN $asn): ASN
    {
        // Must be Sent or PartiallyReceived
        if ($po->getStatus() !== PurchaseOrderStatus::Sent && $po->getStatus() !== PurchaseOrderStatus::PartiallyReceived) {
            throw new \DomainException("Cannot submit ASN for PO in current status.");
        }

        if ($po->vendorId !== $asn->getSupplierId()) {
            throw new \DomainException("ASN supplier does not match PO supplier.");
        }

        if (empty($asn->getItems())) {
            throw new \DomainException("ASN must contain at least one line item.");
        }

        // We assume we might change PO status here if we wanted to add a 'Shipped' status,
        // but for parity with PHP's PurchaseOrderStatus, we'll let it stay Sent or PartiallyReceived
        // or add it if it doesn't exist.
        
        $asn->setStatus('SUBMITTED');

        return $asn;
    }
}
