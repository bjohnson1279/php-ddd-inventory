<?php

namespace InventoryApp\Infrastructure;

use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryLedgerRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemorySerializedItemRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryBarcodeRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryJournalRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryStockOnboardingRepository;

use InventoryApp\Domain\Inventory\Repositories\LedgerRepositoryInterface;
use InventoryApp\Domain\Serial\Repositories\SerializedItemRepositoryInterface;
use InventoryApp\Domain\Barcode\Repositories\BarcodeRepositoryInterface;

class ServiceContainer
{
    private static ?LedgerRepositoryInterface $ledger = null;
    private static ?SerializedItemRepositoryInterface $serials = null;
    private static ?BarcodeRepositoryInterface $barcodes = null;
    private static ?InMemoryJournalRepository $journal = null;
    private static ?InMemoryStockOnboardingRepository $onboards = null;

    public static function ledgerRepo(): LedgerRepositoryInterface
    {
        return self::$ledger ??= new InMemoryLedgerRepository();
    }

    public static function serializedRepo(): SerializedItemRepositoryInterface
    {
        return self::$serials ??= new InMemorySerializedItemRepository();
    }

    public static function barcodeRepo(): BarcodeRepositoryInterface
    {
        return self::$barcodes ??= new InMemoryBarcodeRepository();
    }

    public static function journalRepo(): InMemoryJournalRepository
    {
        return self::$journal ??= new InMemoryJournalRepository();
    }

    public static function stockOnboardingRepo(): InMemoryStockOnboardingRepository
    {
        return self::$onboards ??= new InMemoryStockOnboardingRepository();
    }
}
