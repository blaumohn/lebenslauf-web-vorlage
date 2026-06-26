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
      'tests/**/*.js'
    ],
    languageOptions: {
      ecmaVersion: 'latest',
      sourceType: 'module',
      globals: {
        process: 'readonly',
        console: 'readonly'
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
