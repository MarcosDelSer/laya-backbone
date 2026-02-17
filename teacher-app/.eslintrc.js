module.exports = {
  root: true,
  extends: '@react-native',
  rules: {
    'react/react-in-jsx-scope': 'off',

    // Security: Prevent use of eval() and similar dangerous functions that can execute arbitrary code
    'no-eval': 'error',
    'no-implied-eval': 'error',
    'no-new-func': 'error',

    // Security: Prevent javascript: protocol URLs (XSS vector)
    'no-script-url': 'error',

    // Security: React-specific XSS prevention - warn about dangerouslySetInnerHTML usage
    'react/no-danger': 'warn',
    'react/no-danger-with-children': 'error',

    // Security: Prevent usage of __proto__ which can lead to prototype pollution attacks
    'no-proto': 'error',

    // Security: Prevent use of deprecated or unsafe APIs
    'no-caller': 'error',
    'no-extend-native': 'error',

    // Security: Prevent with statement which can cause scope confusion
    'no-with': 'error',
  },
};
