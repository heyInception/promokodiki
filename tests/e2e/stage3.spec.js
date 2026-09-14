const { test, expect } = require('@playwright/test');

test('menu to shop to code and close', async ({ page }) => {
  await page.goto('/');
  const toggle = page.locator('.menu-toggle');
  if (await toggle.isVisible()) await toggle.click();
  await page.getByRole('link', { name: 'Тестовый магазин', exact: true }).click();
  await page.locator('.promocodes__view').first().click();
  const dialog = page.getByRole('dialog', { name: /Тестовый промокод/ });
  await expect(dialog).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
});

test('discount sort, load more and browser back', async ({ page }) => {
  await page.goto('/discounts/');
  await page.locator('[data-filter-sort="newest"]').click();
  await expect(page).toHaveURL(/paf_sort=newest/);
  const more = page.locator('[data-filter-more]');
  if (await more.isVisible()) await more.click();
  await page.goBack();
  await expect(page).toHaveURL(/\/discounts\/?$/);
});
