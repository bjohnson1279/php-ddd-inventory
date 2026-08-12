<?php

namespace Tests\Unit\Infrastructure\Http\Controllers;

use PHPUnit\Framework\TestCase;
use InventoryApp\Infrastructure\Http\Controllers\KitController;
use InventoryApp\Infrastructure\Http\RequestInterface;
use InventoryApp\Infrastructure\Http\Response;
use InventoryApp\Domain\Kit\Repositories\KitRepositoryInterface;
use InventoryApp\Domain\Kit\Aggregates\Kit;
use Exception;

class KitControllerTest extends TestCase
{
    private KitController $controller;
    private $kitRepositoryMock;

    protected function setUp(): void
    {
        $this->controller = new KitController();
        $this->kitRepositoryMock = $this->createMock(KitRepositoryInterface::class);
    }
}
