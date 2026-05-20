const http = require('http');
const https = require('https');
const fs = require('fs');
const path = require('path');

const PORT = process.env.PORT || 3000;
const CJ_ACCESS_TOKEN = process.env.CJ_ACCESS_TOKEN || 'REPLACE_WITH_CJ_ACCESS_TOKEN';
const CJ_ENDPOINT = 'https://developers.cjdropshipping.com/api2.0/v1/shopping/order/createOrderV2';

function setCorsHeaders(res) {
    res.setHeader('Access-Control-Allow-Origin', '*');
    res.setHeader('Access-Control-Allow-Methods', 'POST, OPTIONS');
    res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
}

function sendJson(res, statusCode, body) {
    const payload = JSON.stringify(body);
    res.writeHead(statusCode, {
        'Content-Type': 'application/json',
        'Content-Length': Buffer.byteLength(payload)
    });
    res.end(payload);
}

function sendFile(res, filePath) {
    fs.readFile(filePath, (err, data) => {
        if (err) {
            res.writeHead(404);
            return res.end('Not found');
        }

        const ext = path.extname(filePath).toLowerCase();
        const mime = {
            '.html': 'text/html',
            '.css': 'text/css',
            '.js': 'application/javascript',
            '.json': 'application/json',
            '.png': 'image/png',
            '.jpg': 'image/jpeg',
            '.svg': 'image/svg+xml',
            '.woff2': 'font/woff2',
            '.woff': 'font/woff'
        }[ext] || 'application/octet-stream';

        res.writeHead(200, { 'Content-Type': mime });
        res.end(data);
    });
}

const server = http.createServer((req, res) => {
    // Handle preflight for the proxy endpoint
    if (req.method === 'OPTIONS' && req.url === '/cj-order') {
        setCorsHeaders(res);
        res.writeHead(204);
        return res.end();
    }

    if (req.method === 'POST' && req.url === '/cj-order') {
        setCorsHeaders(res);

        let body = '';
        req.on('data', chunk => (body += chunk));
        req.on('end', () => {
            let payload;
            try {
                payload = JSON.parse(body);
            } catch (error) {
                return sendJson(res, 400, { error: 'Invalid JSON body' });
            }

            if (!CJ_ACCESS_TOKEN || CJ_ACCESS_TOKEN === 'REPLACE_WITH_CJ_ACCESS_TOKEN') {
                return sendJson(res, 500, { error: 'CJ access token is not configured in the proxy' });
            }

            const cjRequest = https.request(CJ_ENDPOINT, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'CJ-Access-Token': CJ_ACCESS_TOKEN,
                    'Content-Length': Buffer.byteLength(JSON.stringify(payload))
                }
            }, cjResponse => {
                let responseData = '';
                cjResponse.on('data', chunk => (responseData += chunk));
                cjResponse.on('end', () => {
                    res.writeHead(cjResponse.statusCode || 502, {
                        'Content-Type': 'application/json'
                    });
                    res.end(responseData);
                });
            });

            cjRequest.on('error', error => {
                console.error('CJ proxy request error:', error);
                sendJson(res, 502, { error: 'CJ proxy request failed', details: error.message });
            });

            cjRequest.write(JSON.stringify(payload));
            cjRequest.end();
        });

        return;
    }

    // Serve static files from the current directory for GET requests
    if (req.method === 'GET') {
        let reqPath = req.url.split('?')[0];
        if (reqPath === '/' || reqPath === '') reqPath = '/index.html';
        const filePath = path.join(__dirname, reqPath);
        return sendFile(res, filePath);
    }

    // Fallback
    sendJson(res, 404, { error: 'Not found' });
});

server.listen(PORT, () => {
    console.log(`CJ proxy + static server running on http://127.0.0.1:${PORT}`);
    console.log('POST /cj-order will be forwarded to CJ createOrderV2');
});
