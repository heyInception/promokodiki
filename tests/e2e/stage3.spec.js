const { test, expect } = require('@playwright/test');

test('menu link and code dialog', async ({ page }) => {
  await page.goto('/');
  const toggle = page.locator('.menu-toggle');
  if (await toggle.isVisible()) await toggle.click();
  const shopLink = page.locator('#primary-menu a').filter({ hasText: 'Тестовый магазин' }).first();
  await expect(shopLink).toHaveAttribute('href', /taxonomy=shops_category.*term=test-shop/);
  await page.goto('/?pagename=discounts');
  await page.locator('.promocodes__view:visible').first().click();
  const dialog = page.getByRole('dialog', { name: /Тестовый промокод/ });
  await expect(dialog).toBeVisible();
  await page.keyboard.press('Escape');
  await expect(dialog).toBeHidden();
});

test('discount sort, load more and browser back', async ({ page }) => {
  await page.goto('/?pagename=discounts');
  await page.locator('[data-filter-sort="newest"]').click();
  await expect(page).toHaveURL(/paf_sort=newest/);
  const more = page.locator('[data-filter-more]');
  if (await more.isVisible()) await more.click();
  await page.goBack();
  await expect(page).toHaveURL(/[?&]pagename=discounts(?:&|$)/);
});
