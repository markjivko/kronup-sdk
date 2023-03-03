/**
 * Hook functionality
 *
 * @copyright (c) 2022-2023 kronup.com
 * @author    Mark Jivko
 */
const fs = require("fs-extra");
const path = require("path");
const jsYaml = require("js-yaml");
const { readdirRelative } = require("../utils/file");
const mustache = require("mustache");
const config = require("../utils/config");
const logger = require("../utils/logger");

/* eslint-disable guard-for-in */
module.exports = class iHook {
    /**
     * Constructor
     *
     * @param {object} openApi   OpenAPI JSON Object
     * @param {string} srcPath   Source path
     * @param {string} buildPath Build path
     */
    constructor(openApi, srcPath, buildPath) {
        this.openApi = openApi;
        this.srcPath = srcPath;
        this.buildPath = buildPath;
        this.configData = jsYaml.load(fs.readFileSync(path.join(srcPath, "config.yml")));

        // Prepare application configuraiton object
        this.application = JSON.parse(JSON.stringify(config.application()));

        // Convert schema mappings to mustache format
        const schemaMappingsArr = [];
        for (const schemaName in this.application.schemaMappings) {
            schemaMappingsArr.push({
                key: schemaName,
                value: this.application.schemaMappings[schemaName]
            });
        }
        this.application.schemaMappings = schemaMappingsArr;
    }

    /**
     * Perform changes before build
     */
    async preBuild() {
        // @example Make changes to this.openApi object
    }

    /**
     * Perform changes after build
     */
    async postBuild() {
        // @example Append extra files to the output
        // @example this.mustacheFolder('extra');
    }

    /**
     * Run unit tests
     *
     * @param {string} pathOut    Path to output folder
     * @param {string} pathOutDev Path to output development folder
     */
    async test(pathOut, pathOutDev) {
        // @example Copy files from `${this.srcPath}/dev` to pathOutDev
        // @example Run PHPUnit (for PHP), jUnit (for Java), jest (for JS) etc.
    }

    /**
     * Move a folder from source to output recursively
     * Apply mustache transforms to all ".mustache" files, ignoring other extension
     * Remove the ".mustache" extension
     *
     * @param {string} folderName Source folder name
     */
    async mustacheFolder(folderName) {
        const destPath = path.join(this.buildPath, folderName);
        const sourceDir = path.join(this.srcPath, "template", folderName);
        const sourceFiles = readdirRelative(sourceDir);

        // Go throught the input
        for (const relativePath in sourceFiles) {
            // Store the source path
            const sourceFilePath = path.join(sourceDir, relativePath);

            // Store the destination path
            const destFilePath = path.join(destPath, relativePath.replace(/\.mustache$/gi, ""));

            // Create the parent directory
            if (!fs.existsSync(path.dirname(destFilePath))) {
                fs.mkdirSync(path.dirname(destFilePath), { recursive: true });
            }

            if (relativePath.match(/\.mustache$/gi)) {
                // Write the file
                fs.writeFileSync(
                    destFilePath,
                    mustache.render(fs.readFileSync(sourceFilePath).toString(), {
                        openApi: this.openApi,
                        srcPath: this.srcPath,
                        buildPath: this.buildPath,
                        configData: this.configData,
                        application: this.application
                    })
                );
            } else {
                fs.copyFileSync(sourceFilePath, destFilePath);
            }
        }
    }

    /**
     * Move examples
     *
     * @param {string}   folderName    (optional) Destination folder name; default <b>examples</b>
     * @param {function} filter        (optional) Callback filter to execute changes to file contents before saving
     * @param {function} commandPrefix (optional) Command prefix - this is used to execute the generated file
     */
    async moveExamples(folderName, filter, commandPrefix) {
        if ("string" !== typeof folderName || !folderName.length) {
            folderName = "examples";
        }

        // Parse the documents
        for (const relativePath in readdirRelative(path.join(this.buildPath, "docs"))) {
            const docFilePath = path.join(this.buildPath, "docs", relativePath);
            const contentsOriginal = fs.readFileSync(docFilePath).toString();

            // Replace examples with links
            const contentsNew = contentsOriginal.replace(
                /<!-{3}\s*kronup-example\s*file\s*=\s*['"](.*?)['"]\s*-{3}>(.*?)<!-{3}\s*\/kronup-example\s*-{3}>/gims,
                (exAll, exRelativePath, exContents) => {
                    // Prepare the example file path
                    const examplePath = path.join(this.buildPath, folderName, exRelativePath);

                    // Remove triple-tick syntax
                    let exampleContents = exContents
                        .trim()
                        .replace(/^`{3}\w+\s*|`{3}$/gims, "")
                        .trim();

                    // Further filtering
                    if ("function" === typeof filter) {
                        exampleContents = filter(
                            exampleContents,
                            `${folderName}/${exRelativePath}`,
                            `docs/${relativePath}`
                        );
                    }

                    // Valid filter function
                    if ("string" === typeof exampleContents) {
                        // Prepare the folder
                        if (!fs.existsSync(path.dirname(examplePath))) {
                            fs.mkdirSync(path.dirname(examplePath), { recursive: true });
                        }

                        // Save the example
                        fs.writeFileSync(examplePath, exampleContents);

                        // Prepare the URL
                        const gitUserId = this.configData.additionalProperties.theGitUserId;
                        const gitRepoId = this.configData.additionalProperties.theGitRepoId;
                        const url = `https://github.com/${gitUserId}/${gitRepoId}/blob/main/${folderName}/${exRelativePath}`;

                        // Replace example block with link
                        exAll = `{: .new-title }\n> #️⃣ Execute command in terminal \n> \n> [${
                            "string" === typeof commandPrefix ? commandPrefix + " " : ""
                        }**${path.basename(exRelativePath)}**](${url}){: .btn .btn-green .mt-4}`;
                    }

                    return exAll;
                }
            );

            // Save the file
            if (contentsOriginal !== contentsNew) {
                fs.writeFileSync(docFilePath, contentsNew);
            }
        }
    }

    /**
     * Get environment variables.
     * Initialize the Kronup API from configuration if not present
     *
     * @return {object}
     */
    getEnv() {
        const result = { ...process.env };

        if ("string" !== typeof result.KRONUP_API_KEY) {
            result.KRONUP_API_KEY = config.application().apiKey;
        }

        logger.silentInfo("Environment variables", {
            KRONUP_API_KEY: result.KRONUP_API_KEY
        });

        return result;
    }
};
