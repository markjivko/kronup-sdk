/**
 * Kronup SDK Tester
 *
 * @desc      Run unit tests
 * @copyright (c) 2022-2023 kronup.io
 * @author    Mark Jivko
 */
const logger = require("../utils/logger");
const config = require("../utils/config");
const os = require("os");
const fs = require("fs-extra");
const path = require("path");
const generators = require("../utils/generators");
const { spawn, exec } = require("child_process");

// eslint-disable-next-line no-unused-vars
const iHook = require("../lib/iHook");

// Binaries check
const checkBinaries = {
    php: {
        getVersion: "php -r 'echo PHP_VERSION;'",
        help: {
            linux: "Install PHP with `sudo apt-get install php`",
            win32: "Install PHP from https://www.wampserver.com/en/",
            darwin: "Install Homebrew from https://brew.sh/ then install PHP with `brew install php`"
        }
    }
};

(async () => {
    logger.tools.banner();
    logger.tools.heading("Unit testing");

    // Get the client
    const client = await generators.getClient(false, "local", "Unit test");
    const pathRoot = path.dirname(path.dirname(__dirname));

    do {
        if ("local" === client) {
            spawn("npx", ["jest", "--passWithNoTests"], {
                cwd: pathRoot,
                stdio: ["ignore", process.stdout, process.stderr]
            });
            break;
        }

        // Prepare the paths
        const pathSrc = path.join(pathRoot, "src", "generators", client);
        const pathOut = path.join(pathRoot, "out", client);
        const pathOutDev = path.join(pathRoot, "out", `${client}-dev`);
        const tempDir = path.join(os.tmpdir(), "kronup-sdk", client);

        // Create dev directory
        if (!fs.existsSync(pathOutDev)) {
            logger.info(`Created directory "out/${client}-dev"`);
            fs.mkdirSync(pathOutDev, { recursive: true });
        }

        // Test directory missing
        if (!fs.existsSync(pathOut)) {
            logger.error(`${client} SDK build missing\n`);
            logger.debug(
                `Please rebuild with one of these commands:\n $ ${logger.tools.colorInfo(
                    `npm run dev ${client}`
                )}\n $ ${logger.tools.colorInfo(`npm run build ${client}`)}\n`
            );
            break;
        }

        // Check environment
        if (
            "object" === typeof checkBinaries[client] &&
            null !== checkBinaries[client] &&
            "string" === typeof checkBinaries[client].getVersion
        ) {
            try {
                const version = await new Promise(function (resolve, reject) {
                    exec(checkBinaries[client].getVersion, (error, stdout, stderr) => {
                        if (error) {
                            reject(error);

                            return;
                        }

                        resolve(stdout.trim());
                    });
                });
                logger.info(`Running unit tests for ${client} v.${version}\n`);
            } catch (e) {
                let help = `Please install ${client} on your machine`;

                if ("object" === typeof checkBinaries[client].help) {
                    ["linux", os.platform()].forEach(osType => {
                        if ("string" === typeof checkBinaries[client].help[osType]) {
                            help = checkBinaries[client].help[osType];
                        }
                    });
                }

                logger.error(`${client} binaries missing\n`);
                logger.debug(`${help}\n`);
                break;
            }
        }

        try {
            /** @type {iHook}*/
            const hook = new (require(path.join(pathSrc, "hook.js")))(config.openApi(), pathSrc, tempDir);

            // Run the unit tests
            await hook.test(pathOut, pathOutDev);
        } catch (e) {
            logger.error(` ${e}`);
            process.exit(1);
        }
    } while (false);
})();
