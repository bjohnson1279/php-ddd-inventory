<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Application\Shipping\UseCases\CalculateShippingRates;
use InventoryApp\Application\Shipping\UseCases\PurchaseShippingLabel;
use InventoryApp\Application\Shipping\UseCases\UpdateShipmentStatus;
use InventoryApp\Application\Shipping\UseCases\RouteOrder;
use InventoryApp\Domain\Shipping\Repositories\ShipmentRepositoryInterface;
use InventoryApp\Domain\Shipping\Enums\ShipmentStatus;
use Exception;

class ShippingController
{
    public function getRates(RequestInterface $request, CalculateShippingRates $useCase)
    {
        try {
            $sku = $request->query('sku');
            $quantityVal = $request->query('quantity');
            $quantity = $quantityVal !== null ? (int)$quantityVal : 1;
            $address = $request->query('address');

            if (empty($sku) || empty($address)) {
                return new Response(['error' => 'Missing required query parameters: sku and address.'], 400);
            }

            $rates = $useCase->execute($sku, $quantity, $address);

            return new Response(array_map(fn($r) => $r->toArray(), $rates), 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[ShippingController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            $type = (new \ReflectionClass($e))->getShortName();
            return new Response(['error' => $e->getMessage(), 'type' => $type], 400);
        }
    }

    public function purchaseLabel(RequestInterface $request, PurchaseShippingLabel $useCase)
    {
        try {
            $body = json_decode(file_get_contents('php://input'), true) ?: [];

            $sku = $body['sku'] ?? null;
            $quantity = isset($body['quantity']) ? (int)$body['quantity'] : null;
            $destinationAddress = $body['destinationAddress'] ?? null;
            $carrier = $body['carrier'] ?? null;
            $locationId = $body['locationId'] ?? 'default';
            $tenantId = $body['tenantId'] ?? ($_SERVER['auth.tenant_id'] ?? 'system');

            if (empty($sku) || $quantity === null || empty($destinationAddress) || empty($carrier)) {
                return new Response(['error' => 'Missing required parameters for shipping label purchase.'], 400);
            }

            $result = $useCase->execute(
                $sku,
                $quantity,
                $destinationAddress,
                $carrier,
                $locationId,
                $tenantId
            );

            return new Response(array_merge([
                'message' => 'Shipping label purchased successfully.'
            ], $result->toArray()), 201);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[ShippingController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            $type = (new \ReflectionClass($e))->getShortName();
            return new Response(['error' => $e->getMessage(), 'type' => $type], 400);
        }
    }

    public function getShipments(RequestInterface $request, ShipmentRepositoryInterface $repo)
    {
        try {
            $shipments = $repo->findAll();

            return new Response(array_map(fn($s) => [
                'id' => $s->id,
                'sku' => $s->sku,
                'quantity' => $s->quantity,
                'destinationAddress' => $s->destinationAddress,
                'carrier' => $s->carrier,
                'trackingNumber' => $s->trackingNumber,
                'labelUrl' => $s->labelUrl,
                'shippingRateCents' => $s->shippingRateCents,
                'status' => $s->getStatus()->value,
                'createdAt' => $s->createdAt->format(\DateTimeInterface::ATOM),
                'updatedAt' => $s->updatedAt ? $s->updatedAt->format(\DateTimeInterface::ATOM) : null,
            ], $shipments), 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[ShippingController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function trackShipment(RequestInterface $request, string $id, UpdateShipmentStatus $useCase)
    {
        try {
            $body = json_decode(file_get_contents('php://input'), true) ?: [];
            $statusStr = $body['status'] ?? null;

            if (empty($statusStr)) {
                return new Response(['error' => 'Status is required.'], 400);
            }

            $status = ShipmentStatus::from($statusStr);

            $useCase->execute($id, $status);

            return new Response([
                'message' => 'Shipment status updated successfully.',
                'status' => $status->value
            ], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[ShippingController.php] ' . $e->getMessage());
                return new Response(['error' => 'An internal server error occurred.'], 500);
            }
            $type = (new \ReflectionClass($e))->getShortName();
            return new Response(['error' => $e->getMessage(), 'type' => $type], 400);
        }
     }

    public function routeOrder(RequestInterface $request, RouteOrder $useCase)
    {
        try {
            $body = json_decode(file_get_contents('php://input'), true) ?: [];

            $sku = $body['sku'] ?? null;
            $quantityVal = $body['quantity'] ?? null;
            $quantity = $quantityVal !== null ? (int)$quantityVal : null;
            $destinationAddress = $body['destinationAddress'] ?? null;
            $strategyName = $body['strategyName'] ?? null;

            if (empty($sku) || $quantity === null || empty($destinationAddress)) {
                return new Response(['error' => 'Missing required body fields: sku, quantity, and destinationAddress.'], 400);
            }

            $plan = $useCase->execute($sku, $quantity, $destinationAddress, $strategyName);

            $allocations = array_map(fn($a) => [
                'locationId' => $a->locationId,
                'quantity' => $a->quantity
            ], $plan->allocations);

            return new Response([
                'allocations' => $allocations,
                'estimatedShippingCostCents' => $plan->estimatedShippingCostCents,
                'totalDistanceKm' => $plan->totalDistanceKm,
                'splitCount' => $plan->splitCount,
                'score' => $plan->score
            ], 200);
        } catch (Exception $e) {
            if (!($e instanceof \InvalidArgumentException || $e instanceof \ValidationException || $e instanceof \DomainException)) {
                error_log('[ShippingController.php] ' . $e->getMessage());
                return new Response(['error' => 'Failed to route order: ' . $e->getMessage()], 500);
            }
            $type = (new \ReflectionClass($e))->getShortName();
            return new Response(['error' => $e->getMessage(), 'type' => $type], 400);
        }
    }
}
