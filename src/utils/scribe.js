/**
 * Scribe - Support for (()) syntax and generation of extra documentation files not covered by the OpenAPI specification
 * This utility uses "scribe-*.mustache" files; see src/generators/php/template for examples
 *
 * @copyright (c) 2022-2023 kronup.io
 * @author    Mark Jivko
 */
const fs = require("fs-extra");
const path = require("path");
const jsYaml = require("js-yaml");
const Mustache = require("mustache");
const config = require("./config");
const { NodeHtmlMarkdown } = require("node-html-markdown");

/**
 * Scribe operations
 *
 * Usable with ((#opName))(/opName) syntax in post-processing
 * Usable with {{#opName}}{{/opName}} syntax in parsed scribe files
 */
const operations = {
    fluent: str => str.toLowerCase().replace(/\W+/g, "()->") + "()",
    anchorText: str => str.toLowerCase().replace(/\W/g, "-"),
    cleanHtml: str => {
        str = operations.fixHashLinks(str);

        // HTML to Markdown
        return NodeHtmlMarkdown.translate(
            str
                // Better delimiter for tables
                .replace(/<\s*table\s*>/gi, "<br><hr><table>")
                .replace(/<\s*\/\s*table\s*>/gi, "</table><hr><br>")
                // Extra space
                .replace(/<\s*br\s*\/?\s*>/gi, "\n\n")
                // Pre to code
                .replace(/<\s*(\/?)\s*pre\s*>/gi, "<$1code>"),
            { useInlineLinks: false }
        )
            .replace(/^---\s*\n/gm, "\n--- \n\n")
            .replace(/ {2,}/g, " ")
            .trim();
    },
    fixHashLinks: str => {
        const apiName = operations.toDashes(str.replace(/^\s*\>\>\s*(.*?)\s*\<\<.*$/gis, "$1"));

        return str
            .replace(/^\s*\>\>\s*(.*?)\s*\<\</gis, "")
            .replace(/<a\s+href\s*=\s*"#([^"]+?)".*?>(.*?)<\s*\/\s*a>/gi, (a, aHref, aText) => {
                return `<a href="https://api.kronup.com/#tag/${apiName}/${aHref.toLowerCase()}">${aText.trim()}</a>`;
            });
    },
    removeHashLinks: str => str.replace(/<a\s+href\s*=\s*"#.*?>(.*?)<\s*\/\s*a>/gi, "$1"),
    tableCell: str => operations.removeHashLinks(str).replace(/\|/gi, "/").trim(),
    modelHeading: str => (str.match(/^payload/i) ? "Payload setters" : "Model getters"),
    modelArgLabel: str => (str.match(/^payload/i) ? "Argument type" : "Return type"),
    modelPrefix: str => {
        const [model, method] = `${str}`.split(",");

        return model.match(/^payload/i) ? method.replace(/^get/, "set") : method;
    },
    shortLine: str => {
        str = operations.removeHashLinks(str);

        return str.length > 100 ? str.substring(0, 97) + "..." : str;
    },
    toDashes: str => str.replace(/\s/g, "-"),
    stripHash: str => str.replace(/\#.*?$/g, ""),
    splitCamel: str => str.replace(/([A-Z]+)(?=[a-z]|$)/g, " $1").trim(),
    splitPathStripHash: str =>
        str
            .replace(/\#.*?$/g, "")
            .replace(/\//g, " /")
            .trim(),
    host: str => (config.application().production ? str : str.replace(/^.*?\/v(\d+)\b/g, "http://localhost:3000/v$1")),
    defaultDebug: str => ("debug" !== config.application().logLevel ? str : "true"),
    commentLines: str =>
        `${str}`
            .split("\n")
            .map(item => ` * ${item}`)
            .join("\n")
};

/* eslint-disable guard-for-in */
const scribe = class {
    /**
     * Constructor
     *
     * @param {string} srcPath    Source path
     * @param {string} buildPath  Build path
     * @param {object} configData Configuration data
     */
    constructor(srcPath, buildPath, configData) {
        this.srcPath = srcPath;
        this.buildPath = buildPath;
        this.configData = configData;
        this.scribeData = null;
        this.scribeMustacheFile = null;
        this.scribeMustacheFragments = {};

        // Load the configuration
        const scribeConfigPath = path.join(srcPath, "template", "scribe.yml");
        if (fs.existsSync(scribeConfigPath)) {
            this.scribeData = jsYaml.load(fs.readFileSync(scribeConfigPath));
        }

        // Load the file template
        const scribeFilePath = path.join(srcPath, "template", "scribe-file.mustache");
        if (fs.existsSync(scribeFilePath)) {
            this.scribeMustacheFile = fs.readFileSync(scribeFilePath).toString();
        }

        // Load the fragment templates
        const files = fs.readdirSync(path.join(srcPath, "template"));
        files.forEach(fileName => {
            const matches = fileName.match(/^scribe\-fragment\-(.*?)\.mustache$/i);
            if (matches) {
                this.scribeMustacheFragments[matches[1]] = fs
                    .readFileSync(path.join(srcPath, "template", fileName))
                    .toString();
            }
        });
    }

    /**
     * Generate extra documentation for items not present in the OpenAPI specification
     */
    async create() {
        do {
            if (null === this.scribeMustacheFile) {
                break;
            }

            if (null === this.scribeData) {
                break;
            }

            // Add the last key
            const addLast = array => {
                if (Array.isArray(array)) {
                    array.forEach((item, key) => {
                        if ("object" === typeof item && null !== item) {
                            item["-last"] = () => {
                                return array.length - 1 === key;
                            };
                        }
                    });
                }
            };

            // Go through the fragments
            for (const fragmentPath in this.scribeData) {
                if (Array.isArray(this.scribeData[fragmentPath].classes)) {
                    addLast(this.scribeData[fragmentPath].classes);
                    const docPath = path.join(this.buildPath, "docs", fragmentPath);
                    if (!fs.existsSync(docPath)) {
                        fs.mkdirSync(docPath, { recursive: true });
                    }

                    // Prepare class files
                    this.scribeData[fragmentPath].classes.forEach(classData => {
                        if ("string" === typeof classData.className) {
                            const classPath = path.join(docPath, `${classData.className}.md`);
                            let parentCount = 1;
                            if (fragmentPath.match(/\/\w+/g)) {
                                parentCount += fragmentPath.match(/\/\w+/g).length;
                            }
                            const parentRootPath = "../".repeat(parentCount);

                            if (!fs.existsSync(classPath)) {
                                if (Array.isArray(classData.methods)) {
                                    addLast(classData.methods);
                                    classData.methods.forEach(method => {
                                        if (null !== method && Array.isArray(method.methodArgs)) {
                                            addLast(method.methodArgs);
                                        }
                                    });
                                }

                                // Prepare the mustache data
                                const mustacheData = {
                                    parentRootPath,
                                    file: `docs/${fragmentPath}/${classData.className}.md`,
                                    fragment: fragmentPath,
                                    name: this.scribeData[fragmentPath].name,
                                    classes: classData,
                                    config: this.configData,
                                    linkType: () => (val, render) => {
                                        let str = `${render(val)}`;
                                        let parentCount = 2;
                                        if (fragmentPath.match(/\/\w+/g)) {
                                            parentCount += fragmentPath.match(/\/\w+/g).length;
                                        }

                                        const matches = str.match(/\\Model\\(\w+)/i);
                                        if (matches) {
                                            str = `[**${str}**](${"../".repeat(parentCount)}Model/${matches[1]})`;
                                        } else {
                                            str = `\`${str}\``;
                                        }

                                        return str;
                                    }
                                };

                                // Enable known operations
                                for (const opName in operations) {
                                    mustacheData[opName] = () => (val, render) => operations[opName](`${render(val)}`);
                                }

                                fs.writeFileSync(classPath, Mustache.render(this.scribeMustacheFile, mustacheData));
                            }
                        }
                    });
                }
            }
        } while (false);
    }

    /**
     * Parse (()) syntax
     *
     * @param {string} fileContents File contents
     * @param {string} relativeFilePath Relative file path
     * @return {string}
     */
    async parse(fileContents, relativeFilePath) {
        // Replace (()) syntax
        return fileContents
            .replace(/\(\((\w+):([^\(\)]+?)\)\)/g, (match, fragmentName, fragmentPath) => {
                let result = "";

                // Prepare the mustache data
                const mustacheData = {
                    file: relativeFilePath,
                    fragment: fragmentPath,
                    data: "object" === typeof this.scribeData[fragmentPath] ? this.scribeData[fragmentPath] : null,
                    config: this.configData
                };

                // Enable known operations
                for (const opName in operations) {
                    mustacheData[opName] = () => (val, render) => operations[opName](`${render(val)}`);
                }

                // Fragment found
                if ("string" === typeof this.scribeMustacheFragments[fragmentName]) {
                    result = Mustache.render(this.scribeMustacheFragments[fragmentName], mustacheData);
                }

                return result;
            })
            .replace(/\(\(#(\w+)\)\)(.*?)\(\(\/\1\)\)/gis, (match, operation, contents) => {
                let result = "";

                // Operation found
                if ("function" === typeof operations[operation]) {
                    result = operations[operation](contents);
                }

                return result;
            });
    }
};

module.exports = scribe;
