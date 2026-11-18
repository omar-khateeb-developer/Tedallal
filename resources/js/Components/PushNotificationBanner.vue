<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { usePwa } from '@/composables/usePwa';

const page = usePage<any>();
const {
    pushPermission,
    isPushSubscribed,
    isPushLoading,
    subscribeToPush,
    unsubscribeFromPush
} = usePwa();

const dismissed = ref(false);
const testSuccess = ref(false);
const isTesting = ref(false);

onMounted(() => {
    if (typeof window !== 'undefined') {
        dismissed.value = localStorage.getItem('tedallal_dismiss_push_banner') === 'true';
    }
});

function dismiss() {
    dismissed.value = true;
    localStorage.setItem('tedallal_dismiss_push_banner', 'true');
}

async function handleEnablePush() {
    const vapidKey = page.props.vapidPublicKey || '';
    if (!vapidKey) {
        console.warn('VAPID public key not found in Inertia props.');
        return;
    }
    const success = await subscribeToPush(vapidKey);
    if (success) {
        dismissed.value = false;
        localStorage.removeItem('tedallal_dismiss_push_banner');
    }
}

async function sendTestNotification() {
    isTesting.value = true;
    try {
        const res = await fetch('/api/push/test', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || ''
            }
        });
        if (res.ok) {
            testSuccess.value = true;
            setTimeout(() => {
                testSuccess.value = false;
            }, 5000);
        }
    } catch (e) {
        console.error('Error triggering test notification:', e);
    } finally {
        isTesting.value = false;
    }
}
</script>

<template>
    <div v-if="!dismissed && pushPermission !== 'denied' && !isPushSubscribed" class="mb-4">
        <div class="relative overflow-hidden rounded-2xl border border-amber-500/30 bg-gradient-to-r from-amber-950/40 via-[#0e1726] to-[#0c1222] p-4 text-white shadow-lg shadow-amber-500/5">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-500/20 text-amber-400 border border-amber-500/30">
                        <span class="text-lg animate-pulse">🔔</span>
                    </div>
                    <div>
                        <h4 class="text-sm font-bold text-amber-300 font-cairo">
                            تفعيل تنبيهات الطلبات الفورية
                        </h4>
                        <p class="text-xs text-zinc-400 font-cairo mt-0.5">
                            ليصلك رنين جرس واهتزاز فور ورود أي طلب جديد أو حجز طاولة حتى عند قفل الهاتف.
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-2 self-end sm:self-center w-full sm:w-auto">
                    <button
                        type="button"
                        @click="handleEnablePush"
                        :disabled="isPushLoading"
                        class="flex-1 sm:flex-none flex items-center justify-center gap-1.5 px-4 py-2 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-zinc-950 text-xs font-bold transition shadow font-cairo disabled:opacity-50"
                    >
                        <span v-if="isPushLoading" class="animate-spin text-xs">⏳</span>
                        <span>{{ isPushLoading ? 'جارِ التفعيل...' : 'تفعيل التنبيهات الآن' }}</span>
                    </button>
                    <button
                        type="button"
                        @click="dismiss"
                        class="px-3 py-2 rounded-xl text-xs text-zinc-400 hover:text-white hover:bg-white/5 transition font-cairo"
                    >
                        لاحقاً
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Active Indicator Bar with Test Button (Optional / In Settings) -->
    <div v-else-if="isPushSubscribed && testSuccess" class="mb-4 rounded-xl border border-emerald-500/30 bg-emerald-950/30 p-3 text-xs text-emerald-400 flex items-center justify-between font-cairo">
        <span>✅ تم إرسال إشعار تجريبي بنجاح! راقب شريط إشعارات جهازك الآن.</span>
    </div>
</template>
