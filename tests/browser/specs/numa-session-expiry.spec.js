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
        await page.evaluate(() => {
            Math.random = () => 0;
        });
        await openPrivateNuma(page);

        await page.locator('[data-numa-new-conversation]').click();
        await page.getByRole('button', { name: 'Empezar de nuevo' }).click();

        await expect(page.locator('[data-numa-canonical-message="true"]')).toHaveCount(0);
        await expect(page.locator('[data-numa-initial-greeting]')).toHaveText('Hola Usuario Playwright.\n¿En qué puedo ayudarte?');
        await expect(page.locator('[data-numa-initial-prompt]')).toHaveCount(0);
        await expect(page.locator('[data-numa-status]')).toHaveText('Nueva conversación iniciada.');
    });
});

test('muestra las cuatro variantes privadas como texto seguro', async ({ page }) => {
    const userName = '<img src=x onerror=alert(1)>';
    const user = createPrivateUser(userName);

    try {
        await page.route(privateStatusUrl, (route) => route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: availableStatus([]),
        }));
        await login(page, user);
        const variants = [
            [0, `Hola ${userName}.\n¿En qué puedo ayudarte?`],
            [0.3, `¿Qué te gustaría consultar ${userName}?`],
            [0.6, `¿Hay algo que quieras revisar ${userName}?`],
            [0.9, `¿Por dónde quieres empezar ${userName}?`],
        ];

        for (const [randomValue, expectedGreeting] of variants) {
            await page.evaluate((value) => {
                Math.random = () => value;
            }, randomValue);
            await openPrivateNuma(page);

            const greeting = page.locator('[data-numa-initial-greeting]');
            await expect(greeting.locator('img')).toHaveCount(0);
            await expect(greeting).toHaveText(expectedGreeting);
            await expect(page.locator('[data-numa-initial-prompt]')).toHaveCount(0);

            if (randomValue === 0) {
                expect(await greeting.evaluate((element) => getComputedStyle(element).whiteSpace)).toBe('pre-line');
            }

            if (randomValue !== 0.9) {
                await page.locator('[data-numa-close]').click();
            }
        }
    } finally {
        deletePrivateUser(user.email);
    }
});

test('el estado inicial privado es texto informativo y no envía mensajes al pulsarlo', async ({ page }) => {
    await withPrivateUser(page, async (user) => {
        let chatRequests = 0;

        await page.route(privateStatusUrl, (route) => route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: availableStatus([]),
        }));
        await page.route(privateChatUrl, (route) => {
            chatRequests += 1;
            return route.fulfill({ status: 500 });
        });
        await login(page, user);
        await page.evaluate(() => {
            Math.random = () => 0;
        });
        await openPrivateNuma(page);

        await page.locator('[data-numa-initial-greeting]').click({ force: true });

        expect(await page.locator('[data-numa-initial-greeting]').evaluate((element) => getComputedStyle(element).pointerEvents)).toBe('none');
        await expect(page.locator('[data-numa-initial-prompt]')).toHaveCount(0);
        await expect(page.locator('[data-numa-messages] .bh-numa-message.is-user')).toHaveCount(0);
        await expect(page.locator('[data-numa-input]')).toHaveValue('');
        expect(chatRequests).toBe(0);
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
