import express from 'express';
import cors from 'cors';
import dotenv from 'dotenv';
import pino from 'pino';
import qrcode from 'qrcode';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import makeWASocket, {
    useMultiFileAuthState,
    DisconnectReason,
    fetchLatestBaileysVersion,
} from '@whiskeysockets/baileys';

dotenv.config();

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const PORT = parseInt(process.env.PORT || '8082', 10);
const HOST = process.env.HOST || '127.0.0.1';
const GATEWAY_SECRET = process.env.GATEWAY_SECRET || 'tedallal_wa_gateway_2026_secure_key';
const LARAVEL_WEBHOOK_URL = process.env.LARAVEL_WEBHOOK_URL || 'http://127.0.0.1:8000/api/whatsapp/webhook';

const logger = pino({ level: process.env.LOG_LEVEL || 'info' });
const baileysLogger = pino({ level: 'silent' });

const sessions = new Map();

/**
 * Normalize session identifier: either 'platform' (for Super Admin / Platform Owner) or numeric restaurant ID
 */
function normalizeSessionId(id) {
    if (!id) return 'platform';
    const str = String(id).trim().toLowerCase();
    if (str === 'platform' || str === 'super_admin' || str === 'superadmin' || str === '0') {
        return 'platform';
    }
    const num = parseInt(str, 10);
    return isNaN(num) ? str : num;
}

function getSessionDir(sessionId) {
    const sId = normalizeSessionId(sessionId);
    const folderName = (sId === 'platform') ? 'platform' : `restaurant_${sId}`;
    const dir = path.join(__dirname, 'storage', 'sessions', folderName);
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
    return dir;
}

function cleanSessionDir(sessionId) {
    const sId = normalizeSessionId(sessionId);
    const folderName = (sId === 'platform') ? 'platform' : `restaurant_${sId}`;
    const dir = path.join(__dirname, 'storage', 'sessions', folderName);
    if (fs.existsSync(dir)) {
        try {
            fs.rmSync(dir, { recursive: true, force: true });
        } catch (err) {
            logger.error({ err, sessionId: sId }, 'Failed to remove session directory');
        }
    }
}

async function notifyLaravel(payload) {
    if (!LARAVEL_WEBHOOK_URL) return;
    try {
        const res = await fetch(LARAVEL_WEBHOOK_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Gateway-Secret': GATEWAY_SECRET,
            },
            body: JSON.stringify(payload),
        });
        if (!res.ok) {
            logger.warn({ status: res.status, payload }, 'Laravel webhook returned non-200');
        }
        return await res.json().catch(() => null);
    } catch (err) {
        logger.warn({ err: err.message, event: payload?.event }, 'Failed to notify Laravel webhook');
        return null;
    }
}

async function startSession(sessionId) {
    const sId = normalizeSessionId(sessionId);
    const existing = sessions.get(sId);

    if (existing?.sock && existing.status === 'connected') {
        return existing;
    }

    const sessionDir = getSessionDir(sId);
    const { state, saveCreds } = await useMultiFileAuthState(sessionDir);
    const { version } = await fetchLatestBaileysVersion().catch(() => ({ version: [2, 3000, 1015901307] }));

    const sessionData = {
        id: sId,
        sock: null,
        status: 'connecting',
        qr: null,
        phone: null,
        connectedAt: null,
        reconnectAttempts: 0,
        keepAliveTimer: null,
    };
    sessions.set(sId, sessionData);

    const isPlatform = (sId === 'platform');
    const browserTitle = isPlatform ? 'Tedallal Executive' : 'Tedallal Restaurant';

    const sock = makeWASocket({
        version,
        auth: state,
        logger: baileysLogger,
        printQRInTerminal: false,
        browser: [browserTitle, 'Chrome', '124.0.0'],
        syncFullHistory: false,
        markOnlineOnConnect: true,
        connectTimeoutMs: 60000,
        keepAliveIntervalMs: 25000,
    });

    sessionData.sock = sock;

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            try {
                const qrDataUrl = await qrcode.toDataURL(qr, { margin: 2, scale: 7 });
                sessionData.qr = qrDataUrl;
                sessionData.status = 'scan_qr';
                logger.info({ sessionId: sId }, `New QR generated for ${isPlatform ? 'Platform Owner' : 'restaurant'}`);
            } catch (qrErr) {
                logger.error({ err: qrErr, sessionId: sId }, 'Failed to generate QR data URL');
            }
        }

        if (connection === 'open') {
            sessionData.status = 'connected';
            sessionData.qr = null;
            sessionData.connectedAt = new Date().toISOString();
            sessionData.reconnectAttempts = 0;

            const userJid = sock.user?.id || '';
            const phone = userJid.split(':')[0].split('@')[0];
            sessionData.phone = phone;

            logger.info({ sessionId: sId, phone }, `${isPlatform ? 'Platform Owner' : 'Restaurant'} WhatsApp session connected successfully`);

            // Start periodic keep-alive heartbeat every 10 minutes to prevent idle logout
            if (sessionData.keepAliveTimer) {
                clearInterval(sessionData.keepAliveTimer);
            }
            sessionData.keepAliveTimer = setInterval(async () => {
                if (sessionData.status === 'connected' && sessionData.sock) {
                    try {
                        await sessionData.sock.sendPresenceUpdate('available');
                        logger.debug({ sessionId: sId }, 'Dispatched keep-alive presence heartbeat');
                    } catch (e) {
                        logger.debug({ sessionId: sId }, 'Heartbeat presence ping warning');
                    }
                }
            }, 10 * 60 * 1000);

            await notifyLaravel({
                event: 'session.connected',
                session_id: isPlatform ? 'platform' : String(sId),
                restaurant_id: isPlatform ? null : sId,
                phone,
                connected_at: sessionData.connectedAt,
            });
        }

        if (connection === 'close') {
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const isLoggedOut = statusCode === DisconnectReason.loggedOut;

            logger.warn({ sessionId: sId, statusCode, isLoggedOut }, 'Connection closed');

            if (sessionData.keepAliveTimer) {
                clearInterval(sessionData.keepAliveTimer);
                sessionData.keepAliveTimer = null;
            }

            if (isLoggedOut) {
                sessionData.status = 'disconnected';
                sessionData.qr = null;
                sessionData.phone = null;
                sessionData.sock = null;
                cleanSessionDir(sId);

                await notifyLaravel({
                    event: 'session.disconnected',
                    session_id: isPlatform ? 'platform' : String(sId),
                    restaurant_id: isPlatform ? null : sId,
                });
            } else {
                sessionData.status = 'connecting';
                sessionData.reconnectAttempts = (sessionData.reconnectAttempts || 0) + 1;
                const delayMs = Math.min(1000 * Math.pow(2, sessionData.reconnectAttempts), 30000);

                setTimeout(() => {
                    startSession(sId).catch((reconnectErr) => {
                        logger.error({ err: reconnectErr, sessionId: sId }, 'Auto-reconnect failed');
                    });
                }, delayMs);
            }
        }
    });

    // Inbound messages handler (Auto-responder, AI Business Consultant & Webhook)
    sock.ev.on('messages.upsert', async (m) => {
        if (m.type !== 'notify') return;

        for (const msg of m.messages) {
            if (!msg.message) continue;

            const senderJid = msg.key.remoteJid;
            if (!senderJid || senderJid.endsWith('@g.us') || senderJid === 'status@broadcast') continue;

            const text = msg.message.conversation ||
                msg.message.extendedTextMessage?.text ||
                '';

            if (!text.trim()) continue;

            const cleanPhone = senderJid.split('@')[0];

            // If message was sent by the Platform Owner from their own phone (Human Agent Takeover)
            if (msg.key.fromMe) {
                if (isPlatform) {
                    const trimmedText = text.trim();
                    const isResumeCommand = /^(#تفعيل|#ذكاء|#تشغيل|\/resume|\/ai)$/i.test(trimmedText);

                    logger.info({ sessionId: sId, to: cleanPhone, isResumeCommand, text: trimmedText.substring(0, 35) }, 'Platform Owner sent message from device (Human Takeover Event)');

                    notifyLaravel({
                        event: isResumeCommand ? 'platform_message.owner_resumed_ai' : 'platform_message.sent_by_owner',
                        session_id: 'platform',
                        to: cleanPhone,
                        text: trimmedText,
                        timestamp: msg.messageTimestamp,
                    }).catch(() => {});
                }
                continue;
            }

            const senderName = msg.pushName || msg.verifiedBizName || null;
            logger.info({ sessionId: sId, from: cleanPhone, senderName, text: text.substring(0, 40) }, `Inbound message to ${isPlatform ? 'Platform Owner' : 'restaurant'}`);

            // Forward to Laravel webhook
            const hookRes = await notifyLaravel({
                event: isPlatform ? 'platform_message.received' : 'message.received',
                session_id: isPlatform ? 'platform' : String(sId),
                restaurant_id: isPlatform ? null : sId,
                from: cleanPhone,
                sender_name: senderName,
                text,
                timestamp: msg.messageTimestamp,
            });

            // If Laravel returned an auto-reply (e.g. from AI Consultant or Restaurant Auto-reply)
            if (hookRes && hookRes.auto_reply_text) {
                try {
                    await sock.presenceSubscribe(senderJid);
                    await sock.sendPresenceUpdate('composing', senderJid);
                    // Realistic typing delay (1.5s - 2.5s)
                    const typingDelay = Math.floor(Math.random() * 1000) + 1500;
                    await new Promise((r) => setTimeout(r, typingDelay));
                    await sock.sendPresenceUpdate('paused', senderJid);

                    await sock.sendMessage(senderJid, { text: hookRes.auto_reply_text });
                    logger.info({ sessionId: sId, to: cleanPhone }, 'Auto-reply dispatched successfully');
                } catch (replyErr) {
                    logger.warn({ err: replyErr.message, sessionId: sId }, 'Failed to dispatch auto-reply');
                }
            }
        }
    });

    return sessionData;
}

// Automatically resume existing sessions found on disk upon service startup
async function resumePersistedSessions() {
    const baseDir = path.join(__dirname, 'storage', 'sessions');
    if (!fs.existsSync(baseDir)) return;

    const entries = fs.readdirSync(baseDir, { withFileTypes: true });
    for (const entry of entries) {
        if (!entry.isDirectory()) continue;

        if (entry.name === 'platform') {
            logger.info('Found saved Platform Owner WhatsApp session, resuming...');
            startSession('platform').catch((err) => {
                logger.warn({ err: err.message }, 'Failed to resume Platform session');
            });
        } else if (entry.name.startsWith('restaurant_')) {
            const rId = parseInt(entry.name.replace('restaurant_', ''), 10);
            if (!isNaN(rId)) {
                logger.info({ restaurantId: rId }, 'Found saved restaurant session, resuming...');
                startSession(rId).catch((err) => {
                    logger.warn({ err: err.message, restaurantId: rId }, 'Failed to resume saved session');
                });
            }
        }
    }
}

// ---------------- Express App Setup ---------------- //

const app = express();
app.use(cors());
app.use(express.json({ limit: '10mb' }));

// Auth middleware for internal gateway calls
app.use((req, res, next) => {
    if (req.path === '/health') return next();

    const secret = req.headers['x-gateway-secret'];
    if (!secret || secret !== GATEWAY_SECRET) {
        return res.status(401).json({ success: false, message: 'Unauthorized gateway access' });
    }
    next();
});

// Health check endpoint
app.get('/health', (req, res) => {
    res.json({
        success: true,
        service: 'tedallal-whatsapp-gateway',
        status: 'UP',
        active_sessions: sessions.size,
        uptime_seconds: Math.floor(process.uptime()),
    });
});

// Get session status / QR (Supports numeric ID or 'platform')
app.get('/api/session/:id/status', async (req, res) => {
    const sId = normalizeSessionId(req.params.id);
    let session = sessions.get(sId);

    // If session directory exists on disk but not in memory, start it
    if (!session) {
        const sessionDir = getSessionDir(sId);
        if (fs.existsSync(sessionDir)) {
            session = await startSession(sId);
        }
    }

    if (!session) {
        return res.json({
            success: true,
            status: 'disconnected',
            qr_code: null,
            phone: null,
            connected_at: null,
        });
    }

    res.json({
        success: true,
        status: session.status,
        qr_code: session.qr,
        phone: session.phone,
        connected_at: session.connectedAt,
    });
});

// Start or request pairing QR code
app.post('/api/session/:id/start', async (req, res) => {
    const sId = normalizeSessionId(req.params.id);
    try {
        const session = await startSession(sId);
        res.json({
            success: true,
            status: session.status,
            qr_code: session.qr,
            phone: session.phone,
            connected_at: session.connectedAt,
        });
    } catch (err) {
        logger.error({ err, sessionId: sId }, 'Failed to start session');
        res.status(500).json({ success: false, message: err.message });
    }
});

// Disconnect / logout session
app.post('/api/session/:id/logout', async (req, res) => {
    const sId = normalizeSessionId(req.params.id);
    const session = sessions.get(sId);

    if (session?.keepAliveTimer) {
        clearInterval(session.keepAliveTimer);
        session.keepAliveTimer = null;
    }

    if (session?.sock) {
        try {
            await session.sock.logout();
        } catch (err) {
            logger.warn({ err: err.message, sessionId: sId }, 'Sock logout error, proceeding to clean files');
        }
    }

    cleanSessionDir(sId);
    sessions.delete(sId);

    const isPlatform = (sId === 'platform');
    await notifyLaravel({
        event: 'session.disconnected',
        session_id: isPlatform ? 'platform' : String(sId),
        restaurant_id: isPlatform ? null : sId,
    });

    res.json({
        success: true,
        message: 'Session disconnected and cleaned up successfully',
    });
});

// Send message to customer or prospective restaurant owner
app.post('/api/messages/send', async (req, res) => {
    const { restaurant_id, session_id, phone, text, simulate_typing } = req.body;

    const sId = normalizeSessionId(session_id || restaurant_id);
    const session = sessions.get(sId);

    if (!session?.sock || session.status !== 'connected') {
        return res.status(400).json({
            success: false,
            message: `WhatsApp session (${sId}) is not connected`,
        });
    }

    // Normalize phone digits
    let digits = String(phone).replace(/[^0-9]/g, '');
    if (digits.startsWith('09')) {
        digits = '963' + digits.substring(1);
    } else if (digits.startsWith('963')) {
        // already syrian international
    } else if (!digits.startsWith('963') && digits.length === 9 && digits.startsWith('9')) {
        digits = '963' + digits;
    }

    const jid = `${digits}@s.whatsapp.net`;

    try {
        if (simulate_typing !== false) {
            await session.sock.presenceSubscribe(jid).catch(() => {});
            await session.sock.sendPresenceUpdate('composing', jid).catch(() => {});
            const typingDelay = Math.floor(Math.random() * 1200) + 1200; // 1.2s - 2.4s
            await new Promise((r) => setTimeout(r, typingDelay));
            await session.sock.sendPresenceUpdate('paused', jid).catch(() => {});
        }

        const sent = await session.sock.sendMessage(jid, { text });

        logger.info({ sessionId: sId, to: digits, msgId: sent?.key?.id }, 'Message sent successfully');

        res.json({
            success: true,
            message_id: sent?.key?.id,
            to: digits,
        });
    } catch (err) {
        logger.error({ err: err.message, sessionId: sId, to: digits }, 'Failed to send message');
        res.status(500).json({
            success: false,
            message: err.message || 'Failed to send WhatsApp message',
        });
    }
});

app.listen(PORT, HOST, () => {
    logger.info(`Tedallal WhatsApp Gateway running on http://${HOST}:${PORT}`);
    resumePersistedSessions().catch((err) => {
        logger.error({ err }, 'Error during session persistence recovery');
    });
});
