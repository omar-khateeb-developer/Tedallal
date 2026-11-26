<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

const page = usePage();

const maintenanceMode = ref(false);
const maintenanceLoading = ref(false);
const maintenanceMessage = ref('');
const maintenanceSuccess = ref(false);
const maintenanceStatusLoaded = ref(false);

const canToggleMaintenance = computed(() => {
    const user = (page.props.auth as any)?.user;
    if (!user) return false;
    return Boolean(user.can_toggle_maintenance || user.role === 'super_admin');
});

const maintenanceBtnClass = computed(() => {
    if (maintenanceMode.value) {
        return 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500';
    }
    return 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500';
});

const fetchMaintenanceStatus = async () => {
    try {
        const response = await axios.get('/api/super-admin/maintenance');
        maintenanceMode.value = response.data.data?.maintenance_mode ?? false;
        maintenanceStatusLoaded.value = true;
    } catch (e) {
        console.error('Failed to fetch maintenance status');
    }
};

const toggleMaintenance = async () => {
    const confirmMsg = maintenanceMode.value
        ? 'هل تريد إيقاف وضع الصيانة وتشغيل الموقع؟\nDo you want to disable maintenance mode?'
        : 'هل تريد تفعيل وضع الصيانة وإيقاف الموقع للعموم؟\nDo you want to enable maintenance mode and take the site offline?';

    if (!confirm(confirmMsg)) return;

    maintenanceLoading.value = true;
    maintenanceMessage.value = '';
    try {
        const response = await axios.post('/api/super-admin/maintenance/toggle', {
            enabled: !maintenanceMode.value,
        });
        maintenanceMode.value = response.data.data?.maintenance_mode ?? false;
        maintenanceMessage.value = response.data.data?.message ?? '';
        maintenanceSuccess.value = true;
        setTimeout(() => {
            maintenanceMessage.value = '';
            maintenanceSuccess.value = false;
        }, 3000);
    } catch (e) {
        maintenanceMessage.value = 'حدث خطأ أثناء تغيير حالة الصيانة';
        maintenanceSuccess.value = false;
    } finally {
        maintenanceLoading.value = false;
    }
};

onMounted(() => {
    if (canToggleMaintenance.value) {
        fetchMaintenanceStatus();
    }
});
</script>

<template>
    <div v-if="canToggleMaintenance" class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
        <div class="p-6" dir="rtl">
            <div class="flex items-center justify-between flex-wrap gap-4">
                <div class="flex items-center gap-4">
                    <div
                        class="p-3 rounded-xl"
                        :class="maintenanceMode ? 'bg-red-100 text-red-600' : 'bg-green-100 text-green-600'"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                            <path v-if="!maintenanceMode" stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            <path v-else stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-800 text-base">وضع الصيانة | Maintenance Mode</h3>
                        <div class="flex items-center gap-2 mt-1">
                            <span
                                class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                :class="maintenanceMode
                                    ? 'bg-red-100 text-red-700'
                                    : 'bg-green-100 text-green-700'"
                            >
                                <span
                                    class="inline-block w-1.5 h-1.5 rounded-full"
                                    :class="maintenanceMode ? 'bg-red-500 animate-pulse' : 'bg-green-500'"
                                ></span>
                                <template v-if="maintenanceStatusLoaded">
                                    {{ maintenanceMode ? 'الموقع تحت الصيانة · Under Maintenance' : 'الموقع يعمل بشكل طبيعي · Site is Online' }}
                                </template>
                                <template v-else>جاري التحميل...</template>
                            </span>
                        </div>
                        <Transition
                            enter-active-class="transition ease-out duration-200"
                            enter-from-class="opacity-0 translate-y-1"
                            enter-to-class="opacity-100 translate-y-0"
                            leave-active-class="transition ease-in duration-150"
                            leave-from-class="opacity-100"
                            leave-to-class="opacity-0"
                        >
                            <p
                                v-if="maintenanceMessage"
                                class="text-xs mt-1 font-medium"
                                :class="maintenanceSuccess ? 'text-green-600' : 'text-red-600'"
                            >{{ maintenanceMessage }}</p>
                        </Transition>
                    </div>
                </div>
                <button
                    id="maintenance-toggle-btn"
                    @click="toggleMaintenance"
                    :disabled="maintenanceLoading || !maintenanceStatusLoaded"
                    class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl font-medium text-sm transition-all duration-200 shadow-sm focus:ring-2 focus:ring-offset-2 disabled:opacity-50"
                    :class="maintenanceBtnClass"
                >
                    <svg v-if="maintenanceLoading" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <svg v-else xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.636 5.636a9 9 0 1 0 12.728 0M12 3v9" />
                    </svg>
                    <span v-if="maintenanceLoading">جاري التغيير...</span>
                    <span v-else-if="maintenanceMode">تشغيل الموقع · Bring Online</span>
                    <span v-else>إيقاف الموقع للصيانة · Enable Maintenance</span>
                </button>
            </div>
        </div>
    </div>
</template>
