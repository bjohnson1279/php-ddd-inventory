<?php

namespace Tests\Unit\Domain\Identity;

use PHPUnit\Framework\TestCase;
use InventoryApp\Domain\Identity\ValueObjects\Email;
use InvalidArgumentException;

class EmailTest extends TestCase
{
    public function testValidEmailParsingAndGetValue(): void
    {
        $email = new Email('user@example.com');
        $this->assertEquals('user@example.com', $email->getValue());
        $this->assertEquals('user@example.com', (string) $email);
    }

    public function testEmailNormalizesToLowercaseAndTrimsWhitespace(): void
    {
        $email = new Email('   USER@EXAMPLE.COM   ');
        $this->assertEquals('user@example.com', $email->getValue());
    }

    public function testInvalidEmailThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid email address: not-an-email');
        new Email('not-an-email');
    }

    public function testEmptyEmailThrowsInvalidArgumentException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Email('   ');
    }

    public function testEmailEqualsComparison(): void
    {
        $email1 = new Email('user@example.com');
        $email2 = new Email('USER@EXAMPLE.COM');
        $email3 = new Email('other@example.com');

        $this->assertTrue($email1->equals($email2));
        $this->assertFalse($email1->equals($email3));
    }
}
