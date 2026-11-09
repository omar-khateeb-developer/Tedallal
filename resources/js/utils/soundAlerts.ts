/**
 * Tedallal Professional High-Output Acoustic Sound Engine
 * نظام التنبيهات الصوتية الصاخبة وفائقة الوضوح لمنصة تدلّل
 *
 * المصمم خصيصاً ليخترق ضجيج المطابخ المزدحمة وكاونترات الكاشير وهواتف الزبائن
 * باستخدام Web Audio API مع Dynamic Compression ومضاعفة طاقة الصوت (RMS Boost)
 */

class SoundAlertEngine {
    private ctx: AudioContext | null = null;
    private masterGain: GainNode | null = null;
    private compressor: DynamicsCompressorNode | null = null;
    private isUnlocked: boolean = false;
    private hasUserInteracted: boolean = false;

    constructor() {
        if (typeof window !== 'undefined') {
            this.setupAutoUnlock();
        }
    }

    /**
     * إعداد واسترجاع AudioContext مع شبكة المعالجة الصوتية وضغط الديناميكا
     */
    private getContext(): AudioContext | null {
        if (typeof window === 'undefined') return null;

        if (!this.ctx) {
            const AudioCtxClass = window.AudioContext || (window as any).webkitAudioContext;
            if (!AudioCtxClass) return null;

            this.ctx = new AudioCtxClass();

            // 🎛️ إنشاء وحدة ضغط ديناميكي احترافية لرفع شدة الصوت وصخبه دون تشويش
            this.compressor = this.ctx.createDynamicsCompressor();
            this.compressor.threshold.setValueAtTime(-14, this.ctx.currentTime); // تفعيل مبكر للضغط
            this.compressor.knee.setValueAtTime(10, this.ctx.currentTime);
            this.compressor.ratio.setValueAtTime(12, this.ctx.currentTime);      // ضغط عالٍ لمضاعفة الطاقة الصوتية
            this.compressor.attack.setValueAtTime(0.002, this.ctx.currentTime);  // استجابة فورية
            this.compressor.release.setValueAtTime(0.12, this.ctx.currentTime);

            // 🔊 مضخم الصوت الرئيسي
            this.masterGain = this.ctx.createGain();
            this.masterGain.gain.setValueAtTime(1.0, this.ctx.currentTime);

            // الربط: Master Gain -> Compressor -> Destination (مكبرات الصوت)
            this.masterGain.connect(this.compressor);
            this.compressor.connect(this.ctx.destination);
        }

        if (this.ctx.state === 'suspended') {
            this.ctx.resume().catch(() => {});
        }

        return this.ctx;
    }

    /**
     * فك تعليق الصوت تلقائياً عند أول تفاعل للمستخدم على أي شاشة
     */
    private setupAutoUnlock() {
        const unlockEvents = ['click', 'touchstart', 'touchend', 'pointerdown', 'keydown'];

        const onFirstInteraction = () => {
            if (this.hasUserInteracted) return;
            this.hasUserInteracted = true;
            this.unlock();

            unlockEvents.forEach((ev) => {
                window.removeEventListener(ev, onFirstInteraction, true);
            });
        };

        unlockEvents.forEach((ev) => {
            window.addEventListener(ev, onFirstInteraction, { capture: true, once: true });
        });
    }

    /**
     * تشغيل نغمة صامتة لحظية لفك قفل السياسات الصارمة في Safari / iOS / Chrome
     */
    public unlock(): boolean {
        try {
            const ctx = this.getContext();
            if (!ctx) return false;

            if (ctx.state === 'suspended') {
                ctx.resume();
            }

            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            gain.gain.value = 0.0001;
            osc.connect(gain);
            gain.connect(ctx.destination);
            osc.start();
            osc.stop(ctx.currentTime + 0.01);

            this.isUnlocked = true;
            return true;
        } catch (e) {
            console.warn('[SoundEngine] Unlock error:', e);
            return false;
        }
    }

    /**
     * تشغيل هزة فيزيائية لأجهزة الموبايل اللمسية
     */
    private vibrate(pattern: number | number[]) {
        if (typeof window !== 'undefined' && 'navigator' in window && navigator.vibrate) {
            try {
                navigator.vibrate(pattern);
            } catch {}
        }
    }

    /**
     * ============================================================
     * 🍳 1. شاشة المطبخ (Kitchen Screen - High Clamor Urgent Alarm)
     * ============================================================
     * صوت صاخب، ثاقب ومزدوج النغمات، مصمم خصيصاً ليخترق ضجيج
     * مراوح الشفط والمقالي وغسالات الأطباق في المطابخ المزدحمة.
     */
    public playKitchenAlert() {
        const ctx = this.getContext();
        if (!ctx || !this.masterGain) return;

        try {
            this.vibrate([300, 100, 300, 100, 500]); // هزة قوية للشيفات

            const now = ctx.currentTime;

            // دالة تشغيل نبضة صوتية مشبعة بالهارمونيات (Triangle + Sine + Harmonics)
            const playPenetratingBurst = (start: number, baseFreq: number, duration: number, isFinal: boolean = false) => {
                // 1. المذبذب الرئيسي (Triangle: صوت معدني حاد ونافذ)
                const oscMain = ctx.createOscillator();
                oscMain.type = 'triangle';
                oscMain.frequency.setValueAtTime(baseFreq, start);

                // 2. مذبذب الأوكتاف العالي (Harmonic Overtone: يقطع الضوضاء المحيطة)
                const oscOvertone = ctx.createOscillator();
                oscOvertone.type = 'sawtooth';
                oscOvertone.frequency.setValueAtTime(baseFreq * 2, start);

                // فلتر لإبقاء الصوت رناناً دون تشويش إلكتروني فج
                const filter = ctx.createBiquadFilter();
                filter.type = 'lowpass';
                filter.frequency.setValueAtTime(4500, start);

                // Gain فرعي للنبضة
                const burstGain = ctx.createGain();
                burstGain.gain.setValueAtTime(0, start);
                burstGain.gain.linearRampToValueAtTime(isFinal ? 1.0 : 0.85, start + 0.015); // هجوم فوري قوي
                burstGain.gain.exponentialRampToValueAtTime(0.001, start + duration);

                // Gain مخفف لمذبذب الـ Sawtooth
                const overtoneGain = ctx.createGain();
                overtoneGain.gain.setValueAtTime(0.35, start);

                oscMain.connect(burstGain);
                oscOvertone.connect(overtoneGain);
                overtoneGain.connect(filter);
                filter.connect(burstGain);

                burstGain.connect(this.masterGain!);

                oscMain.start(start);
                oscOvertone.start(start);
                oscMain.stop(start + duration);
                oscOvertone.stop(start + duration);
            };

            // 🔔 تسلسل إنذار المطبخ الصاخب:
            // نبضة 1: 1046Hz (C6) -> نبضة 2: 1318Hz (E6) -> نبضة 3: 1568Hz (G6) قوية متلاشية
            playPenetratingBurst(now, 1046, 0.16);
            playPenetratingBurst(now + 0.18, 1318, 0.16);
            playPenetratingBurst(now + 0.36, 1760, 0.40, true);

            // تكرار تأكيدي (Echo Burst) بعد نصف ثانية للتأكد من انتباه الشيف
            playPenetratingBurst(now + 0.85, 1318, 0.14);
            playPenetratingBurst(now + 1.02, 1760, 0.45, true);

        } catch (e) {
            console.warn('[SoundEngine] Kitchen alert error:', e);
        }
    }

    /**
     * ============================================================
     * 💵 2. شاشة الكاشير (Cashier Screen - High-Impact Royal POS Chime)
     * ============================================================
     * صوت جرس ملكي فخم وعالي القوة والوضوح، يفرض حضوره فوراً
     * عند استلام أي طلب جديد عبر كاونتر الكاشير.
     */
    public playCashierAlert() {
        const ctx = this.getContext();
        if (!ctx || !this.masterGain) return;

        try {
            this.vibrate([200, 80, 250]);

            const now = ctx.currentTime;

            // نغمات جرس الكاشير الملكي (A5 -> C#6 -> E6 -> A6)
            const playChimeNote = (start: number, freq: number, duration: number, vol: number) => {
                const osc = ctx.createOscillator();
                const subOsc = ctx.createOscillator();

                osc.type = 'triangle'; // صوت جرس كاشير نحاسي
                subOsc.type = 'sine';  // نغمة أساسية دافئة وعميقة

                osc.frequency.setValueAtTime(freq, start);
                subOsc.frequency.setValueAtTime(freq * 0.5, start);

                const gain = ctx.createGain();
                gain.gain.setValueAtTime(0, start);
                gain.gain.linearRampToValueAtTime(vol, start + 0.012); // ضربة جرس حادة ومسموعة
                gain.gain.exponentialRampToValueAtTime(0.001, start + duration);

                osc.connect(gain);
                subOsc.connect(gain);
                gain.connect(this.masterGain!);

                osc.start(start);
                subOsc.start(start);
                osc.stop(start + duration);
                subOsc.stop(start + duration);
            };

            // تسلسل جرس الكاشير الملكي ذو الحضور القوي:
            playChimeNote(now, 987, 0.22, 0.75);        // B5
            playChimeNote(now + 0.14, 1318, 0.28, 0.85); // E6
            playChimeNote(now + 0.30, 1975, 0.65, 0.95); // B6 (رنين ذهبي ممتد)

        } catch (e) {
            console.warn('[SoundEngine] Cashier alert error:', e);
        }
    }

    /**
     * ============================================================
     * 📱 3. شاشة الزبون (Customer Screen - High-Clarity Announcements)
     * ============================================================
     * رنات وصول الطلب وجاهزية الاستلام على هواتف الزبائن في الصالة
     */
    public playCustomerAlert(type: 'ready' | 'status' = 'ready') {
        const ctx = this.getContext();
        if (!ctx || !this.masterGain) return;

        try {
            const now = ctx.currentTime;

            if (type === 'ready') {
                // 🛎️ رنة "طلبك جاهز للاستلام!" (Fanfare Chime عالي الصخب والبهجة)
                this.vibrate([400, 150, 400, 150, 600]);

                const notes = [
                    { f: 880, d: 0.15, g: 0.8 },   // A5
                    { f: 1108, d: 0.15, g: 0.85 }, // C#6
                    { f: 1320, d: 0.18, g: 0.9 },  // E6
                    { f: 1760, d: 0.80, g: 1.0 },  // A6 (رنين جرس الفندق الفخم)
                ];

                let offset = 0;
                notes.forEach((n, idx) => {
                    const osc = ctx.createOscillator();
                    const overtone = ctx.createOscillator();
                    osc.type = 'triangle';
                    overtone.type = 'sine';

                    osc.frequency.setValueAtTime(n.f, now + offset);
                    overtone.frequency.setValueAtTime(n.f * 2, now + offset);

                    const gain = ctx.createGain();
                    gain.gain.setValueAtTime(0, now + offset);
                    gain.gain.linearRampToValueAtTime(n.g, now + offset + 0.015);
                    gain.gain.exponentialRampToValueAtTime(0.001, now + offset + n.d);

                    osc.connect(gain);
                    overtone.connect(gain);
                    gain.connect(this.masterGain!);

                    osc.start(now + offset);
                    overtone.start(now + offset);
                    osc.stop(now + offset + n.d);
                    overtone.stop(now + offset + n.d);

                    offset += idx === notes.length - 1 ? 0 : 0.12;
                });
            } else {
                // 🔔 رنة تغير الحالة العامة (بدء التحضير / تم الاستلام)
                this.vibrate([200, 100, 200]);

                const playSoftDing = (t: number, f: number, d: number) => {
                    const osc = ctx.createOscillator();
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(f, t);

                    const gain = ctx.createGain();
                    gain.gain.setValueAtTime(0, t);
                    gain.gain.linearRampToValueAtTime(0.75, t + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.001, t + d);

                    osc.connect(gain);
                    gain.connect(this.masterGain!);

                    osc.start(t);
                    osc.stop(t + d);
                };

                playSoftDing(now, 784, 0.25);        // G5
                playSoftDing(now + 0.16, 1046, 0.45); // C6
            }
        } catch (e) {
            console.warn('[SoundEngine] Customer alert error:', e);
        }
    }
}

// تصدير نسخة عامة مفردة (Singleton) تستخدم في جميع شاشات التطبيق
export const soundAlerts = new SoundAlertEngine();
export default soundAlerts;

