<?php

namespace InventoryApp\Domain\Barcode\Enums;

enum BarcodeSymbology: string
{
    case UPC_A = 'upc_a';
    case UPC_E = 'upc_e';
    case EAN_13 = 'ean_13';
    case EAN_8 = 'ean_8';
    case CODE_128 = 'code_128';
    case QR = 'qr';
    case ITF_14 = 'itf_14';
    case GS1_128 = 'gs1_128';

    public function label(): string
    {
        return match($this) {
            self::UPC_A => 'UPC-A',
            self::UPC_E => 'UPC-E',
            self::EAN_13 => 'EAN-13',
            self::EAN_8 => 'EAN-8',
            self::CODE_128 => 'Code 128',
            self::QR => 'QR Code',
            self::ITF_14 => 'ITF-14',
            self::GS1_128 => 'GS1-128',
        };
    }
}
