/**
 * Languages handler
 *
 * @copyright (c) 2022-2023 kronup.io
 * @author    Mark Jivko
 */
const path = require("path");
const os = require("os");
const fs = require("fs-extra");
const jsYaml = require("js-yaml");
const scribe = require("../utils/scribe");
const morpher = require("../utils/morpher");
const logger = require("../utils/logger");
const watcher = require("../utils/watcher");
const config = require("../utils/config");
const cliSpinners = require("cli-spinners");
const { loading } = require("cli-loading-animation");
const { spawn } = require("child_process");
const { readdirRelative } = require("./file");
const inquirer = require("inquirer");

// List of supported languages
// View all supported CLIENT generators with `java -jar res/openapi-generator-cli-{version}.jar list`
const shortlist = [
    "android",
    "csharp",
    "csharp-netcore",
    "cpp-tizen",
    "dart",
    "elm",
    "go",
    "java",
    "javascript",
    "kotlin",
    "php",
    "python",
    "ruby",
    "rust",
    "swift5",
    "typescript-node",
    "typescript-axios"
];

/* eslint-disable guard-for-in */
const gHook = class {
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
        this.scribe = new scribe(this.srcPath, this.buildPath, this.configData);
    }

    /**
     * Perform changes before build
     */
    async preBuild() {
        // Parse the OpenAPI object
        morpher.parse(this.openApi);
    }

    /**
     * Perform changes after build
     */
    async postBuild() {
        // Append documentation for items not present in the OpenAPI specification
        await this.scribe.create();

        // Parse the documents
        for (const relativePath in readdirRelative(path.join(this.buildPath, "docs"))) {
            const docFilePath = path.join(this.buildPath, "docs", relativePath);
            const contentsOriginal = fs.readFileSync(docFilePath).toString();

            let contentsNew = contentsOriginal
                // Fix double-encodig of double-quotes
                .replace(/\\"/g, '"');

            // Parse scribe tags in all documents
            contentsNew = await this.scribe.parse(contentsNew, relativePath);

            // Save the file
            if (contentsNew !== contentsOriginal) {
                fs.writeFileSync(docFilePath, contentsNew);
            }
        }

        // Parse generated files
        for (const relativePath in readdirRelative(path.join(this.buildPath, "lib"))) {
            const filePath = path.join(this.buildPath, "lib", relativePath);
            const contentsOriginal = fs.readFileSync(filePath).toString();

            // Parse scribe tags in all documents
            const contentsNew = await this.scribe.parse(contentsOriginal, relativePath);

            // Save the file
            if (contentsNew !== contentsOriginal) {
                fs.writeFileSync(filePath, contentsNew);
            }
        }

        // Parse the readme
        const readmeFilePath = path.join(this.buildPath, "README.md");
        if (fs.existsSync(readmeFilePath)) {
            const readmeOriginal = fs.readFileSync(readmeFilePath).toString();

            // Parse scribe tags
            const readmeNew = await this.scribe.parse(readmeOriginal, "readme");
            if (readmeNew !== readmeOriginal) {
                fs.writeFileSync(readmeFilePath, readmeNew);
            }
        }

        // Remove extra files
        [".openapi-generator", ".openapi-generator-ignore", "git_push.sh"].forEach(item => {
            const itemPath = path.join(this.buildPath, item);
            if (fs.existsSync(itemPath)) {
                if (fs.lstatSync(itemPath).isDirectory()) {
                    fs.rmSync(itemPath, { recursive: true, force: true });
                } else {
                    fs.unlinkSync(itemPath);
                }
            }
        });
    }
};

const clientGenerators = {
    /**
     * Get the list of implemented client generators
     *
     * @return {string[]}
     */
    getImplemented: () => {
        const generatorsPath = path.join(path.dirname(__dirname), "generators");

        // Get the defined SDK templates
        return fs
            .readdirSync(generatorsPath, { withFileTypes: true })
            .filter(entry => {
                let result = false;

                do {
                    // Not a directory
                    if (!entry.isDirectory()) {
                        break;
                    }

                    // Template directory not found
                    const templatePath = path.join(generatorsPath, entry.name, "template");
                    if (!fs.existsSync(templatePath) || !fs.lstatSync(templatePath).isDirectory()) {
                        break;
                    }

                    // Configuration file not found
                    const configPath = path.join(generatorsPath, entry.name, "config.yml");
                    if (!fs.existsSync(configPath) || !fs.lstatSync(configPath).isFile()) {
                        break;
                    }

                    result = true;
                } while (false);

                return result;
            })
            .map(entry => entry.name);
    },

    /**
     * Get the list of available client generators
     *
     * @return {string[]}
     */
    getAvailable: () => {
        const implemented = clientGenerators.getImplemented();

        return shortlist.filter(item => -1 === implemented.indexOf(item));
    },

    /**
     * Get the SDK client generator currently under development
     * Uses inquirer to ask for the option if not provided as an argument
     *
     * @param {boolean} init  Whether to choose a client to initialize or from already implemented clients
     * @param {string}  extra (optional) Prepend a client to the list
     * @param {string}  label (optional) Label to use instead of "SDK Client"
     * @return {string}
     */
    getClient: async (init, extra, label) => {
        // Prepare the allowed list
        const allowed = init ? clientGenerators.getAvailable() : clientGenerators.getImplemented();

        // Prepend the extra client
        if ("string" === typeof extra && extra.length) {
            allowed.unshift(extra);
        }

        // Prepare the prompt label
        let promptLabel = "SDK Client";
        if ("string" === typeof label && label.length) {
            promptLabel = label;
        }

        // Fetch the SDK client from command line
        let client = process.argv.slice(2)[0];

        // Invalid input
        if ("string" !== typeof client || -1 === allowed.indexOf(client)) {
            const answer = await inquirer.prompt([
                {
                    type: "list",
                    name: "generator",
                    message: `${promptLabel}:`,
                    choices: allowed.map(generator => {
                        return { value: generator, name: generator };
                    })
                }
            ]);
            console.log("");
            client = answer.generator;
        } else {
            console.log(`> ${promptLabel}: \x1b[34m${client}\x1b[0m\n`);
        }

        return client;
    },

    /**
     * Build task
     *
     * Files are written to a temporary directory first,
     * then synchronized with the output to prevent IDE issues
     *
     * @param {object} param0 Options
     * @return {Promise}
     */
    build: async ({ client, animation = false, logging = false }) => {
        try {
            // Prepare the start time in milliseconds
            const startTime = new Date().getTime();

            // Prepare the paths
            const pathRoot = path.dirname(path.dirname(__dirname));
            const srcDir = path.join(pathRoot, "src", "generators", client);
            const outDir = path.join(pathRoot, "out", client);
            const tempDir = path.join(os.tmpdir(), "kronup-sdk", client);

            // Prepare the OpenAPI data
            const openApiData = config.openApi();
            const openApiPath = path.join(os.tmpdir(), "kronup-sdk", `openapi-${client}.json`);

            // Prepare the hooks
            const globalHook = new gHook(openApiData, srcDir, tempDir);
            let clientHook = null;

            // Hook file was defined
            if (fs.existsSync(path.join(srcDir, "hook.js"))) {
                // Prepare the module path
                const hookModule = `../generators/${client}/hook`;

                // Cache clean
                delete require.cache[require.resolve(hookModule)];

                // (re-)require the module
                clientHook = new (require(hookModule))(openApiData, srcDir, tempDir);
            }

            // Pre-build hook
            if (null !== clientHook && "function" === typeof clientHook.preBuild) {
                await globalHook.preBuild();
                await clientHook.preBuild();
            }

            // Create the parent dir and save the OpenAPI file
            if (!fs.existsSync(path.dirname(openApiPath))) {
                fs.mkdirSync(path.dirname(openApiPath), { recursive: true });
            }
            fs.writeFileSync(openApiPath, JSON.stringify(openApiData));

            // Prepare the animation
            const { start, stop } = loading("Generating SDK...", {
                clearOnEnd: true,
                spinner: cliSpinners.aesthetic
            });
            animation && start();

            // Prepare the result
            return new Promise((resolve, reject) => {
                // Clean the output
                if (fs.existsSync(tempDir)) {
                    fs.rmSync(tempDir, { recursive: true, force: true });
                }

                // Prepare the schema mappings array
                const schemaMappingsArr = [];
                for (const schemaName in config.application().schemaMappings) {
                    schemaMappingsArr.push(`${schemaName}=${config.application().schemaMappings[schemaName]}`);
                }

                // Prepare the command options
                const options = [
                    "-jar",
                    path.join(pathRoot, "res", "openapi-generator-cli-6.2.1.jar"),
                    "generate",
                    "-i",
                    openApiPath,
                    "-g",
                    client,
                    "-t",
                    `src/generators/${client}/template`,
                    "-c",
                    `src/generators/${client}/config.yml`,
                    "-o",
                    tempDir,
                    "--skip-validate-spec",
                    "--inline-schema-name-mappings",
                    schemaMappingsArr.join(","),
                    "--global-property",
                    "apis,models,supportingFiles,apiTests=false,modelTests=false"
                ];
                if (logging) {
                    logger.silentInfo(`$ java ${options.join(" ")}`);
                }

                // Prepare the child process
                const child = spawn("java", options, {
                    cwd: pathRoot,
                    encoding: "utf-8",
                    stdio: ["ignore", "pipe", "pipe"]
                });

                // Log the output
                let errorsThrown = false;
                if (logging) {
                    child.stdout.on("data", function (data) {
                        logger.silentDebug(data.toString());
                    });
                    child.stderr.on("data", function (data) {
                        logger.error(data.toString());
                        errorsThrown = true;
                    });
                }

                // Task done
                child.on("close", async code => {
                    animation && stop();

                    // Errors thrown
                    if (errorsThrown) {
                        reject(new Error("Build failed"));
                    } else {
                        // Post-build hook
                        if (null !== clientHook && "function" === typeof clientHook.postBuild) {
                            await globalHook.postBuild();
                            await clientHook.postBuild();
                        }

                        // Synchronize locally
                        watcher.synchronize(tempDir, outDir, new Date().getTime() - startTime);

                        // Debugging
                        if (!animation && "debug" === config.application().logLevel) {
                            // Show the builder output
                            console.log(fs.readFileSync(path.join(pathRoot, "out", "activity.log")).toString());
                        }

                        // Done
                        resolve(outDir, new Date().getTime() - startTime);
                    }
                });

                // Error occured
                child.on("error", err => {
                    reject(err);
                });
            });
        } catch (e) {
            logger.error(` ${e}`);
            process.exit(1);
        }
    }
};

module.exports = clientGenerators;
