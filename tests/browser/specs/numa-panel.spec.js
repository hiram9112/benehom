const { test, expect } = require('@playwright/test');

const homeUrl = '/index.php?r=home/index';

function numaPanel(page) {
    return page.locator('[data-numa-panel]');
}

async function openNuma(page) {
    await page.getByRole('button', { name: 'Abrir Numa' }).click();
}

async function mockAvailableStatus(page, conversation = []) {
    await page.route(/\/index\.php\?r=numa\/public\/status$/, (route) => route.fulfill({
        status: 200,
        contentType: 'application/json',
        body: JSON.stringify({
            ok: true,
            data: {
                availability: 'available',
                conversation,
            },
        }),
    }));
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
    await mockAvailableStatus(page);
    await page.goto(homeUrl);

    await openNuma(page);

    const input = page.locator('[data-numa-input]');
    await expect(page.locator('.bh-numa-composer')).toBeVisible();
    await expect(input).toBeEnabled();
    await input.fill('Necesito revisar mis gastos.');
    await expect(input).toHaveValue('Necesito revisar mis gastos.');
    await expect(page.getByRole('button', { name: 'Enviar mensaje' })).toBeEnabled();
});

test('envia una consulta, muestra Pensando y revela solo la respuesta nueva progresivamente', async ({ page }) => {
    const question = '¿Cómo añado un movimiento?';
    const answer = 'Esta es una respuesta progresiva de prueba con suficientes palabras para observar su revelado pausado antes de completarse.';
    const statusConversation = [];
    let fulfillChat;

    await mockAvailableStatus(page, statusConversation);
    await page.route(/\/index\.php\?r=numa\/public\/chat$/, (route) => new Promise((resolve) => {
        fulfillChat = async () => {
            statusConversation.push(
                { role: 'user', message: question },
                { role: 'assistant', message: answer }
            );
            await route.fulfill({
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
            });
            resolve();
        };
    }));
    await page.goto(homeUrl);
    await openNuma(page);

    const chatRequest = page.waitForRequest((request) => {
        const url = new URL(request.url());

        return url.searchParams.get('r') === 'numa/public/chat' && request.method() === 'POST';
    });
    await page.locator('[data-numa-input]').fill(question);
    await page.getByRole('button', { name: 'Enviar mensaje' }).click();

    const request = await chatRequest;
    expect(request.postDataJSON()).toEqual({ message: question });
    await expect(page.locator('[data-numa-messages]')).toContainText(question);
    await expect(page.locator('[data-numa-messages]')).toContainText('Pensando…');

    await fulfillChat();

    const progressiveMessage = page.locator('[data-numa-messages] .bh-numa-message.is-assistant').last();
    await expect(page.locator('[data-numa-messages]')).not.toContainText('Pensando…');
    await expect(progressiveMessage.locator('.bh-numa-message-content > p')).toContainText('Esta es una respuesta progresiva');
    await expect(progressiveMessage.locator('.bh-numa-message-content > p')).not.toHaveText(answer);
    await expect(page.locator('[data-numa-messages] .bh-numa-message.is-assistant').last().locator('.bh-numa-message-content > p')).toHaveText(answer);
});

test('restaura el transcript completo sin revelar progresivamente sus respuestas', async ({ page }) => {
    const conversation = [
        { role: 'user', message: '¿Qué son los gastos flexibles?' },
        { role: 'assistant', message: 'Los gastos flexibles son aquellos que puedes ajustar según tus necesidades.' },
    ];

    await mockAvailableStatus(page, conversation);
    await page.goto(homeUrl);
    await openNuma(page);

    const restoredMessages = page.locator('[data-numa-messages] [data-numa-canonical-message="true"]');
    await expect(restoredMessages).toHaveCount(2);
    await expect(restoredMessages.nth(1).locator('.bh-numa-message-content > p')).toHaveText(conversation[1].message);
    await expect(page.locator('[data-numa-messages] .bh-numa-message.is-assistant').last()).toHaveAttribute('data-numa-canonical-message', 'true');
});
