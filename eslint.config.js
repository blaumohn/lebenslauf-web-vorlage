import js from '@eslint/js';

export default [
  {
    ignores: [
      'node_modules/**',
      'public/css/**',
      'var/**',
      'vendor/**'
    ]
  },
  js.configs.recommended,
  {
    files: [
      'eslint.config.js',
      'playwright.config.js',
      'tests/**/*.js',
      'tests/**/*.mjs'
    ],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        process: 'readonly',
        console: 'readonly',
        URL: 'readonly',
        document: 'readonly'
      }
    }
  },
  {
    files: [
      '*.config.cjs'
    ],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'commonjs',
      globals: {
        module: 'readonly'
      }
    }
  }
];
