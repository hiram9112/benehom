const testDatabaseEnvironment = {
    DB_HOST: process.env.DB_HOST || '127.0.0.1',
    DB_PORT: process.env.DB_PORT || '3306',
    DB_NAME: 'benehom_test',
    DB_USER: process.env.DB_USER || 'benehom_test_user',
    DB_PASS: process.env.DB_PASS || 'test_password_123',
};

module.exports = { testDatabaseEnvironment };
