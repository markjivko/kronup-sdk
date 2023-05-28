/**
 * kronup sdk Configuration manager
 *
 * @copyright (c) 2022-2023 kronup.io
 * @author    Mark Jivko
 */
const inquirer = require("inquirer");
const fs = require("fs-extra");
const path = require("path");
const logger = require("../utils/logger");
const config = require("../utils/config");
const md5File = require("md5-file");
const axios = require("axios");
const lint = require("htmllint");

// Configuration path
const configPath = path.join(path.dirname(path.dirname(__dirname)), "config");

/**
 * Treat the OpenAPI specification
 *
 * @param {object} spec OpenAPI specification
 */
const treatSpec = spec => {
    // Nothing to do
};

/* eslint-disable guard-for-in */
const tools = {
    /**
     * Prepare inquirer choices of configuration files
     *
     * @return {array} Array of {value, name} objects for inquirer
     */
    getChoices: () => {
        const mainFilePath = path.join(configPath, "openapi.json");
        const mainFileMd5 = fs.existsSync(mainFilePath) ? md5File.sync(mainFilePath) : null;

        return fs
            .readdirSync(configPath)
            .filter(fileName => {
                return !!fileName.match(/^openapi\-[\w\-]+\.json/g);
            })
            .map(fileName => {
                const md5 = md5File.sync(path.join(configPath, fileName));
                const name = fileName.replace(/^openapi\-|\.json$/gi, "");

                return {
                    value: fileName,
                    name: `${md5 === mainFileMd5 ? "✔" : " "} ${name}`,
                    short: `${name} (${fileName})`,
                    id: name
                };
            });
    },

    /**
     * Prepare a new OpenApi configuration file name
     *
     * @param {string} name (optional) Configuration name
     * @return {string}
     */
    getConfigName: async name => {
        const validate = answer => {
            let result = true;

            if (!answer.match(/^(?:\w+|\w[\w\-]+\w)$/gi)) {
                result = "Configuration name must use only letters, numbers and dashes";
            }

            return result;
        };
        const filter = answer => {
            return answer.replace(/\-{2,}/gi, "-").toLowerCase();
        };

        let configName = "";
        do {
            if ("string" === typeof name && true === validate(name)) {
                configName = filter(name);
                break;
            }

            const dialog = await inquirer.prompt([
                {
                    type: "input",
                    name: "configName",
                    message: "New configuration name:",
                    validate,
                    filter
                }
            ]);
            configName = dialog.configName;
        } while (false);

        return `openapi-${configName}.json`;
    },

    /**
     * Create a new file from existing configuration files
     *
     * @param {array} choices Array of {value, name} objects for inquirer
     * @param {string} sourceName (optional) Source Configuration name
     * @param {string} name       (optional) Destination Configuration name
     */
    handleNew: async (choices, sourceName, name) => {
        const choiceObject = choices.reduce((prev, current) => {
            return null !== prev ? prev : current.id === sourceName ? current : null;
        }, null);

        // Source file name
        let sourceFileName = null;
        if (null !== choiceObject) {
            logger.debug(`> Copy from: ${logger.tools.colorInfo(choiceObject.name)}`);
            sourceFileName = choiceObject.value;
        } else {
            const dialog =
                choices.length == 1
                    ? { sourceFileName: choices[0].value }
                    : await inquirer.prompt([
                          {
                              type: "list",
                              name: "sourceFileName",
                              message: "Copy from",
                              choices: choices
                          }
                      ]);
            sourceFileName = dialog.sourceFileName;
        }
        // Prepare the destination file name
        const destFileName = await tools.getConfigName(name);

        // Do a file copy
        fs.copyFileSync(path.join(configPath, sourceFileName), path.join(configPath, destFileName));
        logger.success(`✨ Copied "${sourceFileName}" to "${destFileName}"\n`);
    },

    /**
     * Development mode: continuous fetching
     */
    handleDev: async () => {
        console.log("");
        logger.info("📦 Fetching OpenAPI specification at a regular interval\n");

        // State machine
        const go = async () => {
            const start = new Date().getTime();
            try {
                const changesMade = await tools.handleFetch("sample", true);

                if (changesMade) {
                    // Prepare the timestamp
                    const timestamp = new Date().toLocaleString("en-gb", {
                        hour: "2-digit",
                        minute: "2-digit",
                        second: "2-digit"
                    });

                    // Log the end
                    logger.debug(
                        `^^^ ${timestamp} in ${new Intl.NumberFormat("en-GB").format(
                            (new Date().getTime() - start) / 1000
                        )}s\n`
                    );
                }

                setTimeout(go, 2000);
            } catch (e) {
                setTimeout(go, 1000);
            }
        };

        // Start the state machine
        go();
    },

    /**
     * Perform check on configuration files
     *
     * @param {array}  choices Array of {value, name} objects for inquirer
     * @param {string} name    (optional) Configuration name
     */
    handleCheck: async (choices, name) => {
        const choiceObject = choices.reduce((prev, current) => {
            return null !== prev ? prev : current.id === name ? current : null;
        }, null);

        // Prepare the filename
        let fileName = null;
        if (null !== choiceObject) {
            logger.debug(`> Check: ${logger.tools.colorInfo(choiceObject.name)}`);
            fileName = choiceObject.value;
        } else {
            const dialog = await inquirer.prompt([
                {
                    type: "list",
                    name: "fileName",
                    message: "Check",
                    choices: choices
                }
            ]);
            fileName = dialog.fileName;
        }

        // Log issue
        const logIssue = (path, verb, error) => {
            console.log(`\`${path}\` (${verb})\n${error}\n`);
        };

        // Get the OpenAPI data
        const openApi = JSON.parse(fs.readFileSync(path.join(configPath, fileName)).toString());
        for (const path in openApi.paths) {
            for (const verb in openApi.paths[path]) {
                if ("string" === typeof openApi.paths[path][verb].description) {
                    try {
                        const result = await lint(openApi.paths[path][verb].description, {
                            "tag-bans": ["style", "script"],
                            "line-no-trailing-whitespace": false,
                            "link-req-noopener": false,
                            "line-end-style": false,
                            "indent-style": false,
                            "attr-quote-style": false,
                            "tag-close": true,
                            "tag-name-match": true,
                            "spec-char-escape": true
                        });
                        const lines = openApi.paths[path][verb].description.split("\n");

                        if (result.length) {
                            result.forEach(issue => {
                                if (-1 === ["indent-style"].indexOf(issue.rule)) {
                                    const lineHint = `> ${lines[issue.line - 1]
                                        .slice(issue.clumn - 1)
                                        .slice(0, 70)}\n> ^^^`;
                                    logIssue(
                                        path,
                                        verb,
                                        `${issue.rule} (column ${issue.column}): ${
                                            Object.keys(issue.data).length ? JSON.stringify(issue.data) : ""
                                        }\n${lineHint}`
                                    );
                                }
                            });
                        }
                    } catch (e) {
                        logIssue(path, verb, "> Invalid HTML syntax");
                    }
                }
            }
        }
    },

    /**
     * Fetch OpenApi specification from server
     *
     * @param {string} name (optional) Configuration name
     * @param {boolean} devMode (optional) openapi.json should store the development version
     * @return {boolean}
     */
    handleFetch: async (name, devMode = false) => {
        let changesMade = false;

        // Prepare the paths
        const configName = await tools.getConfigName(name);
        const pathConfigMain = path.join(configPath, "openapi.json");
        const pathConfigFetched = path.join(configPath, configName);

        // Fetch the html
        const openApiObject = await axios.get(`http://localhost:3000/openapi.json`);

        // Invalid response
        if ("object" !== typeof openApiObject || null === openApiObject) {
            throw new Error("Invalid server response");
        }

        // Invalid payload
        if ("string" !== typeof openApiObject.data.openapi) {
            throw new Error("Unknown specification format");
        }

        // Fix specification issues
        treatSpec(openApiObject.data);

        // Development mode
        let openApiDevObject = null;
        if (devMode) {
            openApiDevObject = await axios.get(`http://localhost:3000/openapi-dev.json`);

            // Invalid response
            if ("object" !== typeof openApiDevObject || null === openApiDevObject) {
                throw new Error("Invalid server response (development version)");
            }

            // Invalid payload
            if ("string" !== typeof openApiDevObject.data.openapi) {
                throw new Error("Unknown specification format (development version)");
            }

            // Fix specification issues
            treatSpec(openApiDevObject.data);
        }

        // Prepare the data
        const jsonMain = fs.existsSync(pathConfigMain) ? fs.readFileSync(pathConfigMain).toString() : "";
        const jsonFetchedOld = fs.existsSync(pathConfigFetched) ? fs.readFileSync(pathConfigFetched).toString() : "";
        const jsonFetchedNew = JSON.stringify(openApiObject.data, null, 4);
        const jsonMainNew = devMode ? JSON.stringify(openApiDevObject.data, null, 4) : jsonFetchedNew;

        // Update the fetched configuration file
        if (jsonFetchedOld !== jsonFetchedNew) {
            fs.writeFileSync(pathConfigFetched, jsonFetchedNew);
            logger.success(`✨ Updated "${configName}"`);
            changesMade = true;
        }

        // Auto-switch
        if (jsonMain !== jsonMainNew) {
            fs.writeFileSync(pathConfigMain, jsonMainNew);
            logger.success(`✨ Updated "openapi.json"`);
            changesMade = true;
        }

        return changesMade;
    },

    /**
     * Switch to a configuration file (copy contents over "openapi.json")
     *
     * @param {array}  choices Array of {value, name} objects for inquirer
     * @param {string} name    (optional) Configuration name
     */
    handleSwitchTo: async (choices, name) => {
        const choiceObject = choices.reduce((prev, current) => {
            return null !== prev ? prev : current.id === name ? current : null;
        }, null);

        // Prepare the filename
        let fileName = null;
        if (null !== choiceObject) {
            logger.debug(`> Switch to: ${logger.tools.colorInfo(choiceObject.name)}`);
            fileName = choiceObject.value;
        } else {
            const dialog = await inquirer.prompt([
                {
                    type: "list",
                    name: "fileName",
                    message: "Switch to",
                    choices: choices
                }
            ]);
            fileName = dialog.fileName;
        }

        fs.copyFileSync(path.join(configPath, fileName), path.join(configPath, "openapi.json"));
        logger.success(`✨ Copied "${fileName}" to "openapi.json"\n`);
    },

    /**
     * Delete a configuration file
     *
     * @param {array} choices Array of {value, name} objects for inquirer
     * @param {string} name    (optional) Configuration name
     */
    handleDelete: async (choices, name) => {
        const choiceObject = choices.reduce((prev, current) => {
            return null !== prev ? prev : current.id === name ? current : null;
        }, null);

        // Prepare the filename
        let fileNameToDelete = null;
        if (null !== choiceObject) {
            logger.debug(`> Delete ${logger.tools.colorInfo(choiceObject.name)}`);
            fileNameToDelete = choiceObject.value;
        } else {
            const dialog = await inquirer.prompt([
                {
                    type: "list",
                    name: "fileNameToDelete",
                    message: "Delete",
                    choices: choices
                }
            ]);
            fileNameToDelete = dialog.fileNameToDelete;
        }

        if ("string" === typeof fileNameToDelete) {
            fs.unlinkSync(path.join(configPath, fileNameToDelete));
            logger.success(`✖ Deleted "${fileNameToDelete}"\n`);
        }
    }
};

(async () => {
    logger.tools.banner();
    logger.tools.heading("Configuration manager");

    // Initialize the openapi.json file
    config.openApi();

    try {
        // Prepare the choices
        const choicesSwitch = tools.getChoices();
        const choicesDelete = choicesSwitch.filter(item => "openapi-sample.json" !== item.value);

        // Prepare available actions
        const actionChoices = [
            { value: "check", name: "Check configuration" },
            { value: "fetch", name: "Fetch from server" },
            { value: "copy", name: "Copy from file" },
            { value: "dev", name: "Development mode" }
        ];

        // Files to switch to
        if (choicesSwitch.length > 1) {
            actionChoices.push({ value: "switch", name: "Switch to configuration" });
        }

        // Files to delete
        if (choicesDelete.length) {
            actionChoices.push({ value: "delete", name: "Delete configuration" });
        }

        // Prepare the action
        let action = null;
        const argAction = process.argv.slice(2)[0];
        const argActionObject =
            "string" == typeof argAction
                ? actionChoices.reduce((prev, current) => {
                      return null !== prev ? prev : current.value === argAction ? current : null;
                  }, null)
                : null;

        if (null !== argActionObject) {
            logger.debug(`> OpenAPI specification action: ${logger.tools.colorInfo(argActionObject.name)}`);
            action = argActionObject.value;
        } else {
            const dialogAction = await inquirer.prompt([
                {
                    type: "list",
                    name: "action",
                    message: "OpenAPI specification action:",
                    choices: actionChoices
                }
            ]);
            action = dialogAction.action;
        }

        // Prepare the final argument
        const argExtra = process.argv.slice(3)[0];
        switch (action) {
            case "copy":
                await tools.handleNew(choicesSwitch, argExtra, process.argv.slice(4)[0]);
                break;

            case "dev":
                await tools.handleDev();
                break;

            case "check":
                await tools.handleCheck(choicesSwitch, argExtra);
                break;

            case "fetch":
                logger.debug("📥 Fetching OpenAPI Specification...");
                await tools.handleFetch(argExtra);
                console.log("\n");
                break;

            case "switch":
                await tools.handleSwitchTo(choicesSwitch, argExtra);
                break;

            case "delete":
                await tools.handleDelete(choicesDelete, argExtra);
                break;
        }
    } catch (e) {
        logger.error(` ${e}`);
        process.exit(1);
    }
})();
