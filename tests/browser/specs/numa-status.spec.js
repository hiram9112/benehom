const { test, expect } = require('@playwright/test');
const { configureNumaScenario } = require('../helpers/numa-scenario');

test('el panel consulta el estado real de Numa desde la interfaz', async ({ context, page }) => {
    await configureNumaScenario(context, 'success');
    await page.goto('/index.php?r=home/index');
    const statusResponse = page.waitForResponse((response) => {
        const url = new URL(response.url());

        return url.searchParams.get('r') === 'numa/public/status'
            && response.request().method() === 'GET';
    });
    await page.getByRole('button', { name: 'Abrir Numa' }).click();

    const response = await statusResponse;
    expect(response.status()).toBe(200);
    await expect(response.json()).resolves.toEqual({
        ok: true,
        data: {
            availability: 'unavailable',
            conversation: [],
        },
    });
    await expect(page.locator('[data-numa-input]')).toBeDisabled();
});
