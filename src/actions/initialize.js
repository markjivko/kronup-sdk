/**
 * Kronup SDK Initializer
 *
 * @desc      Initialize a new SDK client generator
 * @copyright (c) 2022-2023 kronup.io
 * @author    Mark Jivko
 */
const logger = require("../utils/logger");
const path = require("path");
const fs = require("fs-extra");
const generators = require("../utils/generators");
const { exec } = require("child_process");
const Mustache = require("mustache");
const cliSpinners = require("cli-spinners");
const { loading } = require("cli-loading-animation");
const { exit } = require("process");

(async () => {
    logger.tools.banner();
    logger.tools.heading("Initialize generator");
    const pathRoot = path.dirname(path.dirname(__dirname));

    // Store the generator client value
    const client = await generators.getClient(true);

    const { start, stop } = loading(`Initializing ${client} SDK Generator...`, {
        clearOnEnd: true,
        spinner: cliSpinners.aesthetic
    });
    start();

    try {
        const command = [
            `java -jar ${path.join(pathRoot, "res", "openapi-generator-cli-6.2.1.jar")} author template`,
            ` -g ${client}`,
            ` -o src/generators/${client}/template`
        ].join("");
        logger.silentInfo(command);

        exec(
            command,
            {
                cwd: pathRoot
            },
            (error, stdout, stderr) => {
                // Logging
                stdout && logger.silentDebug(stdout);
                stderr && logger.silentError(stderr);

                if (null === error) {
                    // Configuration
                    fs.writeFileSync(
                        path.join(pathRoot, "src", "generators", client, "config.yml"),
                        `additionalProperties:
  theGitUserId: "kronup"
  theGitRepoId: "kronup-${client}"
  theAuthorName: "kronup.io"
  theAuthorUrl: "https://kronup.io/"
  packagePath: "Kronup"
  artifactVersion: "0.0.1"
  invokerPackage: "Kronup"
  modelPackage: "Model"
  apiPackage: "Api"
  composerVendorName: "Kronup"
  composerProjectName: "SDK"
  licenseInfo: "MIT"
  copyright: "(c) ${new Date().getFullYear()} kronup.io"`
                    );

                    // Hooks
                    fs.writeFileSync(
                        path.join(pathRoot, "src", "generators", client, "hook.js"),
                        Mustache.render(fs.readFileSync(path.join(pathRoot, "res", "hook.mustache")).toString(), {
                            generator: client,
                            year: new Date().getFullYear()
                        })
                    );
                }

                // Stop the animation
                stop();

                if (error) {
                    logger.error(`Could not initialize template for "${client}"\n`);
                    exit(1);
                } else {
                    logger.debug(`Template initialized in "${logger.tools.colorInfo(`src/generators/${client}`)}"`);
                    logger.debug(`Start developing with \`${logger.tools.colorInfo(`npm run dev ${client}`)}\`\n`);
                }
            }
        );
    } catch (e) {
        logger.error(` ${e}`);
        process.exit(1);
    }
})();
