module.exports = {
    env: {
        node: true,
        es6: true,
        jest: true
    },
    parserOptions: {
        ecmaVersion: 11,
        sourceType: "module",
        ecmaFeatures: {
            modules: true,
            experimentalObjectRestSpread: true
        }
    },
    extends: ["eslint:recommended", "google", "prettier"],
    ignorePatterns: ["lib/*"],
    plugins: ["prettier"],
    rules: {
        "operator-linebreak": [
            1,
            "after",
            {
                overrides: {
                    "?": "ignore",
                    ":": "ignore"
                }
            }
        ],
        "prettier/prettier": ["warn"],
        "newline-before-return": "warn",
        "max-len": [
            "error",
            {
                code: 120,
                ignoreComments: true,
                ignoreStrings: true,
                ignoreTemplateLiterals: true
            }
        ],
        "camelcase": "off",
        "new-cap": "off",
        "quotes": "off",
        "indent": "off",
        "no-useless-escape": "off",
        "space-before-function-paren": "off",
        "no-constant-condition": ["error", { checkLoops: false }],
        "object-curly-spacing": ["error", "always"],
        "comma-dangle": ["error", "never"],
        "arrow-parens": ["error", "as-needed"]
    }
};
