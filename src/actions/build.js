/**
 * Kronup SDK Builder
 *
 * @desc      Build SDK client
 * @copyright (c) 2022-2023 kronup.com
 * @author    Mark Jivko
 */
const inquirer = require("inquirer");
const logger = require("../utils/logger");
const config = require("../utils/config");
const generators = require("../utils/generators");

// Tools
const tools = {
    /**
     * Get the SDK client generator currently under development
     *
     * @return {string}
     */
    getGenerator: async () => {
        const implemented = generators.getImplemented();

        // Fetch the SDK client from command line
        let client = process.argv.slice(2)[0];

        // Invalid input
        if ("string" !== typeof client || -1 === implemented.indexOf(client)) {
            const answer = await inquirer.prompt([
                {
                    type: "list",
                    name: "generator",
                    message: "SDK Client:",
                    choices: implemented.map(generator => {
                        return { value: generator, name: generator };
                    })
                }
            ]);
            console.log("");
            client = answer.generator;
        } else {
            console.log(`> SDK Client: \x1b[34m${client}\x1b[0m\n`);
        }

        return client;
    }
};

(async () => {
    logger.tools.heading("Generate SDK");

    // Get the client
    const client = await tools.getGenerator();

    try {
        config.openApi();

        // Build the SDK
        await generators.build({ client, logging: true });
    } catch (e) {
        logger.error(` ${e}`);
        process.exit(1);
    }
})();
