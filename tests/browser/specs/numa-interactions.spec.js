const { test, expect } = require('@playwright/test');
const { createPrivateUser, deletePrivateUser } = require('../helpers/private-user');

const homeUrl = '/index.php?r=home/index';

function messages(page) {
    return page.locator('[data-numa-messages]');
}

async function mockAvailableStatus(page, conversation = []) {
    await page.route(/\/index\.php\?r=numa\/public\/status$/, (route) => route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
            ok: true,
            data: { availability: 'available', conversation },
        }),
    }));
}

async function openAvailableNuma(page, conversation = []) {
    await mockAvailableStatus(page, conversation);
    await page.goto(homeUrl);
    await page.getByRole('button', { name: 'Abrir Numa' }).click();
    await expect(page.locator('[data-numa-input]')).toBeEnabled();
}

test('inicia una nueva conversacion y restablece el panel', async ({ page }) => {
    const conversation = [
        { role: 'user', message: '¿Qué son los gastos flexibles?' },
        { role: 'assistant', message: 'Son gastos que puedes ajustar según tus necesidades.' },
    ];

    await openAvailableNuma(page, conversation);
    await expect(page.locator('[data-numa-canonical-message="true"]')).toHaveCount(2);
    await expect(page.locator('[data-numa-new-conversation]')).toBeEnabled();

    await page.route(/\/index\.php\?r=numa\/public\/conversation\/new$/, (route) => route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
            ok: true,
            data: { availability: 'available', conversation: [] },
        }),
    }));
    page.once('dialog', (dialog) => dialog.accept());

    const newConversationRequest = page.waitForRequest((request) => {
        const url = new URL(request.url());

        return url.searchParams.get('r') === 'numa/public/conversation/new'
            && request.method() === 'POST';
    });
    await page.locator('[data-numa-new-conversation]').click();

    await newConversationRequest;
    await expect(messages(page).locator('[data-numa-canonical-message="true"]')).toHaveCount(0);
    await expect(page.locator('[data-numa-initial]')).toBeVisible();
    await expect(page.locator('[aria-label="Preguntas sugeridas para Numa"] button')).toHaveCount(3);
    await expect(page.locator('[data-numa-new-conversation]')).toBeDisabled();
    await expect(page.locator('[data-numa-input]')).toBeFocused();
    await expect(page.locator('[data-numa-status]')).toHaveText('Nueva conversación iniciada.');
});

test('mantiene Numa privado al navegar entre dashboard, proyecciones y cuenta', async ({ page }) => {
    const user = createPrivateUser();

    try {
        await page.route(/\/index\.php\?r=numa\/status$/, (route) => route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                ok: true,
                data: { availability: 'available', conversation: [] },
            }),
        }));
        await page.goto('/index.php?r=auth/login');
        await page.getByLabel('Correo electrónico:').fill(user.email);
        await page.getByLabel('Contraseña:').fill(user.password);
        await page.getByRole('button', { name: 'Iniciar sesión' }).click();
        await expect(page).toHaveURL(/\?r=dashboard\/index$/);

        for (const view of [
            { name: 'Dashboard', url: /\?r=dashboard\/index$/ },
            { name: 'Proyecciones', url: /\?r=proyecciones\/index$/ },
            { name: 'Cuenta', url: /\?r=cuenta\/index$/ },
        ]) {
            if (view.name !== 'Dashboard') {
                await page.getByRole('link', { name: view.name, exact: true }).first().click();
                await expect(page).toHaveURL(view.url);
            }

            const widget = page.locator('[data-numa-widget]');
            const statusRequest = page.waitForRequest((request) => {
                const url = new URL(request.url());

                return url.searchParams.get('r') === 'numa/status' && request.method() === 'GET';
            });

            await expect(widget).toHaveAttribute('data-numa-mode', 'private');
            await expect(widget).toHaveAttribute('data-numa-status-url', '/index.php?r=numa/status');
            await expect(widget).toHaveAttribute('data-numa-chat-url', '/index.php?r=numa/chat');
            await expect(widget).toHaveAttribute('data-numa-new-conversation-url', '/index.php?r=numa/conversation/new');
            await page.getByRole('button', { name: 'Abrir Numa' }).click();
            await statusRequest;
            await expect(page.locator('[data-numa-panel]')).toBeVisible();
            await expect(page.locator('[data-numa-input]')).toBeEnabled();
            await page.locator('[data-numa-close]').click();
        }
    } finally {
        deletePrivateUser(user.email);
    }
});

test('permite abrir, enviar y cerrar mediante teclado, manteniendo el foco', async ({ page }) => {
    const question = '¿Cómo añado un movimiento?';
    const answer = 'Puedes añadirlo desde el formulario de movimientos.';

    await mockAvailableStatus(page);
    await page.route(/\/index\.php\?r=numa\/public\/chat$/, (route) => route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
            ok: true,
            data: {
                message: answer,
                availability: 'available',
                conversation: [
                    { role: 'user', message: question },
                    { role: 'assistant', message: answer },
                ],
            },
        }),
    }));
    await page.goto(homeUrl);

    const launcher = page.getByRole('button', { name: 'Abrir Numa' });
    await launcher.focus();
    await page.keyboard.press('Enter');

    const input = page.locator('[data-numa-input]');
    await expect(input).toBeEnabled();
    await expect(input).toBeFocused();
    await input.focus();
    await input.fill(question);
    await page.keyboard.press('Shift+Enter');
    await expect(input).toHaveValue(`${question}\n`);

    await input.fill(question);
    await page.keyboard.press('Enter');
    await expect(messages(page).locator('[data-numa-canonical-message="true"]')).toHaveCount(2);

    await page.keyboard.press('Escape');
    await expect(page.locator('[data-numa-panel]')).toBeHidden();
    await expect(launcher).toBeFocused();
});
