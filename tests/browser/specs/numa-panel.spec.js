const { test, expect } = require('@playwright/test');

const homeUrl = '/index.php?r=home/index';

function numaPanel(page) {
    return page.locator('[data-numa-panel]');
}

async function openNuma(page) {
    await page.getByRole('button', { name: 'Abrir Numa' }).click();
}

test('abre el panel y lo cierra al pulsar su control', async ({ page }) => {
    await page.goto(homeUrl);

    await openNuma(page);
    await expect(numaPanel(page)).toBeVisible();
    await expect(page.locator('[data-numa-close]')).toBeVisible();
    await expect(page.locator('[data-numa-launcher]')).toHaveAttribute('aria-expanded', 'true');

    await page.locator('[data-numa-close]').click();
    await expect(page.getByRole('button', { name: 'Abrir Numa' })).toHaveAttribute('aria-expanded', 'false');
    await expect(numaPanel(page)).toBeHidden();
});

test('aplica la animacion de entrada cuando no se reduce el movimiento', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.goto(homeUrl);
    await page.evaluate(() => {
        window.requestAnimationFrame = () => 1;
        window.cancelAnimationFrame = () => {};
    });

    await openNuma(page);

    await expect(numaPanel(page)).toHaveClass(/is-numa-entering/);
    await expect(numaPanel(page)).toHaveCSS('opacity', '0');
    await expect(numaPanel(page)).toHaveCSS('transition-duration', '0.22s, 0.22s');
});

test('omite las transiciones del panel cuando se reduce el movimiento', async ({ page }) => {
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto(homeUrl);

    await openNuma(page);
    await expect(numaPanel(page)).toBeVisible();
    await expect(numaPanel(page)).not.toHaveClass(/is-numa-entering/);

    await page.locator('[data-numa-close]').click();
    await expect(numaPanel(page)).toBeHidden();
});

test('mantiene el compositor visible y utilizable mientras el panel esta abierto', async ({ page }) => {
    await page.route(/\/index\.php\?r=numa\/public\/status$/, (route) => route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
            ok: true,
            data: {
                availability: 'available',
                conversation: [],
            },
        }),
    }));
    await page.goto(homeUrl);

    await openNuma(page);

    const input = page.locator('[data-numa-input]');
    await expect(page.locator('.bh-numa-composer')).toBeVisible();
    await expect(input).toBeEnabled();
    await input.fill('Necesito revisar mis gastos.');
    await expect(input).toHaveValue('Necesito revisar mis gastos.');
    await expect(page.getByRole('button', { name: 'Enviar mensaje' })).toBeEnabled();
});
