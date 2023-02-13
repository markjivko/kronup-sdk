module.exports = {
    arrowParens: "avoid",
    bracketSpacing: true,
    htmlWhitespaceSensitivity: "css",
    insertPragma: false,
    bracketSameLine: true,
    jsxSingleQuote: false,
    printWidth: 120,
    proseWrap: "preserve",
    quoteProps: "consistent",
    requirePragma: false,
    semi: true,
    singleQuote: false,
    tabWidth: 4,
    trailingComma: "none",
    useTabs: false,
    overrides: [
        {
            files: "*.php",
            options: {
                phpVersion: "8.1",
                printWidth: 120,
                braceStyle: "1tbs",
                trailingCommaPHP: false
            }
        }
    ]
};
