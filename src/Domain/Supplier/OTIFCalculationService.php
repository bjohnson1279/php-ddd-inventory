<?php

namespace App\Domain\Supplier;

class OTIFCalculationService
{
    /**
     * @param ASN $asn
     * @param \DateTimeImmutable $actualReceiptDate
     * @param array<string, int> $actualQuantities
     * @return array
     */
    public function calculateOTIF(ASN $asn, \DateTimeImmutable $actualReceiptDate, array $actualQuantities): array
    {
        $isOnTime = $actualReceiptDate <= $asn->getExpectedArrivalDate();

        $isInFull = true;
        $defectCount = 0;
        $items = $asn->getLines();
        $totalItems = count($items);

        foreach ($items as $item) {
            $sku = $item->sku;
            $received = $actualQuantities[$sku] ?? 0;
            if ($received < $item->shippedQuantity) {
                $isInFull = false;
                $defectCount++;
            }
        }

        $defectRatePercentage = $totalItems > 0 ? ($defectCount / $totalItems) * 100.0 : 0.0;

        return [
            'onTime' => $isOnTime,
            'inFull' => $isInFull,
            'otifSuccess' => $isOnTime && $isInFull,
            'defectRatePercentage' => $defectRatePercentage
        ];
    }
}
