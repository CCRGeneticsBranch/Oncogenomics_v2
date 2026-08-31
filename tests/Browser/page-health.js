const { expect } = require('@playwright/test');

function monitorPageHealth(page) {
  const failures = [];

  page.on('pageerror', error => failures.push(`pageerror: ${error.message}`));
  page.on('console', message => {
    if (message.type() === 'error' && !message.text().startsWith('Failed to load resource:')) {
      failures.push(`console: ${message.text()}`);
    }
  });
  page.on('response', response => {
    if (response.status() >= 400) {
      failures.push(`http ${response.status()} (${response.request().resourceType()}): ${response.url()}`);
    }
  });

  return async () => {
    await page.waitForLoadState('domcontentloaded');
    await page.waitForTimeout(500);
    expect(failures, failures.join('\n')).toEqual([]);
  };
}

module.exports = { monitorPageHealth };
