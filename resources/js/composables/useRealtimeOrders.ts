import { onMounted, onUnmounted } from 'vue';

interface OrderPayload {
    id: number;
    order_number: string;
    status: string;
    status_label: string;
    total: string;
    table?: { name: string };
}

type OrderEventHandler = (order: OrderPayload) => void;

export interface ServiceCallPayload {
    id: number;
    table_id: number;
    table_name: string;
    type: 'waiter_call' | 'bill_request';
    status: string;
    payment_method?: string | null;
    reason?: string | null;
    notes?: string | null;
    created_at?: string;
}

export function useRestaurantOrders(
    restaurantId: number | null | undefined,
    callbacks: {
        onInitialLoad: () => void;
        onOrderCreated: (order: OrderPayload) => void;
        onOrderUpdated: (order: OrderPayload) => void;
        onServiceCalled?: (payload: { service_call: ServiceCallPayload; table: any }) => void;
        onServiceAttended?: (payload: { service_call_id: number; table_id: number; status: string }) => void;
    },
) {
    let pollTimer: ReturnType<typeof setInterval> | null = null;

    onMounted(() => {
        callbacks.onInitialLoad();
        // Fallback polling every 60 seconds
        pollTimer = setInterval(callbacks.onInitialLoad, 60000);

        if (restaurantId && window.Echo) {
            window.Echo.private(`restaurant.${restaurantId}.orders`)
                .listen('.order.created', (payload: { order: OrderPayload }) => {
                    callbacks.onOrderCreated(payload.order);
                })
                .listen('.order.status_changed', (payload: { order: OrderPayload }) => {
                    callbacks.onOrderUpdated(payload.order);
                })
                .listen('.table.service_called', (payload: { service_call: ServiceCallPayload; table: any }) => {
                    if (callbacks.onServiceCalled) {
                        callbacks.onServiceCalled(payload);
                    }
                })
                .listen('.table.service_attended', (payload: { service_call_id: number; table_id: number; status: string }) => {
                    if (callbacks.onServiceAttended) {
                        callbacks.onServiceAttended(payload);
                    }
                });
        }
    });

    onUnmounted(() => {
        if (pollTimer) clearInterval(pollTimer);

        if (restaurantId && window.Echo) {
            window.Echo.leave(`restaurant.${restaurantId}.orders`);
        }
    });
}

export function useOrderStatusChannel(
    orderNumber: string,
    onStatusChange: OrderEventHandler,
) {
    let pollTimer: ReturnType<typeof setInterval> | null = null;

    onMounted(() => {
        if (window.Echo) {
            window.Echo.channel(`order.${orderNumber}`).listen(
                '.order.status_changed',
                (payload: { order: OrderPayload }) => {
                    onStatusChange(payload.order);
                },
            );
        }

        pollTimer = setInterval(() => {
            // Polling fallback handled by parent component
        }, 5000);
    });

    onUnmounted(() => {
        if (pollTimer) clearInterval(pollTimer);

        if (window.Echo) {
            window.Echo.leave(`order.${orderNumber}`);
        }
    });
}
