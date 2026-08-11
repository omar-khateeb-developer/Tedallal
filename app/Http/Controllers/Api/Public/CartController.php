<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Api\ApiController;
use App\Http\Middleware\ValidateTableSession;
use App\Http\Resources\CartItemResource;
use App\Models\CartItem;
use App\Models\MenuItem;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends ApiController
{
    public function __construct(
        private readonly CartService $cartService,
    ) {}

    public function index(Request $request)
    {
        $session = ValidateTableSession::sessionFrom($request);
        $summary = $this->cartService->summary($session);

        return response()->json([
            'success' => true,
            'data' => [
                'items' => CartItemResource::collection($summary['items']),
                'subtotal' => $summary['subtotal'],
                'count' => $summary['count'],
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'menu_item_id' => ['required', 'integer', 'exists:menu_items,id'],
            'quantity' => ['sometimes', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $session = ValidateTableSession::sessionFrom($request);
        $menuItem = MenuItem::query()->findOrFail($validated['menu_item_id']);

        $cartItem = $this->cartService->addItem(
            $session,
            $menuItem,
            $validated['quantity'] ?? 1,
            $validated['note'] ?? null,
        );

        return $this->success(new CartItemResource($cartItem), 201);
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $validated = $request->validate([
            'quantity' => ['required', 'integer', 'min:0'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $session = ValidateTableSession::sessionFrom($request);

        if ($cartItem->table_session_id !== $session->id) {
            return $this->error('غير مصرح.', 403);
        }

        $updated = $this->cartService->updateItem(
            $cartItem,
            $validated['quantity'],
            $validated['note'] ?? null,
        );

        return $this->success(new CartItemResource($updated));
    }

    public function destroy(Request $request, CartItem $cartItem)
    {
        $session = ValidateTableSession::sessionFrom($request);

        if ($cartItem->table_session_id !== $session->id) {
            return $this->error('غير مصرح.', 403);
        }

        $this->cartService->removeItem($cartItem);

        return response()->json(['success' => true, 'data' => null]);
    }

    public function clear(Request $request)
    {
        $session = ValidateTableSession::sessionFrom($request);
        $this->cartService->clear($session);

        return response()->json(['success' => true, 'data' => null]);
    }
}
