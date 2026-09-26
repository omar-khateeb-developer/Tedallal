<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';

const lang = ref<'ar' | 'en'>('ar');

onMounted(() => {
    const browserLang = navigator.language?.toLowerCase();
    if (browserLang && !browserLang.startsWith('ar')) {
        lang.value = 'en';
    }
});

const content = {
    ar: {
        title: 'الموقع تحت الصيانة',
        headline: 'نعمل على تحسين خدماتك',
        subheadline: 'الموقع تحت الصيانة مؤقتاً',
        body: 'نقوم حالياً بإجراء تحديثات وتحسينات على المنصة لنقدم لك تجربة أفضل. سنعود قريباً جداً.',
        returnMsg: 'شكراً لصبرك وتفهمك.',
        badge: 'قيد الصيانة',
        langBtn: 'English',
    },
    en: {
        title: 'Site Under Maintenance',
        headline: 'We\'re Improving Your Experience',
        subheadline: 'The site is temporarily under maintenance',
        body: 'We are currently performing updates and improvements to the platform to bring you a better experience. We\'ll be back very soon.',
        returnMsg: 'Thank you for your patience and understanding.',
        badge: 'Under Maintenance',
        langBtn: 'العربية',
    },
};

const toggleLang = () => {
    lang.value = lang.value === 'ar' ? 'en' : 'ar';
};
</script>

<template>
    <Head :title="content[lang].title" />

    <div
        class="maintenance-page"
        :dir="lang === 'ar' ? 'rtl' : 'ltr'"
        :lang="lang"
    >
        <div class="bg-overlay">
            <div class="circle circle-1"></div>
            <div class="circle circle-2"></div>
            <div class="circle circle-3"></div>
            <div class="grid-pattern"></div>
        </div>

        <button id="lang-toggle-btn" class="lang-toggle" @click="toggleLang">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="icon">
                <path stroke-linecap="round" stroke-linejoin="round" d="m10.5 21 5.25-11.25L21 21m-9-3h7.5M3 5.621a48.474 48.474 0 0 1 6-.371m0 0c1.12 0 2.233.038 3.334.114M9 5.25V3m3.334 2.364C11.176 10.658 7.69 15.08 3 17.502m9.334-12.138c.896.061 1.785.147 2.666.257m-4.589 8.495a18.023 18.023 0 0 1-3.827-5.802" />
            </svg>
            {{ content[lang].langBtn }}
        </button>

        <div class="content-wrapper">
            <div class="icon-container">
                <div class="icon-pulse"></div>
                <div class="icon-ring"></div>
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.2" stroke="currentColor" class="main-icon">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085m-1.745 1.437L5.909 7.5H4.5L2.25 3.75l1.5-1.5L7.5 4.5v1.409l4.26 4.26m-1.745 1.437 1.745-1.437m6.615 8.206L15.75 15.75M4.867 19.125h.008v.008h-.008v-.008Z" />
                </svg>
            </div>

            <div class="status-badge">
                <span class="badge-dot"></span>
                {{ content[lang].badge }}
            </div>

            <h1 class="headline">{{ content[lang].headline }}</h1>
            <h2 class="subheadline">{{ content[lang].subheadline }}</h2>

            <p class="body-text">{{ content[lang].body }}</p>

            <div class="divider">
                <span class="divider-line"></span>
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="divider-icon">
                    <path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd" />
                </svg>
                <span class="divider-line"></span>
            </div>

            <p class="return-msg">{{ content[lang].returnMsg }}</p>

            <div class="brand">
                <span class="brand-text">تدلّل</span>
                <span class="brand-dot">·</span>
                <span class="brand-text-en">Tedallal</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&family=Inter:wght@300;400;500;600;700&display=swap');

* { box-sizing: border-box; margin: 0; padding: 0; }

.maintenance-page {
    min-height: 100vh;
    background: linear-gradient(135deg, #0f0c29 0%, #302b63 50%, #24243e 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Tajawal', 'Inter', sans-serif;
    position: relative;
    overflow: hidden;
    padding: 2rem;
}

.bg-overlay { position: fixed; inset: 0; pointer-events: none; z-index: 0; }

.circle {
    position: absolute;
    border-radius: 50%;
    filter: blur(80px);
    opacity: 0.15;
    animation: float 8s ease-in-out infinite;
}
.circle-1 { width: 600px; height: 600px; background: radial-gradient(circle, #7c3aed, transparent); top: -200px; right: -100px; }
.circle-2 { width: 500px; height: 500px; background: radial-gradient(circle, #2563eb, transparent); bottom: -150px; left: -100px; animation-delay: 3s; }
.circle-3 { width: 400px; height: 400px; background: radial-gradient(circle, #db2777, transparent); top: 50%; left: 50%; transform: translate(-50%, -50%); animation-delay: 1.5s; }

.grid-pattern {
    position: absolute; inset: 0;
    background-image: linear-gradient(rgba(255,255,255,0.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,0.03) 1px, transparent 1px);
    background-size: 60px 60px;
}

@keyframes float {
    0%, 100% { transform: translate(0, 0) scale(1); }
    33% { transform: translate(30px, -20px) scale(1.05); }
    66% { transform: translate(-20px, 20px) scale(0.95); }
}

.lang-toggle {
    position: fixed; top: 1.5rem; right: 1.5rem; z-index: 100;
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.5rem 1.25rem;
    background: rgba(255,255,255,0.1); backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.2); border-radius: 9999px;
    color: rgba(255,255,255,0.9); font-size: 0.875rem; font-family: inherit; font-weight: 500;
    cursor: pointer; transition: all 0.3s ease;
}
[dir="ltr"] .lang-toggle { right: auto; left: 1.5rem; }
.lang-toggle:hover { background: rgba(255,255,255,0.18); border-color: rgba(255,255,255,0.35); transform: translateY(-1px); }
.lang-toggle .icon { width: 1rem; height: 1rem; }

.content-wrapper {
    position: relative; z-index: 10;
    display: flex; flex-direction: column; align-items: center; text-align: center;
    max-width: 600px; width: 100%;
}

.icon-container {
    position: relative; width: 120px; height: 120px;
    display: flex; align-items: center; justify-content: center;
    margin-bottom: 2rem;
}
.icon-pulse { position: absolute; inset: 0; border-radius: 50%; background: linear-gradient(135deg, #7c3aed33, #2563eb33); animation: pulse 2s ease-in-out infinite; }
.icon-ring { position: absolute; inset: -12px; border-radius: 50%; border: 2px solid rgba(124,58,237,0.3); animation: spin 8s linear infinite; }

@keyframes pulse { 0%, 100% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.15); opacity: 0.7; } }
@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }

.main-icon {
    width: 64px; height: 64px; color: #a78bfa;
    filter: drop-shadow(0 0 20px rgba(167,139,250,0.5));
    animation: glow 3s ease-in-out infinite;
}
@keyframes glow {
    0%, 100% { filter: drop-shadow(0 0 15px rgba(167,139,250,0.4)); }
    50% { filter: drop-shadow(0 0 30px rgba(167,139,250,0.8)); }
}

.status-badge {
    display: inline-flex; align-items: center; gap: 0.5rem;
    padding: 0.375rem 1rem;
    background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); border-radius: 9999px;
    color: #fca5a5; font-size: 0.8125rem; font-weight: 600; letter-spacing: 0.05em; text-transform: uppercase;
    margin-bottom: 1.75rem;
}
.badge-dot { width: 8px; height: 8px; border-radius: 50%; background: #ef4444; animation: blink 1.5s ease-in-out infinite; }
@keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.2; } }

.headline {
    font-size: clamp(1.75rem, 5vw, 2.75rem); font-weight: 800; line-height: 1.2; margin-bottom: 0.75rem;
    background: linear-gradient(135deg, #ffffff 0%, #c4b5fd 100%);
    -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text;
}
.subheadline { font-size: clamp(1rem, 2.5vw, 1.25rem); font-weight: 500; color: rgba(255,255,255,0.6); margin-bottom: 1.5rem; }
.body-text { font-size: 1rem; line-height: 1.8; color: rgba(255,255,255,0.55); max-width: 480px; margin-bottom: 2rem; }

.divider { display: flex; align-items: center; gap: 0.75rem; width: 100%; max-width: 320px; margin-bottom: 1.5rem; }
.divider-line { flex: 1; height: 1px; background: linear-gradient(90deg, transparent, rgba(167,139,250,0.3), transparent); }
.divider-icon { width: 1.125rem; height: 1.125rem; flex-shrink: 0; color: rgba(167,139,250,0.5); }

.return-msg { font-size: 0.9375rem; color: rgba(255,255,255,0.45); margin-bottom: 2.5rem; font-style: italic; }

.brand {
    display: flex; align-items: center; gap: 0.5rem;
    padding: 0.625rem 1.5rem;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); border-radius: 9999px;
    backdrop-filter: blur(8px);
}
.brand-text, .brand-text-en { font-size: 0.9375rem; font-weight: 700; color: rgba(255,255,255,0.75); letter-spacing: 0.02em; }
.brand-dot { color: rgba(167,139,250,0.6); font-size: 1.25rem; line-height: 1; }

@media (max-width: 480px) {
    .icon-container { width: 90px; height: 90px; }
    .main-icon { width: 48px; height: 48px; }
}
</style>
