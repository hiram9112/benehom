const { test, expect } = require('@playwright/test');

const homeUrl = '/index.php?r=home/index';

function menu(page) {
    return page.locator('#bh-home-mobile-menu');
}

async function openMenu(page) {
    await page.getByRole('button', { name: 'Abrir menú' }).click();
    await expect(menu(page)).toHaveClass(/show/);
}

test.describe('home sin Bootstrap CSS', () => {
    test.use({ viewport: { width: 390, height: 844 } });

    test('conserva el offcanvas, foco, scroll, Lenis y cierre por boton o Escape', async ({ page }) => {
        await page.goto(homeUrl);

        await expect(page.locator('link[href*="bootstrap@5.3.2/dist/css/bootstrap.min.css"]')).toHaveCount(0);
        await expect(page.locator('script[src*="bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"]')).toHaveCount(1);
        await expect(page.locator('.bh-home-numa-stage .visually-hidden')).toHaveCSS('position', 'absolute');
        await expect(page.locator('[data-numa-status]')).toHaveCSS('width', '1px');

        await openMenu(page);
        await expect(page.locator('.offcanvas-backdrop')).toHaveClass(/show/);
        await expect(page.locator('body')).toHaveCSS('overflow-y', 'hidden');
        await expect(page.locator('html')).toHaveClass(/lenis-stopped/);
        await expect(menu(page)).toHaveAttribute('role', 'dialog');
        await expect(menu(page)).toHaveAttribute('aria-modal', 'true');
        await expect(menu(page)).toBeFocused();

        await page.locator('.bh-home-mobile-signup').focus();
        await page.keyboard.press('Tab');
        await expect(page.locator('.bh-home-mobile-menu [data-bs-dismiss="offcanvas"]')).toBeFocused();

        await page.locator('.bh-home-mobile-menu [data-bs-dismiss="offcanvas"]').click();
        await expect(menu(page)).not.toHaveClass(/show/);
        await expect(page.locator('.offcanvas-backdrop')).toHaveCount(0);
        await expect(page.locator('body')).toHaveCSS('overflow-y', 'auto');
        await expect(page.locator('html')).not.toHaveClass(/lenis-stopped/);
        await expect(page.getByRole('button', { name: 'Abrir menú' })).toBeFocused();

        await openMenu(page);
        await expect(menu(page)).toBeFocused();
        await page.keyboard.press('Escape');
        await expect(menu(page)).not.toHaveClass(/show/);
        await expect(page.getByRole('button', { name: 'Abrir menú' })).toBeFocused();
    });

    test('cierra al pulsar el backdrop', async ({ page }) => {
        await page.goto(homeUrl);
        await openMenu(page);

        await page.locator('.offcanvas-backdrop').click({ position: { x: 385, y: 10 } });

        await expect(menu(page)).not.toHaveClass(/show/);
        await expect(page.locator('.offcanvas-backdrop')).toHaveCount(0);
    });
});

test('oculta el menu movil a partir de 992px', async ({ page }) => {
    await page.setViewportSize({ width: 992, height: 844 });
    await page.goto(homeUrl);

    await expect(menu(page)).toBeHidden();
    await expect(page.getByRole('button', { name: 'Abrir menú' })).toBeHidden();
});
