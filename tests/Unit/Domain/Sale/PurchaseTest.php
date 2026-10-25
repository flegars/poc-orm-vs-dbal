<?php

namespace App\Tests\Unit\Domain\Sale;

use App\Domain\Discount\Quote;
use App\Domain\Sale\Purchase;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class PurchaseTest extends TestCase
{
    public function testPurchaseRecordsPaidPriceAndDiscount(): void
    {
        $purchase = Purchase::record(Uuid::v7(), 1, new Quote(2000, 5, 1500), new \DateTimeImmutable('2026-06-15'));

        self::assertSame([2000, 5, 1500], [$purchase->basePriceInCents(), $purchase->discountId(), $purchase->paidInCents()]);
    }

    public function testPurchaseKeepsIdentityBookAndDate(): void
    {
        $id = Uuid::v7();

        $purchase = Purchase::record($id, 42, new Quote(1000, null, 1000), new \DateTimeImmutable('2026-06-15'));

        self::assertTrue($id->equals($purchase->id()));
        self::assertSame(42, $purchase->bookId());
        self::assertSame('2026-06-15', $purchase->purchasedOn()->format('Y-m-d'));
        self::assertNull($purchase->discountId());
    }
}
