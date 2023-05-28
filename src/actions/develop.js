/**
 * kronup sdk Developer
 *
 * @desc      Develop SDK client in real-time
 * @copyright (c) 2022-2023 kronup.io
 * @author    Mark Jivko
 */
const config = require("../utils/config");
const logger = require("../utils/logger");
const path = require("path");
const generators = require("../utils/generators");
const watcher = require("../utils/watcher");
const fs = require("fs-extra");

(async () => {
    logger.tools.banner();
    logger.tools.heading("Development mode");

    // Get the client
    const client = await generators.getClient();

    // Prepare the directories
    const pathRoot = path.dirname(path.dirname(__dirname));
    const srcDir = path.join(pathRoot, "src", "generators", client);
    const configDir = path.join(pathRoot, "config");

    try {
        config.openApi();

        // Prepare the task running flag
        let taskRunning = false;

        // Log the folders
        logger.debug(
            `Listening to "${logger.tools.colorInfo(
                `src/generators/${client}`
            )}" for changes, re-building to "${logger.tools.colorInfo(`out/${client}`)}"...\n`
        );

        // Watch for changes in the source
        watcher.watch([srcDir, configDir], async () => {
            do {
                if (!fs.existsSync(srcDir)) {
                    logger.error("Source directory removed");
                    process.exit();
                    break;
                }

                // Another task is running
                if (taskRunning) {
                    break;
                }

                // Set the flag
                taskRunning = true;

                // Reload the config
                config.application(true);

                // Build the SDK
                await generators.build({ client, logging: true, animation: true });

                // All done
                taskRunning = false;
            } while (false);
        });
    } catch (e) {
        logger.error(` ${e}`);
        process.exit(1);
    }
})();
