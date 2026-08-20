const { execFileSync } = require('child_process');
const path = require('path');
const { testDatabaseEnvironment } = require('./test-environment');

const fixturePath = path.join(__dirname, 'private-user.php');

function runFixture(action, email) {
    return JSON.parse(execFileSync('php', [fixturePath, action, email], {
        encoding: 'utf8',
        env: {
            ...process.env,
            APP_ENV: 'testing',
            ...testDatabaseEnvironment,
        },
    }));
}

function createPrivateUser() {
    const email = `pw-numa-${Date.now().toString(36)}-${process.pid}@t.test`;

    return runFixture('create', email);
}

function deletePrivateUser(email) {
    runFixture('delete', email);
}

module.exports = { createPrivateUser, deletePrivateUser };
