const { defineConfig } = require('@playwright/test');

module.exports = defineConfig({
  testDir: './tests/e2e',
  use: { baseURL: 'http://localhost:8888', browserName: 'chromium' },
  projects: [
    { name: 'mobile-chrome', use: { viewport: { width: 390, height: 844 } } },
    { name: 'desktop-chrome', use: { viewport: { width: 1440, height: 900 } } }
  ]
});
