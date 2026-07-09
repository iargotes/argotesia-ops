require('dotenv').config();

const express = require('express');
const {
  getBotEnabled,
  getDiagnostics,
  getLastQr,
  initWhatsApp,
  sendText,
  setBotEnabled
} = require('./whatsapp');
const QRCode = require('qrcode');

const app = express();
const port = Number(process.env.WHATSAPP_CONNECTOR_PORT || process.env.PORT || 3000);
const host = process.env.WHATSAPP_CONNECTOR_HOST || '0.0.0.0';

app.use(express.json({ limit: '1mb' }));

app.get('/health', (_req, res) => {
  const diagnostics = getDiagnostics();
  res.json({
    service: 'ai-ops-whatsapp-connector',
    ready: diagnostics.ready,
    status: diagnostics.status
  });
});

app.get('/status', (_req, res) => {
  res.json({
    status: getDiagnostics().status,
    ready: getDiagnostics().ready,
    botEnabled: getBotEnabled()
  });
});

app.get('/me', (_req, res) => {
  const diagnostics = getDiagnostics();
  res.json({
    wid: diagnostics.wid,
    ready: diagnostics.ready,
    status: diagnostics.status
  });
});

app.get('/diag', (_req, res) => {
  res.json(getDiagnostics());
});

app.get('/qr', (_req, res) => {
  const qr = getLastQr();
  if (!qr) return res.status(404).json({ error: 'qr-not-available' });
  return res.json({ qr });
});

app.get('/qr.svg', async (_req, res) => {
  const qr = getLastQr();
  if (!qr) return res.status(404).type('text/plain').send('qr-not-available');

  const svg = await QRCode.toString(qr, { type: 'svg', margin: 1, width: 320 });
  return res.type('image/svg+xml').send(svg);
});

app.post('/bot/enabled', (req, res) => {
  setBotEnabled(Boolean(req.body?.enabled));
  res.json({ status: 'ok', enabled: getBotEnabled() });
});

app.post('/send-text2', async (req, res) => {
  const { to, message } = req.body || {};

  if (!to || !message) {
    return res.status(400).json({ error: 'missing-to-or-message' });
  }

  try {
    const result = await sendText(to, message);
    return res.json(result);
  } catch (error) {
    if (error.message === 'number-not-on-whatsapp') {
      return res.status(404).json({ error: 'number-not-on-whatsapp' });
    }
    if (error.message === 'missing-message') {
      return res.status(400).json({ error: 'missing-to-or-message' });
    }
    console.error('Error en /send-text2:', error.message);
    return res.status(500).json({ error: 'send-failed', detail: error.message });
  }
});

app.listen(port, host, () => {
  console.log(`WhatsApp connector listening on ${host}:${port}`);
  initWhatsApp();
});
