<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Barcode\Repositories\BarcodeRepositoryInterface;
use InventoryApp\Domain\Barcode\ValueObjects\Barcode;
use InventoryApp\Domain\Barcode\Enums\BarcodeSource;
use InventoryApp\Domain\Barcode\Enums\BarcodeSymbology;
use Exception;

class BarcodeController
{
    public function lookup(RequestInterface $request, BarcodeRepositoryInterface $repo)
    {
        try {
            $value = $request->query('value');
            if (empty($value)) {
                return new Response(['error' => 'Barcode value query parameter is required'], 400);
            }

            $variantId = $repo->findVariantByBarcodeValue($value);

            return new Response(['variant_id' => $variantId], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function assign(RequestInterface $request, BarcodeRepositoryInterface $repo)
    {
        try {
            $validated = $request->validate([
                'variant_id' => 'required|string',
                'value'      => 'required|string',
                'symbology'  => 'required|string',
                'source'     => 'required|string',
            ]);

            $isPrimary = (bool)($request->query('is_primary') ?? ($request->validate([])['is_primary'] ?? false));
            // Let's grab is_primary from the body as well if present
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            if (isset($body['is_primary'])) {
                $isPrimary = (bool)$body['is_primary'];
            }

            $symbology = BarcodeSymbology::tryFrom($validated['symbology']);
            if ($symbology === null) {
                throw new \InvalidArgumentException('Invalid barcode symbology.');
            }

            $source = BarcodeSource::tryFrom($validated['source']);
            if ($source === null) {
                throw new \InvalidArgumentException('Invalid barcode source.');
            }

            $barcode = new Barcode($symbology, $validated['value']);

            $repo->registerAssignment($validated['variant_id'], $barcode, $source, $isPrimary);

            return new Response(['message' => 'Barcode assigned successfully'], 201);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function getVariantSet(RequestInterface $request, string $variantId, BarcodeRepositoryInterface $repo)
    {
        try {
            $set = $repo->findSetForVariant($variantId);
            $assignments = array_map(function ($a) {
                return [
                    'id'         => $a->id,
                    'variant_id' => $a->variantId,
                    'value'      => $a->barcode->value,
                    'symbology'  => $a->barcode->symbology->value,
                    'source'     => $a->source->value,
                    'is_primary' => $a->isPrimary,
                    'created_at' => $a->assignedAt->format(\DateTimeInterface::ATOM),
                ];
            }, $set->all());

            return new Response([
                'variant_id'  => $variantId,
                'assignments' => $assignments,
            ], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }
}
