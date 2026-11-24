import { readFile, writeFile } from 'node:fs/promises';
import { fileURLToPath } from 'node:url';
import { chromium } from '@playwright/test';

// favicon.svg is the single source for the app symbol and its raster exports.
const root = new URL('../', import.meta.url);
const file = (path) => new URL(path, root);
const symbol = await readFile(file('public/favicon.svg'), 'utf8');
const inner = symbol.match(/<svg\b[^>]*>([\s\S]*?)<\/svg>/)?.[1];
if (!inner) throw new Error('favicon.svg must contain an SVG element.');
const socialPath = file('public/branding/social-card.svg');
const socialTemplate = await readFile(socialPath, 'utf8');
const marker = /<!-- brand-icon:start -->[\s\S]*?<!-- brand-icon:end -->/;
if (!marker.test(socialTemplate))
    throw new Error('The social card is missing its brand icon markers.');
const social = socialTemplate.replace(
    marker,
    `<!-- brand-icon:start -->\n <svg x="72" y="64" width="56" height="56" viewBox="0 0 64 64">${inner}</svg>\n <!-- brand-icon:end -->`,
);
const browser = await chromium.launch({
    headless: true,
    ...(process.env.CHROME_PATH
        ? { executablePath: process.env.CHROME_PATH }
        : {}),
    args: ['--no-sandbox'],
});
try {
    const page = await browser.newPage();
    async function render(svg, width, height, background = null) {
        const data = await page.evaluate(
            async ({ svg, width, height, background }) => {
                const image = new Image();
                image.src = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`;
                await image.decode();
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const context = canvas.getContext('2d');
                if (!context)
                    throw new Error('Canvas rendering is unavailable.');
                if (background) {
                    context.fillStyle = background;
                    context.fillRect(0, 0, width, height);
                }
                context.drawImage(image, 0, 0, width, height);
                return canvas.toDataURL('image/png').split(',')[1];
            },
            { svg, width, height, background },
        );
        return Buffer.from(data, 'base64');
    }
    for (const size of [192, 512]) {
        await writeFile(
            file(`public/icons/icon-${size}.png`),
            await render(symbol, size, size),
        );
    }
    // iOS applies its own rounded mask; the touch icon must be opaque.
    await writeFile(
        file('public/apple-touch-icon.png'),
        await render(symbol, 180, 180, '#19765b'),
    );
    const sizes = [16, 32, 48, 64, 128, 256];
    const frames = await Promise.all(
        sizes.map((size) => render(symbol, size, size)),
    );
    const directory = Buffer.alloc(6 + sizes.length * 16);
    directory.writeUInt16LE(1, 2); // ICO image type.
    directory.writeUInt16LE(sizes.length, 4);
    let offset = directory.length;
    for (const [index, size] of sizes.entries()) {
        const entry = 6 + index * 16;
        directory[entry] = size === 256 ? 0 : size;
        directory[entry + 1] = size === 256 ? 0 : size;
        directory.writeUInt16LE(1, entry + 4);
        directory.writeUInt16LE(32, entry + 6);
        directory.writeUInt32LE(frames[index].length, entry + 8);
        directory.writeUInt32LE(offset, entry + 12);
        offset += frames[index].length;
    }
    await writeFile(
        file('public/favicon.ico'),
        Buffer.concat([directory, ...frames]),
    );
    await writeFile(socialPath, social);
    await writeFile(
        file('public/branding/social-card.png'),
        await render(social, 1200, 630),
    );
    console.log(
        `Brand assets regenerated from ${fileURLToPath(file('public/favicon.svg'))}.`,
    );
} finally {
    await browser.close();
}
