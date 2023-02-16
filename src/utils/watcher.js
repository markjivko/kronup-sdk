/**
 * File watcher utility
 *
 * @desc      File watcher
 * @copyright (c) 2022-2023 kronup.com
 * @author    Mark Jivko
 */
const fs = require("fs-extra");
const path = require("path");
const logger = require("./logger");
const md5File = require("md5-file");
const chokidar = require("chokidar");
const { readdirRelative } = require("./file");

/* eslint-disable prefer-rest-params,guard-for-in */
module.exports = {
    /**
     * Synchronize two directories by performing Create, Update and Delete actions as needed
     *
     * @param {string} fromPath   Source directory
     * @param {string} toPath     Destination directory
     * @param {int}    execTimeMs Execution time in milliseconds
     */
    synchronize: (fromPath, toPath, execTimeMs) => {
        const fromFiles = readdirRelative(fromPath);
        const toFiles = readdirRelative(toPath);
        let changesMade = false;

        // Added/modified files
        for (const filePath in fromFiles) {
            const fromFilePath = path.join(fromPath, filePath);
            const toFilePath = path.join(toPath, filePath);

            // Fetch the file information
            if (!fs.existsSync(fromFilePath) || fs.lstatSync(fromFilePath).isDirectory()) {
                continue;
            }

            // New file
            if ("undefined" === typeof toFiles[filePath]) {
                const toDir = path.join(toPath, path.dirname(filePath));
                if (!fs.existsSync(toDir) || !fs.lstatSync(toDir).isDirectory()) {
                    fs.mkdirSync(toDir, { recursive: true });
                }

                // Copy new file
                fs.copyFileSync(fromFilePath, toFilePath);
                logger.debug(`${logger.tools.colorSuccess("(+) Added")}    ${filePath}`);
                changesMade = true;
            } else {
                // Update file
                if (md5File.sync(fromFilePath) !== md5File.sync(toFilePath)) {
                    fs.copyFileSync(fromFilePath, toFilePath);
                    logger.debug(`${logger.tools.colorInfo("(~) Modified")} ${filePath}`);
                    changesMade = true;
                }
            }
        }

        // Deleted files
        for (const filePath in toFiles) {
            const toFilePath = path.join(toPath, filePath);
            if (!fs.existsSync(toFilePath) || fs.lstatSync(toFilePath).isDirectory()) {
                continue;
            }

            // Deleted file
            if ("undefined" === typeof fromFiles[filePath]) {
                const toDir = path.join(toPath, path.dirname(filePath));

                // Remove extra files
                fs.unlinkSync(path.join(toPath, filePath));
                logger.debug(`${logger.tools.colorError("(x) Deleted")}  ${filePath}`);
                changesMade = true;

                // Empty directory
                if (toDir !== toPath && 0 === fs.readdirSync(toDir).length) {
                    fs.rmSync(toDir);
                    logger.debug(`${logger.tools.colorError("(x) Deleted")}  📂 ${path.dirname(filePath)}`);
                }
            }
        }

        // Prepare the timestamp
        const timestamp = new Date().toLocaleString("en-gb", {
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit"
        });

        // Log the end
        logger.debug(
            `${changesMade ? "^^^" : "⛳ "} ${timestamp}${
                changesMade ? "" : " (no changes)"
            } in ${new Intl.NumberFormat("en-GB").format(execTimeMs / 1000)}s\n`
        );
    },

    /**
     * Listen for file changes
     *
     * @param {string[]} dirs     Directory paths
     * @param {function} callback Callback on change
     */
    watch: (dirs, callback) => {
        let ready = false;

        // Prepare the listener
        ["unlink", "change", "add", "ready"].forEach(eventType => {
            chokidar.watch(dirs).on(
                eventType,
                (p, e) => {
                    do {
                        // Set the ready flag
                        if ("ready" === eventType) {
                            ready = true;
                            if ("function" === typeof callback) {
                                setTimeout(async () => {
                                    await callback(p, e);
                                }, 200);
                            }
                            break;
                        }

                        // Add, change and delete events only when ready
                        if (ready && "function" === typeof callback) {
                            setTimeout(async () => {
                                await callback(p, e);
                            }, 200);
                        }
                    } while (false);
                },
                {
                    ignored: ".git",
                    ignoreInitial: true,
                    usePolling: true,
                    persistent: true,
                    alwaysStat: true,
                    awaitWriteFinish: true,
                    cwd: dirs
                }
            );
        });
    }
};
