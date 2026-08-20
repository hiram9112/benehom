const SCENARIOS = new Set(['success', 'error', 'timeout', 'limit']);
const HEADER_NAME = 'X-Numa-Testing-Scenario';

async function configureNumaScenario(context, scenario) {
    if (!SCENARIOS.has(scenario)) {
        throw new Error(`Escenario de Numa no soportado: ${scenario}`);
    }

    await context.setExtraHTTPHeaders({ [HEADER_NAME]: scenario });
}

module.exports = { configureNumaScenario };
