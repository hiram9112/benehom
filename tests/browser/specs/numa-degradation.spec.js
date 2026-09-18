const { test, expect } = require('@playwright/test');

const homeUrl = '/index.php?r=home/index';

async function mockAvailableStatus(page) {
    await page.route(/\/index\.php\?r=numa\/public\/status$/, (route) => route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
            ok: true,
            data: { availability: 'available', conversation: [] },
        }),
    }));
}

test('muestra un error de red seguro sin reintentar automáticamente la consulta', async ({ page }) => {
    let chatRequests = 0;

    await mockAvailableStatus(page);
    await page.route(/\/index\.php\?r=numa\/public\/chat$/, (route) => {
        chatRequests += 1;
        return route.abort('connectionfailed');
    });
    await page.goto(homeUrl);
    await page.getByRole('button', { name: 'Abrir Numa' }).click();
    await expect(page.locator('[data-numa-input]')).toBeEnabled();

    await page.locator('[data-numa-input]').fill('¿Cómo añado un movimiento?');
    await page.getByRole('button', { name: 'Enviar mensaje' }).click();

    await expect(page.locator('[data-numa-messages]')).toContainText('No he podido conectar en este momento.');
    await page.waitForTimeout(300);
    expect(chatRequests).toBe(1);
    await expect(page.locator('[data-numa-input]')).toBeEnabled();
});

test('conserva el panel operativo y el personaje estático cuando GSAP no carga', async ({ page }) => {
    await mockAvailableStatus(page);
    await page.route(/\/js\/vendor\/gsap\/gsap\.min\.js(?:\?.*)?$/, (route) => route.fulfill({
        status: 200,
        contentType: 'application/javascript',
        body: '',
    }));
    await page.goto(homeUrl);

    await expect(page.locator('[data-numa-static]')).toBeVisible();
    await expect(page.locator('[data-numa-animated]')).toBeHidden();
    await page.getByRole('button', { name: 'Abrir Numa' }).click();
    await expect(page.locator('[data-numa-panel]')).toBeVisible();
    await expect(page.locator('[data-numa-input]')).toBeEnabled();
});
