<?php

namespace InventoryApp\Application\Returns\UseCases;

use InventoryApp\Domain\Returns\Repositories\RMARepositoryInterface;
use Exception;

class AuthorizeRMA
{
    public function __construct(private readonly RMARepositoryInterface $rmaRepository) {}

    public function execute(string $rmaId): void
    {
        $rma = $this->rmaRepository->findById($rmaId);
        if (!$rma) {
            throw new Exception("RMA with ID {$rmaId} not found.");
        }

        $rma->authorize();
        $this->rmaRepository->save($rma);
    }
}
