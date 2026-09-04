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

export function useRestaurantOrders(
    restaurantId: number | null | undefined,
    callbacks: {
        onInitialLoad: () => void;
        onOrderCreated: (order: OrderPayload) => void;
        onOrderUpdated: (order: OrderPayload) => void;
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
