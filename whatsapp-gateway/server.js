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

function getSessionDir(restaurantId) {
    const dir = path.join(__dirname, 'storage', 'sessions', `restaurant_${restaurantId}`);
    if (!fs.existsSync(dir)) {
        fs.mkdirSync(dir, { recursive: true });
    }
    return dir;
}

function cleanSessionDir(restaurantId) {
    const dir = path.join(__dirname, 'storage', 'sessions', `restaurant_${restaurantId}`);
    if (fs.existsSync(dir)) {
        try {
            fs.rmSync(dir, { recursive: true, force: true });
        } catch (err) {
            logger.error({ err, restaurantId }, 'Failed to remove session directory');
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

async function startSession(restaurantId) {
    const rId = parseInt(restaurantId, 10);
    const existing = sessions.get(rId);

    if (existing?.sock && existing.status === 'connected') {
        return existing;
    }

    const sessionDir = getSessionDir(rId);
    const { state, saveCreds } = await useMultiFileAuthState(sessionDir);
    const { version } = await fetchLatestBaileysVersion().catch(() => ({ version: [2, 3000, 1015901307] }));

    const sessionData = {
        id: rId,
        sock: null,
        status: 'connecting',
        qr: null,
        phone: null,
        connectedAt: null,
        reconnectAttempts: 0,
    };
    sessions.set(rId, sessionData);

    const sock = makeWASocket({
        version,
        auth: state,
        logger: baileysLogger,
        printQRInTerminal: false,
        browser: ['Tedallal Platform', 'Chrome', '124.0.0'],
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
                logger.info({ restaurantId: rId }, 'New QR generated for restaurant');
            } catch (qrErr) {
                logger.error({ err: qrErr, restaurantId: rId }, 'Failed to generate QR data URL');
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

            logger.info({ restaurantId: rId, phone }, 'Restaurant WhatsApp session connected successfully');

            await notifyLaravel({
                event: 'session.connected',
                restaurant_id: rId,
                phone,
                connected_at: sessionData.connectedAt,
            });
        }

        if (connection === 'close') {
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const isLoggedOut = statusCode === DisconnectReason.loggedOut;

            logger.warn({ restaurantId: rId, statusCode, isLoggedOut }, 'Connection closed');

            if (isLoggedOut) {
                sessionData.status = 'disconnected';
                sessionData.qr = null;
                sessionData.phone = null;
                sessionData.sock = null;
                cleanSessionDir(rId);

                await notifyLaravel({
                    event: 'session.disconnected',
                    restaurant_id: rId,
                });
            } else {
                sessionData.status = 'connecting';
                sessionData.reconnectAttempts = (sessionData.reconnectAttempts || 0) + 1;
                const delayMs = Math.min(1000 * Math.pow(2, sessionData.reconnectAttempts), 30000);

                setTimeout(() => {
                    startSession(rId).catch((reconnectErr) => {
                        logger.error({ err: reconnectErr, restaurantId: rId }, 'Auto-reconnect failed');
                    });
                }, delayMs);
            }
        }
    });

    // Inbound messages handler (Auto-responder & Webhook)
    sock.ev.on('messages.upsert', async (m) => {
        if (m.type !== 'notify') return;

        for (const msg of m.messages) {
            if (!msg.message || msg.key.fromMe) continue;

            const senderJid = msg.key.remoteJid;
            if (!senderJid || senderJid.endsWith('@g.us') || senderJid === 'status@broadcast') continue;

            const text = msg.message.conversation ||
                msg.message.extendedTextMessage?.text ||
                '';

            if (!text.trim()) continue;

            const cleanPhone = senderJid.split('@')[0];
            logger.info({ restaurantId: rId, from: cleanPhone, text: text.substring(0, 40) }, 'Inbound customer message');

            // Forward to Laravel webhook
            const hookRes = await notifyLaravel({
                event: 'message.received',
                restaurant_id: rId,
                from: cleanPhone,
                text,
                timestamp: msg.messageTimestamp,
            });

            // If Laravel requested an immediate auto-reply
            if (hookRes && hookRes.auto_reply_text) {
                try {
                    await sock.presenceSubscribe(senderJid);
                    await sock.sendPresenceUpdate('composing', senderJid);
                    await new Promise((r) => setTimeout(r, 1200));
                    await sock.sendPresenceUpdate('paused', senderJid);

                    await sock.sendMessage(senderJid, { text: hookRes.auto_reply_text });
                    logger.info({ restaurantId: rId, to: cleanPhone }, 'Auto-reply dispatched to customer');
                } catch (replyErr) {
                    logger.warn({ err: replyErr.message, restaurantId: rId }, 'Failed to dispatch auto-reply');
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
        if (entry.isDirectory() && entry.name.startsWith('restaurant_')) {
            const rId = parseInt(entry.name.replace('restaurant_', ''), 10);
            if (!isNaN(rId)) {
                logger.info({ restaurantId: rId }, 'Found saved session, resuming...');
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

// Get session status / QR
app.get('/api/session/:id/status', async (req, res) => {
    const rId = parseInt(req.params.id, 10);
    let session = sessions.get(rId);

    // If session directory exists on disk but not in memory, start it
    if (!session) {
        const sessionDir = path.join(__dirname, 'storage', 'sessions', `restaurant_${rId}`);
        if (fs.existsSync(sessionDir)) {
            session = await startSession(rId);
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
    const rId = parseInt(req.params.id, 10);
    try {
        const session = await startSession(rId);
        res.json({
            success: true,
            status: session.status,
            qr_code: session.qr,
            phone: session.phone,
            connected_at: session.connectedAt,
        });
    } catch (err) {
        logger.error({ err, restaurantId: rId }, 'Failed to start session');
        res.status(500).json({ success: false, message: err.message });
    }
});

// Disconnect / logout session
app.post('/api/session/:id/logout', async (req, res) => {
    const rId = parseInt(req.params.id, 10);
    const session = sessions.get(rId);

    if (session?.sock) {
        try {
            await session.sock.logout();
        } catch (err) {
            logger.warn({ err: err.message, restaurantId: rId }, 'Sock logout error, proceeding to clean files');
        }
    }

    cleanSessionDir(rId);
    sessions.delete(rId);

    await notifyLaravel({
        event: 'session.disconnected',
        restaurant_id: rId,
    });

    res.json({
        success: true,
        message: 'Session disconnected and cleaned up successfully',
    });
});

// Send message to customer
app.post('/api/messages/send', async (req, res) => {
    const { restaurant_id, phone, text, simulate_typing } = req.body;

    const rId = parseInt(restaurant_id, 10);
    const session = sessions.get(rId);

    if (!session?.sock || session.status !== 'connected') {
        return res.status(400).json({
            success: false,
            message: 'WhatsApp session for this restaurant is not connected',
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

        logger.info({ restaurantId: rId, to: digits, msgId: sent?.key?.id }, 'Message sent successfully');

        res.json({
            success: true,
            message_id: sent?.key?.id,
            to: digits,
        });
    } catch (err) {
        logger.error({ err: err.message, restaurantId: rId, to: digits }, 'Failed to send message');
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

