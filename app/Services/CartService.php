<?php

namespace App\Services;

use App\Models\CartItem;
use App\Models\MenuItem;
use App\Models\TableSession;
use App\Support\MoneyCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CartService
{
    public function addItem(TableSession $session, MenuItem $menuItem, int $quantity = 1, ?string $note = null): CartItem
    {
        $this->assertSessionActive($session);
        $this->assertItemOrderable($menuItem);

        if ($quantity < 1) {
            throw ValidationException::withMessages([
                'quantity' => ['الكمية يجب أن تكون 1 على الأقل.'],
            ]);
        }

        return DB::transaction(function () use ($session, $menuItem, $quantity, $note) {
            $cartItem = CartItem::query()->firstOrNew([
                'table_session_id' => $session->id,
                'menu_item_id' => $menuItem->id,
            ]);

            $cartItem->quantity = ($cartItem->exists ? $cartItem->quantity : 0) + $quantity;
            $cartItem->note = $note ?? $cartItem->note;
            $cartItem->save();

            return $cartItem->load('menuItem');
        });
    }

    public function updateItem(CartItem $cartItem, int $quantity, ?string $note = null): CartItem
    {
        $this->assertSessionActive($cartItem->session);

        if ($quantity < 1) {
            $cartItem->delete();

            return $cartItem;
        }

        $this->assertItemOrderable($cartItem->menuItem);

        $cartItem->update([
            'quantity' => $quantity,
            'note' => $note,
        ]);

        return $cartItem->fresh('menuItem');
    }

    public function removeItem(CartItem $cartItem): void
    {
        $this->assertSessionActive($cartItem->session);
        $cartItem->delete();
    }

    public function clear(TableSession $session): void
    {
        $session->cartItems()->delete();
    }

    /**
     * @return array{items: \Illuminate\Support\Collection, subtotal: string, count: int}
     */
    public function summary(TableSession $session): array
    {
        $items = $session->cartItems()->with('menuItem')->get();
        $subtotal = '0.00';

        foreach ($items as $item) {
            if ($item->menuItem) {
                $subtotal = MoneyCalculator::add(
                    $subtotal,
                    MoneyCalculator::multiply((string) $item->menuItem->price, $item->quantity)
                );
            }
        }

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'count' => $items->sum('quantity'),
        ];
    }

    private function assertSessionActive(TableSession $session): void
    {
        if (! $session->isActive()) {
            throw ValidationException::withMessages([
                'session' => ['انتهت جلسة الطاولة.'],
            ]);
        }
    }

    private function assertItemOrderable(MenuItem $menuItem): void
    {
        if (! $menuItem->isOrderable()) {
            throw ValidationException::withMessages([
                'menu_item' => ["{$menuItem->name} غير متاح حالياً."],
            ]);
        }
    }
}
