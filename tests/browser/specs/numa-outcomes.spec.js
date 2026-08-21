const { test, expect } = require('@playwright/test');

const homeUrl = '/index.php?r=home/index';

function messages(page) {
    return page.locator('[data-numa-messages]');
}

async function openAvailableNuma(page, afterChatAvailability = 'available', conversation = []) {
    let statusRequests = 0;

    await page.route(/\/index\.php\?r=numa\/public\/status$/, (route) => {
        const availability = statusRequests === 0 ? 'available' : afterChatAvailability;
        statusRequests += 1;

        return route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                ok: true,
                data: { availability, conversation },
            }),
        });
    });
    await page.goto(homeUrl);
    await page.getByRole('button', { name: 'Abrir Numa' }).click();
    await expect(page.locator('[data-numa-input]')).toBeEnabled();
}

async function submitMessage(page, message, expectThinking = true) {
    await page.locator('[data-numa-input]').fill(message);
    await page.getByRole('button', { name: 'Enviar mensaje' }).click();

    if (expectThinking) {
        await expect(messages(page)).toContainText('Pensando…');
    }
}

async function mockDeferredChat(page, response) {
    let fulfillChat;

    await page.route(/\/index\.php\?r=numa\/public\/chat$/, (route) => new Promise((resolve) => {
        fulfillChat = async () => {
            await route.fulfill(response);
            resolve();
        };
    }));

    return () => fulfillChat();
}

test('presenta los mensajes de usuario y Numa con sus roles, alineacion y estilos propios', async ({ page }) => {
    const question = '¿Cómo añado un movimiento?';
    const answer = 'Puedes añadir un movimiento desde el formulario correspondiente.';
    const conversation = [];

    await openAvailableNuma(page, 'available', conversation);
    await page.route(/\/index\.php\?r=numa\/public\/chat$/, (route) => {
        conversation.push(
            { role: 'user', message: question },
            { role: 'assistant', message: answer }
        );

        return route.fulfill({
            status: 200,
            contentType: 'application/json',
            body: JSON.stringify({
                ok: true,
                data: { message: answer, availability: 'available', conversation },
            }),
        });
    });

    await submitMessage(page, question, false);

    const userMessage = messages(page).locator('[data-numa-role="user"]').last();
    const assistantMessage = messages(page).locator('[data-numa-role="assistant"][data-numa-canonical-message="true"]').last();
    await expect(assistantMessage.locator('.bh-numa-message-content > p')).toHaveText(answer);
    await expect(userMessage).toHaveClass(/is-user/);
    await expect(assistantMessage).toHaveClass(/is-assistant/);
    await expect(userMessage).toHaveText(`Tú: ${question}`);
    await expect(assistantMessage).toHaveText(`Numa: ${answer}`);
    await expect(userMessage).toHaveCSS('justify-content', 'flex-end');
    await expect(assistantMessage).toHaveCSS('justify-content', 'flex-start');

    const userContent = userMessage.locator('.bh-numa-message-content');
    const assistantContent = assistantMessage.locator('.bh-numa-message-content');
    const userStyles = await userContent.evaluate((element) => {
        const styles = window.getComputedStyle(element);

        return { background: styles.backgroundColor, color: styles.color, paddingLeft: styles.paddingLeft };
    });
    const assistantStyles = await assistantContent.evaluate((element) => {
        const styles = window.getComputedStyle(element);

        return { background: styles.backgroundColor, paddingLeft: styles.paddingLeft };
    });

    expect(userStyles.background).not.toBe(assistantStyles.background);
    expect(userStyles.color).toBe('rgb(253, 254, 253)');
    expect(Number.parseFloat(userStyles.paddingLeft)).toBeGreaterThan(0);
    expect(Number.parseFloat(assistantStyles.paddingLeft)).toBe(0);
    await expect(page.locator('[data-numa-new-conversation]')).toBeEnabled();
});

test('retira Pensando y muestra un estado de error seguro cuando falla la consulta', async ({ page }) => {
    await openAvailableNuma(page, 'unavailable');
    const fulfillChat = await mockDeferredChat(page, {
        status: 503,
        contentType: 'application/json',
        body: JSON.stringify({
            ok: false,
            error: { code: 'NUMA_PROVIDER_UNAVAILABLE', message: 'No exponer este detalle.' },
        }),
    });

    await submitMessage(page, '¿Cómo añado un movimiento?');
    await fulfillChat();

    const error = messages(page).locator('[data-numa-state-message="Ahora no puedo atender consultas. Inténtalo de nuevo más tarde."]');
    await expect(error).toBeVisible();
    await expect(error).toHaveClass(/is-assistant/);
    await expect(error).toHaveClass(/is-error/);
    await expect(messages(page)).not.toContainText('Pensando…');
    await expect(page.locator('[data-numa-input]')).toBeDisabled();
});

test('retira Pensando y explica el timeout sin revelar detalles del proveedor', async ({ page }) => {
    const timeoutText = 'He tardado demasiado en responder. La consulta podría haberse enviado y haber consumido cuota. Comprueba el estado antes de volver a intentarlo.';

    await openAvailableNuma(page, 'unavailable');
    const fulfillChat = await mockDeferredChat(page, {
        status: 504,
        contentType: 'application/json',
        body: JSON.stringify({
            ok: false,
            error: { code: 'NUMA_PROVIDER_TIMEOUT', message: 'timeout interno' },
        }),
    });

    await submitMessage(page, '¿Cómo añado un movimiento?');
    await fulfillChat();

    const timeout = messages(page).locator(`[data-numa-state-message="${timeoutText}"]`);
    await expect(timeout).toBeVisible();
    await expect(timeout).toHaveClass(/is-assistant/);
    await expect(timeout).toHaveClass(/is-error/);
    await expect(messages(page)).not.toContainText('Pensando…');
    await expect(timeout).not.toContainText('timeout interno');
});

test('retira Pensando, comunica el limite y bloquea el compositor', async ({ page }) => {
    const limitText = 'Has alcanzado el límite de uso. Podrás volver a utilizarlo cuando se renueve.';

    await openAvailableNuma(page, 'limit_reached');
    const fulfillChat = await mockDeferredChat(page, {
        status: 429,
        contentType: 'application/json',
        body: JSON.stringify({
            ok: false,
            error: { code: 'NUMA_LIMIT_REACHED', message: 'Límite interno.' },
        }),
    });

    await submitMessage(page, '¿Cómo añado un movimiento?');
    await fulfillChat();

    const limit = messages(page).locator(`[data-numa-state-message="${limitText}"]`);
    await expect(limit).toBeVisible();
    await expect(limit).toHaveClass(/is-assistant/);
    await expect(limit).toHaveClass(/is-warning/);
    await expect(messages(page)).not.toContainText('Pensando…');
    await expect(page.locator('[data-numa-input]')).toBeDisabled();
    await expect(page.getByRole('button', { name: 'Enviar mensaje' })).toBeDisabled();
});
