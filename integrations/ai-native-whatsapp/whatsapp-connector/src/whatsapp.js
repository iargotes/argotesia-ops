const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const axios = require('axios');

const BLOCKED_CHAT_IDS = new Set(['status@broadcast']);
const BLOCKED_CHAT_SUFFIXES = ['@g.us', '@broadcast', '@newsletter'];
const SUPPORTED_INBOUND_TYPES = new Set(['chat', 'ptt', 'audio']);
const AUDIO_INBOUND_TYPES = new Set(['ptt', 'audio']);
const DIRECT_JID_PATTERN = /@(c\.us|lid|g\.us|s\.whatsapp\.net)$/i;

const state = {
  ready: false,
  lastQrAt: null,
  lastQr: null,
  lastEventAt: null,
  lastError: null,
  wid: null,
  status: 'starting'
};

let client;
let botEnabled = true;
const autoReplyEnabled = process.env.WHATSAPP_AUTO_REPLY === 'true';

function sanitizeText(text = '') {
  if (typeof text !== 'string') return '';
  return text.replace(/"/g, "'").replace(/\n|\r/g, ' ').replace(/\\/g, '\\\\').trim();
}

function normalizeDigits(value = '') {
  return String(value).replace(/\D/g, '');
}

function parseAllowedSenders(value = '') {
  return String(value)
    .split(',')
    .map((sender) => sender.trim().toLowerCase())
    .filter(Boolean);
}

function isAllowedSender(sender = '') {
  const allowed = parseAllowedSenders(process.env.WHATSAPP_ALLOWED_SENDERS || '');
  if (allowed.length === 0) return true;

  const normalizedSender = String(sender).trim().toLowerCase();
  const senderDigits = normalizeDigits(normalizedSender);

  return allowed.some((entry) => {
    if (normalizedSender === entry) return true;
    const entryDigits = normalizeDigits(entry);
    return senderDigits && entryDigits && senderDigits === entryDigits;
  });
}

function isDirectChatJid(chatId = '') {
  if (!chatId || BLOCKED_CHAT_IDS.has(chatId)) return false;
  return !BLOCKED_CHAT_SUFFIXES.some((suffix) => chatId.endsWith(suffix));
}

function shouldForwardInbound(msg) {
  if (!botEnabled) return [false, 'bot-disabled'];
  if (msg.fromMe) return [false, 'from-me'];
  if (!isDirectChatJid(msg.from || '')) return [false, 'not-direct-chat'];
  if (!SUPPORTED_INBOUND_TYPES.has(msg.type)) return [false, 'unsupported-type'];
  if (!isAllowedSender(msg.from || '')) return [false, 'sender-not-allowed'];
  if (AUDIO_INBOUND_TYPES.has(msg.type) && !msg.hasMedia) return [false, 'audio-without-media'];
  if (msg.type === 'chat' && !sanitizeText(msg.body || '')) return [false, 'empty-body'];
  return [true, null];
}

async function postToBackend(payload) {
  const backendUrl = process.env.WHATSAPP_BACKEND_WEBHOOK_URL;
  if (!backendUrl) throw new Error('WHATSAPP_BACKEND_WEBHOOK_URL is not configured');

  const timeout = AUDIO_INBOUND_TYPES.has(payload.type)
    ? Number(process.env.WHATSAPP_AUDIO_TIMEOUT_MS || 180000)
    : Number(process.env.WHATSAPP_BACKEND_TIMEOUT_MS || 10000);

  return axios.post(backendUrl, payload, {
    timeout,
    maxBodyLength: Number(process.env.WHATSAPP_MAX_AUDIO_BYTES || 10485760) * 1.5,
    headers: process.env.WHATSAPP_WEBHOOK_SECRET
      ? { 'x-webhook-secret': process.env.WHATSAPP_WEBHOOK_SECRET }
      : {}
  });
}

function buildInboundPayload(msg, options = {}) {
  return {
    chatType: 'direct',
    from: msg.from,
    from_me: Boolean(options.fromMe),
    pushName: msg.pushName || null,
    body: sanitizeText(msg.body || ''),
    type: msg.type,
    timestamp: msg.timestamp,
    id: msg.id?._serialized || String(Date.now())
  };
}

async function attachAudioMedia(payload, msg) {
  if (!AUDIO_INBOUND_TYPES.has(msg.type)) return payload;

  const media = await msg.downloadMedia();
  if (!media?.data || !media?.mimetype) throw new Error('audio-download-failed');

  const sizeBytes = Buffer.byteLength(media.data, 'base64');
  const maxBytes = Number(process.env.WHATSAPP_MAX_AUDIO_BYTES || 10485760);
  if (!Number.isFinite(maxBytes) || maxBytes <= 0) throw new Error('invalid-audio-size-limit');
  if (sizeBytes > maxBytes) throw new Error('audio-too-large');

  payload.media = {
    data: media.data,
    mimetype: media.mimetype,
    filename: media.filename || null,
    sizeBytes
  };
  return payload;
}

function initWhatsApp() {
  if (client) return client;

  const clientId = process.env.WHATSAPP_CLIENT_ID || 'mcp-whatsapp-ai-ops-center';
  const executablePath = process.env.PUPPETEER_EXECUTABLE_PATH || undefined;
  const headless = process.env.WHATSAPP_HEADLESS !== 'false';

  client = new Client({
    authStrategy: new LocalAuth({ clientId }),
    puppeteer: {
      headless,
      executablePath,
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-accelerated-2d-canvas',
        '--no-first-run',
        '--disable-gpu'
      ]
    }
  });

  client.on('qr', (qr) => {
    state.status = 'qr';
    state.ready = false;
    state.lastQr = qr;
    state.lastQrAt = new Date().toISOString();
    console.log('Escanea este QR con el WhatsApp de soporte:');
    qrcode.generate(qr, { small: true });
  });

  client.on('ready', async () => {
    state.status = 'ready';
    state.ready = true;
    state.lastError = null;
    try {
      state.wid = client.info?.wid?._serialized || null;
    } catch (_) {
      state.wid = null;
    }
    console.log('WhatsApp conectado y listo', { wid: state.wid });
  });

  client.on('authenticated', () => {
    state.status = 'authenticated';
    console.log('WhatsApp autenticado');
  });

  client.on('auth_failure', (message) => {
    state.status = 'auth_failure';
    state.ready = false;
    state.lastError = message;
    console.error('Error de autenticacion WhatsApp:', message);
  });

  client.on('disconnected', (reason) => {
    state.status = 'disconnected';
    state.ready = false;
    state.lastError = reason;
    console.warn('WhatsApp desconectado:', reason);
  });

  client.on('message', async (msg) => {
    state.lastEventAt = new Date().toISOString();

    const [allowed, reason] = shouldForwardInbound(msg);
    if (!allowed) {
      if (msg.from === 'status@broadcast') return;
      console.log('Mensaje ignorado', {
        reason,
        from: msg.from,
        type: msg.type,
        fromMe: msg.fromMe
      });
      return;
    }

    let payload = buildInboundPayload(msg);

    try {
      payload = await attachAudioMedia(payload, msg);
      const response = await postToBackend(payload);
      const replyMessage = autoReplyEnabled && typeof response?.data?.message === 'string'
        ? response.data.message.trim()
        : '';
      const replyTarget = response?.data?.to || payload.from;

      if (!replyMessage) {
        console.log('Backend recibido sin respuesta automatica', {
          from: payload.from,
          id: payload.id,
          status: response?.data?.status
        });
        return;
      }

      await sendText(replyTarget, replyMessage);
      console.log('Respuesta enviada por solicitud explicita del backend', {
        to: replyTarget,
        id: payload.id
      });
    } catch (error) {
      state.lastError = error.response?.data || error.message;
      console.error('Error enviando inbound al backend:', error.response?.status, state.lastError);
    }
  });

  client.on('message_create', async (msg) => {
    if (!msg.fromMe) return;
    if (!isDirectChatJid(msg.to || msg.from || '')) return;
    if (msg.type !== 'chat') return;

    const payload = buildInboundPayload(msg, { fromMe: true });
    try {
      await postToBackend(payload);
      console.log('Echo de mensaje propio enviado al backend', { id: payload.id });
    } catch (error) {
      state.lastError = error.response?.data || error.message;
      console.error('Error enviando echo al backend:', error.response?.status, state.lastError);
    }
  });

  client.initialize();
  return client;
}

async function resolveRecipient(to) {
  const rawTo = String(to || '').trim();
  if (!rawTo) return null;

  if (DIRECT_JID_PATTERN.test(rawTo)) return rawTo;

  const digits = normalizeDigits(rawTo);
  if (!digits) return null;

  const resolved = await client.getNumberId(digits);
  return resolved?._serialized || null;
}

async function sendText(to, message) {
  if (!client) throw new Error('Cliente WhatsApp no inicializado');
  if (!message || typeof message !== 'string') throw new Error('missing-message');

  const jid = await resolveRecipient(to);
  if (!jid) throw new Error('number-not-on-whatsapp');

  const sent = await client.sendMessage(jid, message);
  return {
    status: 'ok',
    id: sent?.id?._serialized || null,
    to: jid
  };
}

function getDiagnostics() {
  return {
    ...state,
    lastQr: state.lastQr ? '[available]' : null,
    botEnabled,
    autoReplyEnabled,
    backendWebhookUrl: process.env.WHATSAPP_BACKEND_WEBHOOK_URL || null,
    clientId: process.env.WHATSAPP_CLIENT_ID || 'mcp-whatsapp-ai-ops-center',
    allowedSendersConfigured: parseAllowedSenders(process.env.WHATSAPP_ALLOWED_SENDERS || '').length
  };
}

function setBotEnabled(value) {
  botEnabled = Boolean(value);
}

function getBotEnabled() {
  return botEnabled;
}

module.exports = {
  getBotEnabled,
  getDiagnostics,
  getLastQr: () => state.lastQr,
  initWhatsApp,
  sendText,
  setBotEnabled
};
