<?php

namespace InventoryApp\Infrastructure\Http\Controllers;

use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Domain\Inventory\Repositories\StockOnboardingRepositoryInterface;
use InventoryApp\Domain\Inventory\Aggregates\StockOnboarding;
use InventoryApp\Domain\Inventory\Services\OpeningBalanceService;
use Illuminate\Database\Capsule\Manager as Capsule;
use Ramsey\Uuid\Uuid;
use Exception;

class StockOnboardingController
{
    public function create(RequestInterface $request, StockOnboardingRepositoryInterface $repo)
    {
        try {
            $validated = $request->validate([
                'location_id' => 'required|string',
                'as_of_date'  => 'required|string',
            ]);

            $id = Uuid::uuid4()->toString();
            $tenantId = $_SERVER['auth.tenant_id'] ?? 'system';
            $asOfDate = new \DateTimeImmutable($validated['as_of_date']);

            $onboarding = new StockOnboarding($id, $tenantId, $validated['location_id'], $asOfDate);
            $repo->save($onboarding);

            return new Response([
                'message' => 'Stock onboarding created successfully',
                'id'      => $id,
            ], 201);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function setItem(RequestInterface $request, string $id, StockOnboardingRepositoryInterface $repo)
    {
        try {
            $validated = $request->validate([
                'variant_id'      => 'required|string',
                'quantity'        => 'required|integer',
                'unit_cost_cents' => 'required|integer',
            ]);

            $onboarding = $repo->findOrFail($id);
            $onboarding->setItem($validated['variant_id'], $validated['quantity'], $validated['unit_cost_cents']);
            $repo->save($onboarding);

            return new Response(['message' => 'Onboarding item set successfully'], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function removeItem(RequestInterface $request, string $id, string $variantId, StockOnboardingRepositoryInterface $repo)
    {
        try {
            $onboarding = $repo->findOrFail($id);
            $onboarding->removeItem($variantId);
            $repo->save($onboarding);

            return new Response(['message' => 'Onboarding item removed successfully'], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function submit(RequestInterface $request, string $id, OpeningBalanceService $service, StockOnboardingRepositoryInterface $repo)
    {
        try {
            $actorId = $_SERVER['auth.user_id'] ?? 'system';

            Capsule::transaction(function () use ($id, $actorId, $service, $repo) {
                $onboarding = $repo->findOrFail($id);
                $onboarding->submit();
                $repo->save($onboarding);
                $service->process($onboarding, $actorId);
            });

            return new Response(['message' => 'Stock onboarding submitted and processed successfully'], 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    public function show(RequestInterface $request, string $id, StockOnboardingRepositoryInterface $repo)
    {
        try {
            $onboarding = $repo->findOrFail($id);
            return new Response($this->serializeOnboarding($onboarding), 200);
        } catch (Exception $e) {
            return new Response(['error' => $e->getMessage()], 400);
        }
    }

    private function serializeOnboarding(StockOnboarding $onboarding): array
    {
        $items = array_map(function ($item) {
            return [
                'variant_id'      => $item->variantId,
                'quantity'        => $item->quantity,
                'unit_cost_cents' => $item->unitCostCents,
            ];
        }, $onboarding->items());

        return [
            'id'           => $onboarding->id,
            'tenant_id'    => $onboarding->tenantId,
            'location_id'  => $onboarding->locationId,
            'as_of_date'   => $onboarding->asOfDate->format('Y-m-d'),
            'status'       => $onboarding->isSubmitted() ? 'submitted' : 'draft',
            'items'        => $items,
        ];
    }
}
