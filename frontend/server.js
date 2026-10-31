import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PORT = Number(process.env.PORT || 5173);
const HOST = process.env.HOST || '0.0.0.0';
const BACKEND_INTERNAL_URL = process.env.BACKEND_INTERNAL_URL || 'http://127.0.0.1:8000';

const MIME_TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'text/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.webp': 'image/webp',
  '.webm': 'video/webm',
  '.mp4': 'video/mp4',
  '.ico': 'image/x-icon'
};

const server = http.createServer((req, res) => {
  const urlPath = (req.url || '/').split('?')[0];

  const isBackendProxyRoute =
    urlPath.startsWith('/api/') ||
    urlPath === '/admin' ||
    urlPath.startsWith('/admin/') ||
    urlPath.startsWith('/livewire') ||
    urlPath.startsWith('/filament') ||
    urlPath.startsWith('/css/filament') ||
    urlPath.startsWith('/js/filament') ||
    urlPath.startsWith('/fonts/filament') ||
    urlPath.startsWith('/vendor/filament') ||
    urlPath.startsWith('/vendor/livewire') ||
    urlPath.startsWith('/storage/') ||
    urlPath.startsWith('/sanctum/') ||
    urlPath === '/up';

  if (isBackendProxyRoute) {
    const targetUrl = new URL(req.url, BACKEND_INTERNAL_URL);
    const forwardedProto = req.headers['x-forwarded-proto'] || 'https';
    const forwardedHost = req.headers['x-forwarded-host'] || req.headers.host || targetUrl.host;
    const forwardedPort = req.headers['x-forwarded-port'] || (forwardedProto === 'https' ? '443' : '80');

    const proxyReq = http.request(
      targetUrl,
      {
        method: req.method,
        headers: {
          ...req.headers,
          host: forwardedHost,
          'x-forwarded-host': forwardedHost,
          'x-forwarded-proto': forwardedProto,
          'x-forwarded-port': forwardedPort
        }
      },
      (proxyRes) => {
        res.writeHead(proxyRes.statusCode || 502, proxyRes.headers);
        proxyRes.pipe(res, { end: true });
      }
    );
    proxyReq.on('error', (err) => {
      res.writeHead(502, { 'Content-Type': 'application/json; charset=utf-8' });
      res.end(JSON.stringify({ ok: false, error: 'Backend unreachable', details: err.message }));
    });
    req.pipe(proxyReq, { end: true });
    return;
  }

  const ext = path.extname(urlPath).toLowerCase();
  const isSpaRoute = urlPath === '/' || !ext;
  const relPath = isSpaRoute ? '/index.html' : urlPath;
  const filePath = path.join(__dirname, path.normalize(relPath));

  if (!filePath.startsWith(__dirname)) {
    res.writeHead(403);
    res.end('Forbidden');
    return;
  }

  fs.stat(filePath, (statErr, stat) => {
    if (statErr || !stat.isFile()) {
      res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
      res.end('Not found');
      return;
    }

    const resolvedExt = path.extname(filePath).toLowerCase();
    const contentType = MIME_TYPES[resolvedExt] || 'application/octet-stream';
    const range = req.headers.range;

    if (range && (resolvedExt === '.webm' || resolvedExt === '.mp4')) {
      const parts = range.replace(/bytes=/, '').split('-');
      const start = parseInt(parts[0], 10);
      const end = parts[1] ? parseInt(parts[1], 10) : stat.size - 1;
      const chunksize = end - start + 1;

      res.writeHead(206, {
        'Content-Range': `bytes ${start}-${end}/${stat.size}`,
        'Accept-Ranges': 'bytes',
        'Content-Length': chunksize,
        'Content-Type': contentType
      });
      fs.createReadStream(filePath, { start, end }).pipe(res);
      return;
    }

    fs.readFile(filePath, (err, data) => {
      if (err) {
        res.writeHead(404, { 'Content-Type': 'text/plain; charset=utf-8' });
        res.end('Not found');
        return;
      }
      res.writeHead(200, {
        'Content-Type': contentType,
        'Accept-Ranges': 'bytes',
        'Cache-Control': 'no-store, no-cache, must-revalidate, proxy-revalidate, max-age=0'
      });
      res.end(data);
    });
  });
});

server.listen(PORT, HOST, () => {
  console.log(`\n✨ ScenePulse by Flownie running at: http://localhost:${PORT} (Proxy -> ${BACKEND_INTERNAL_URL})\n`);
});
