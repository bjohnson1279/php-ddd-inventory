<?php

namespace InventoryApp\Infrastructure;

use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryLedgerRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemorySerializedItemRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryBarcodeRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryJournalRepository;
use InventoryApp\Infrastructure\Persistence\Repositories\InMemoryStockOnboardingRepository;

class ServiceContainer
{
    private static ?InMemoryLedgerRepository $ledger = null;
    private static ?InMemorySerializedItemRepository $serials = null;
    private static ?InMemoryBarcodeRepository $barcodes = null;
    private static ?InMemoryJournalRepository $journal = null;
    private static ?InMemoryStockOnboardingRepository $onboards = null;

    public static function ledgerRepo(): InMemoryLedgerRepository
    {
        return self::$ledger ??= new InMemoryLedgerRepository();
    }

    public static function serializedRepo(): InMemorySerializedItemRepository
    {
        return self::$serials ??= new InMemorySerializedItemRepository();
    }

    public static function barcodeRepo(): InMemoryBarcodeRepository
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
