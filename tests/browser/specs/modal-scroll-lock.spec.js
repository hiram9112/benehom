const { test, expect } = require('@playwright/test');
const { createPrivateUser, deletePrivateUser } = require('../helpers/private-user');

async function login(page, user) {
    await page.goto('/index.php?r=auth/login');
    await page.getByLabel('Correo electrónico:').fill(user.email);
    await page.locator('input[name="password"]').fill(user.password);
    await page.getByRole('button', { name: 'Iniciar sesión' }).click();
    await expect(page).toHaveURL(/\?r=dashboard\/index$/);
}

test('los modales informativos mantienen el sidebar y el scroll del dashboard', async ({ page }) => {
    const user = createPrivateUser();
    const pageErrors = [];
    const consoleErrors = [];

    try {
        await page.setViewportSize({ width: 1280, height: 720 });
        page.on('pageerror', (error) => pageErrors.push(error.message));
        page.on('console', (message) => {
            if (message.type() === 'error') consoleErrors.push(message.text());
        });
        await login(page, user);

        for (const modalId of ['infoHistoriaMes', 'infoGastosFlexibles']) {
            await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));

            const before = await page.evaluate(() => {
                const sidebar = document.querySelector('.bh-sidebar');

                return {
                    scrollY: window.scrollY,
                    sidebarTop: sidebar?.getBoundingClientRect().top,
                };
            });

            expect(before.scrollY).toBeGreaterThan(0);
            await page.locator(`[data-bs-target="#${modalId}"]`).evaluate((trigger) => trigger.click());
            await expect(page.locator(`#${modalId}`)).toHaveClass(/show/);
            await expect(page.locator('html')).toHaveClass(/bh-modal-scroll-locked/);

            expect(await page.evaluate(() => ({
                scrollY: window.scrollY,
                sidebarTop: document.querySelector('.bh-sidebar')?.getBoundingClientRect().top,
                bodyOverflow: getComputedStyle(document.body).overflow,
            }))).toEqual({
                scrollY: before.scrollY,
                sidebarTop: before.sidebarTop,
                bodyOverflow: 'visible',
            });

            await page.locator(`#${modalId} [data-bs-dismiss="modal"]`).first().click();
            await expect(page.locator(`#${modalId}`)).not.toHaveClass(/show/);
            await expect(page.locator('html')).not.toHaveClass(/bh-modal-scroll-locked/);
            expect(await page.evaluate(() => window.scrollY)).toBe(before.scrollY);
        }

        expect(pageErrors).toEqual([]);
        expect(consoleErrors).toEqual([]);
    } finally {
        deletePrivateUser(user.email);
    }
});
