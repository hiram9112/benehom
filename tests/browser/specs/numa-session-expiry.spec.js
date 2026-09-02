const { test, expect } = require('@playwright/test');
const { createPrivateUser, deletePrivateUser } = require('../helpers/private-user');

const privateStatusUrl = /\/index\.php\?r=numa\/status$/;
const privateChatUrl = /\/index\.php\?r=numa\/chat$/;
const privateNewConversationUrl = /\/index\.php\?r=numa\/conversation\/new$/;
const loginUrl = /\?r=auth\/login$/;

const conversation = [
    { role: 'user', message: 'Pregunta anterior.' },
    { role: 'assistant', message: 'Respuesta anterior.' },
];

const availableStatus = (entries = conversation) => JSON.stringify({
    ok: true,
    data: { availability: 'available', conversation: entries },
});

const expiredSession = JSON.stringify({
    ok: false,
    error: {
        code: 'UNAUTHENTICATED',
        message: 'Tu sesión se ha cerrado por inactividad. Vuelve a iniciar sesión para continuar.',
    },
});

async function login(page, user) {
    await page.goto('/index.php?r=auth/login');
    await page.getByLabel('Correo electrónico:').fill(user.email);
    await page.getByLabel('Contraseña:').fill(user.password);
    await page.getByRole('button', { name: 'Iniciar sesión' }).click();
    await expect(page).toHaveURL(/\?r=dashboard\/index$/);
}

async function openPrivateNuma(page) {
    await page.getByRole('button', { name: 'Abrir Numa' }).click();
    await expect(page.locator('[data-numa-input]')).toBeEnabled();
}

async function withPrivateUser(page, callback) {
    const user = createPrivateUser();

    try {
        await callback(user);
    } finally {
        deletePrivateUser(user.email);
    }
}

test('una sesión válida inicia una nueva conversación privada', async ({ page }) => {
    await withPrivateUser(page, async (user) => {
        await page.route(privateStatusUrl, (route) => route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: availableStatus(),
        }));
        await page.route(privateNewConversationUrl, (route) => route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: availableStatus([]),
        }));
        await login(page, user);
        await openPrivateNuma(page);

        await page.locator('[data-numa-new-conversation]').click();
        await page.getByRole('button', { name: 'Empezar de nuevo' }).click();

        await expect(page.locator('[data-numa-canonical-message="true"]')).toHaveCount(0);
        await expect(page.locator('[data-numa-status]')).toHaveText('Nueva conversación iniciada.');
    });
});

test('una sesión caducada al iniciar una nueva conversación privada redirige al login', async ({ page }) => {
    await withPrivateUser(page, async (user) => {
        await page.route(privateStatusUrl, (route) => route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: availableStatus(),
        }));
        await page.route(privateNewConversationUrl, (route) => route.fulfill({
            status: 401,
            contentType: 'application/json',
            body: expiredSession,
        }));
        await login(page, user);
        await openPrivateNuma(page);

        await page.locator('[data-numa-new-conversation]').click();
        await page.getByRole('button', { name: 'Empezar de nuevo' }).click();

        await expect(page).toHaveURL(loginUrl);
    });
});

test('una sesión caducada al enviar un mensaje privado redirige al login', async ({ page }) => {
    await withPrivateUser(page, async (user) => {
        await page.route(privateStatusUrl, (route) => route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: availableStatus([]),
        }));
        await page.route(privateChatUrl, (route) => route.fulfill({
            status: 401,
            contentType: 'application/json',
            body: expiredSession,
        }));
        await login(page, user);
        await openPrivateNuma(page);

        await page.locator('[data-numa-input]').fill('¿Cómo añado un movimiento?');
        await page.getByRole('button', { name: 'Enviar mensaje' }).click();

        await expect(page).toHaveURL(loginUrl);
    });
});

test('una sesión caducada al consultar el estado privado redirige al login', async ({ page }) => {
    await withPrivateUser(page, async (user) => {
        await page.route(privateStatusUrl, (route) => route.fulfill({
            status: 401,
            contentType: 'application/json',
            body: expiredSession,
        }));
        await login(page, user);
        await page.getByRole('button', { name: 'Abrir Numa' }).click();

        await expect(page).toHaveURL(loginUrl);
    });
});

test('el contrato 401 de NUMA público no redirige al login', async ({ page }) => {
    await page.route(/\/index\.php\?r=numa\/public\/status$/, (route) => route.fulfill({
        status: 401,
        contentType: 'application/json',
        body: expiredSession,
    }));
    await page.goto('/index.php?r=home/index');
    await page.getByRole('button', { name: 'Abrir Numa' }).click();

    await expect(page).toHaveURL(/\?r=home\/index$/);
    await expect(page.locator('[data-numa-messages]')).toContainText('No se ha podido validar tu identidad temporal.');
});
