import js from '@eslint/js';
import globals from 'globals';
import prettier from 'eslint-config-prettier';

export default [
    // Ignore build artifacts and dependencies.
    {
        ignores: ['public/build/**', 'node_modules/**', 'vendor/**', 'bootstrap/ssr/**'],
    },

    // Recommended JS rules.
    js.configs.recommended,

    // Project JS — browser globals, ES modules.
    {
        files: ['resources/js/**/*.js'],
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: {
                ...globals.browser,
            },
        },
    },

    // Must stay LAST: turns off ESLint rules that conflict with Prettier
    // so the two tools never disagree on formatting.
    prettier,
];
