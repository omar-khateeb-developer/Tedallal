<?php

namespace Tests\Unit;

use App\Enums\OrderStatus;
use Tests\TestCase;

class OrderStatusTransitionTest extends TestCase
{
    public function test_pending_can_move_to_accepted(): void
    {
        $this->assertTrue(OrderStatus::Pending->canTransitionTo(OrderStatus::Accepted));
    }

    public function test_served_is_terminal(): void
    {
        $this->assertTrue(OrderStatus::Served->isTerminal());
        $this->assertEmpty(OrderStatus::Served->allowedTransitions());
    }
}
