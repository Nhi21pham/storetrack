// Renders many invoice HTMLs to PDFs through a single shared Chromium, so an
// export pays the browser's startup cost once instead of once per invoice.
// Invoked as: node render-invoices.cjs <manifest.json>
// Manifest: { chromePath, margin, items: [{ html, out }] }
const puppeteer = require('puppeteer');
const fs = require('fs');

(async () => {
    const manifest = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));

    const browser = await puppeteer.launch({
        executablePath: manifest.chromePath,
        args: ['--no-sandbox', '--disable-dev-shm-usage'],
    });

    try {
        const page = await browser.newPage();
        for (const item of manifest.items) {
            await page.setContent(item.html, { waitUntil: 'load' });
            await page.pdf({
                path: item.out,
                format: 'A4',
                printBackground: true,
                margin: manifest.margin,
            });
        }
    } finally {
        await browser.close();
    }
})().catch((err) => {
    console.error(err && err.stack ? err.stack : String(err));
    process.exit(1);
});
