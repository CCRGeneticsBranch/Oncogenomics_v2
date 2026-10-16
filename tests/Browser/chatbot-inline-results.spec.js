const { test, expect } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const source = fs.readFileSync(path.resolve(__dirname, '../../resources/views/pages/viewChatbot.blade.php'), 'utf8');
const renderer = source.slice(source.indexOf('    function renderRedirectPage('), source.indexOf('    function renderExecutions('));

test('analysis pages stay inside the conversation and duplicate events do not duplicate frames', async ({ page }) => {
    await page.route('http://chat.test/**', route => route.fulfill({
        contentType: 'text/html',
        body: route.request().url().includes('/analysis')
            ? '<h1>Correlation result</h1>'
            : '<main id="message"></main><textarea aria-label="Chat input"></textarea>',
    }));
    await page.goto('http://chat.test/conversation');
    await page.addScriptTag({ content: renderer });
    await page.evaluate(() => {
        const bubble = { body: document.getElementById('message') };
        renderRedirectPage(bubble, '/analysis');
        renderRedirectPage(bubble, '/analysis');
    });
    await expect(page).toHaveURL('http://chat.test/conversation');
    await expect(page.getByRole('textbox', { name: 'Chat input' })).toBeVisible();
    await expect(page.locator('iframe')).toHaveCount(1);
    await expect(page.frameLocator('iframe').getByRole('heading')).toHaveText('Correlation result');
    await expect(page.getByRole('link', { name: 'Open in new tab' })).toHaveAttribute('target', '_blank');
    expect(source).toContain('renderRedirectPage(bubble, meta.redirect_url)');
    expect(source).toContain('renderRedirectPage(bubble, event.redirect_url)');
    expect(source).not.toContain('window.location.assign(destination.href)');
});

test('embedded result renderer rejects external and script URLs', async ({ page }) => {
    await page.route('http://chat.test/**', route => route.fulfill({ contentType: 'text/html', body: '<main id="message"></main>' }));
    await page.goto('http://chat.test/conversation');
    await page.addScriptTag({ content: renderer });
    await page.evaluate(() => {
        for (const url of ['https://external.test/result', 'javascript:alert(1)', 'http://[']) {
            renderRedirectPage({ body: document.getElementById('message') }, url);
        }
    });
    await expect(page.locator('iframe')).toHaveCount(0);
});
