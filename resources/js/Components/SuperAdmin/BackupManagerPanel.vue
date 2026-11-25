<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';

interface BackupItem {
    filename: string;
    size_bytes: number;
    size_formatted: string;
    created_at: string;
    created_at_human: string;
}

const page = usePage();
const canManageBackups = computed(() => {
    const user = (page.props.auth as any)?.user;
    return !!user && user.email === 'super@tedallal.test';
});

const backups = ref<BackupItem[]>([]);
const backupsLoading = ref(false);
const backupCreating = ref(false);
const backupMessage = ref('');
const backupSuccess = ref(false);
const showBackupsList = ref(false);

const fetchBackups = async () => {
    backupsLoading.value = true;
    try {
        const res = await axios.get('/api/super-admin/backups');
        backups.value = res.data.data?.backups ?? [];
    } catch (e: any) {
        console.error('Failed to load backups', e);
    } finally {
        backupsLoading.value = false;
    }
};

const createBackupNow = async (includeMedia = true) => {
    backupCreating.value = true;
    backupMessage.value = '';
    try {
        const res = await axios.post('/api/super-admin/backups/create', { include_media: includeMedia });
        backupSuccess.value = true;
        backupMessage.value = res.data.data?.message ?? 'تم إنشاء النسخة الاحتياطية بنجاح.';
        await fetchBackups();
    } catch (e: any) {
        backupSuccess.value = false;
        backupMessage.value = e.response?.data?.message ?? 'فشل إنشاء النسخة الاحتياطية.';
    } finally {
        backupCreating.value = false;
        setTimeout(() => { backupMessage.value = ''; }, 6000);
    }
};

const deleteBackup = async (filename: string) => {
    if (!confirm(`هل أنت متأكد من حذف النسخة الاحتياطية (${filename}) نهائياً؟`)) return;
    try {
        await axios.delete(`/api/super-admin/backups/${filename}`);
        await fetchBackups();
    } catch (e: any) {
        alert(e.response?.data?.message ?? 'تعذر حذف النسخة');
    }
};

onMounted(() => {
    if (canManageBackups.value) {
        fetchBackups();
    }
});
</script>

<template>
    <div v-if="canManageBackups" class="bg-white dark:bg-[#0a0f1d] overflow-hidden shadow-sm sm:rounded-2xl border border-stone-200 dark:border-slate-800">
        <div class="p-6" dir="rtl">
            <div class="flex items-center justify-between flex-wrap gap-4 border-b border-stone-100 dark:border-slate-800 pb-5">
                <div class="flex items-center gap-4">
                    <div class="p-3.5 rounded-2xl bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300">
                        <span class="text-2xl">💾</span>
                    </div>
                    <div>
                        <h3 class="font-bold text-gray-900 dark:text-white text-base flex items-center gap-2">
                            <span>النسخ الاحتياطي والأمان السحابي</span>
                            <span class="text-xs font-normal text-stone-500">| Automated System Backups</span>
                        </h3>
                        <p class="text-xs text-stone-500 dark:text-stone-400 mt-0.5">
                            أرشفة شاملة لقاعدة البيانات والملفات مع جدولة تلقائية يومية وإمكانية التحميل المباشر لجهازك الشخصي.
                        </p>
                        <div class="flex items-center gap-3 mt-2 flex-wrap text-xs">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full font-medium bg-stone-100 dark:bg-slate-800 text-stone-700 dark:text-slate-300">
                                <span>عدد النسخ:</span>
                                <strong class="font-bold">{{ backups.length }}</strong>
                            </span>
                            <span v-if="backups.length > 0" class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full font-medium bg-green-50 dark:bg-green-950/40 text-green-700 dark:text-green-300">
                                <span>آخر نسخة:</span>
                                <span>{{ backups[0].created_at_human }} ({{ backups[0].size_formatted }})</span>
                            </span>
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-stone-400 text-[11px]">
                                ⏰ مجدولة تلقائياً: يومياً 3:00 ص
                            </span>
                        </div>
                        <p v-if="backupMessage" class="text-xs mt-2 font-medium" :class="backupSuccess ? 'text-green-600' : 'text-red-600'">
                            {{ backupMessage }}
                        </p>
                    </div>
                </div>
                <div class="flex items-center gap-2.5 flex-wrap">
                    <button
                        @click="showBackupsList = !showBackupsList"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-xs font-medium border border-stone-300 dark:border-slate-700 hover:bg-stone-50 dark:hover:bg-slate-800 transition"
                    >
                        <span>{{ showBackupsList ? 'إخفاء الأرشيف' : 'عرض النسخ المحفوظة' }}</span>
                        <svg class="size-4 transition-transform" :class="showBackupsList ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>
                    <button
                        @click="createBackupNow(true)"
                        :disabled="backupCreating"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl text-xs font-bold text-white bg-indigo-600 hover:bg-indigo-700 transition shadow-sm disabled:opacity-50"
                    >
                        <svg v-if="backupCreating" class="animate-spin h-3.5 w-3.5" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                        </svg>
                        <span v-if="backupCreating">جاري إنشاء النسخة...</span>
                        <span v-else>📦 إنشاء نسخة احتياطية فورية الآن</span>
                    </button>
                </div>
            </div>

            <div v-if="showBackupsList" class="mt-4 pt-2">
                <div v-if="backupsLoading" class="text-center py-6 text-xs text-stone-500">
                    جاري تحميل قائمة النسخ...
                </div>
                <div v-else-if="backups.length === 0" class="text-center py-6 text-xs text-stone-500">
                    لا توجد نسخ احتياطية محفوظة حالياً. اضغط على الزر أعلاه لإنشاء أول نسخة.
                </div>
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-xs text-right">
                        <thead>
                            <tr class="border-b border-stone-200 dark:border-slate-800 text-stone-500">
                                <th class="py-2 px-3 font-semibold">اسم الملف</th>
                                <th class="py-2 px-3 font-semibold">الحجم</th>
                                <th class="py-2 px-3 font-semibold">تاريخ الإنشاء</th>
                                <th class="py-2 px-3 font-semibold text-center">الإجراءات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-stone-100 dark:divide-slate-800/60">
                            <tr v-for="b in backups" :key="b.filename" class="hover:bg-stone-50 dark:hover:bg-slate-800/40 transition">
                                <td class="py-2.5 px-3 font-mono text-[11px] text-stone-700 dark:text-slate-300">
                                    {{ b.filename }}
                                </td>
                                <td class="py-2.5 px-3 font-semibold text-stone-800 dark:text-stone-200">
                                    {{ b.size_formatted }}
                                </td>
                                <td class="py-2.5 px-3 text-stone-500">
                                    {{ b.created_at }} ({{ b.created_at_human }})
                                </td>
                                <td class="py-2.5 px-3 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <a
                                            :href="`/super-admin/backups/download/${b.filename}`"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-indigo-50 hover:bg-indigo-100 text-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300 text-[11px] font-semibold transition"
                                        >
                                            <span>تحميل لجهازي 📥</span>
                                        </a>
                                        <button
                                            @click="deleteBackup(b.filename)"
                                            type="button"
                                            class="inline-flex items-center px-2 py-1 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 dark:bg-red-950/40 dark:text-red-400 text-[11px] transition"
                                        >
                                            حذف
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</template>
