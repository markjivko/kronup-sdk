/**
 * Logger
 *
 * @desc      Common logging utility
 * @copyright (c) 2022-2023 kronup.io
 * @author    Mark Jivko
 */
const path = require("path");
const fs = require("fs-extra");
const config = require("./config");
const { version } = require("../../package.json");

const tools = {
    logLevels: {
        debug: [1, "DEBUG"],
        info: [2, "INFO "],
        warn: [3, "WARN "],
        error: [4, "ERROR"]
    },
    logStream: null,
    log: (data, logLevelData) => {
        // Minimum log level as defined in the application configuration
        const minLogLevel =
            "object" === typeof tools.logLevels[config.application().logLevel]
                ? tools.logLevels[config.application().logLevel][0]
                : 100;

        // Current log level
        const currentLogLevel =
            Array.isArray(logLevelData) && "number" === typeof logLevelData[0] ? logLevelData[0] : 1;

        // Allowed to log event to file
        if (currentLogLevel >= minLogLevel) {
            // Prepare the row data
            const rowData = [
                Array.isArray(logLevelData) && "string" === typeof logLevelData[1] ? logLevelData[1] : "DEBUG",
                new Date().toLocaleString("en-gb", {
                    hour: "2-digit",
                    minute: "2-digit",
                    second: "2-digit",
                    year: "numeric",
                    month: "numeric",
                    day: "numeric"
                }) +
                    "." +
                    `${new Date().getTime()}`.slice(-3)
            ];

            // Initialize the log stream
            if (null === tools.logStream) {
                const pathRoot = path.dirname(path.dirname(__dirname));
                const logPath = path.join(pathRoot, "out", "activity.log");

                // Prepare the table header
                const tableColumns = ["Type", "Datetime", "File", "Line", "Function", "Message"];
                const tableHeader = `| ${tableColumns.join(" | ")} |\n|${tableColumns
                    .map(str => "-".repeat(str.length + 2))
                    .join("|")}|\n`;

                // Log file does not exist
                if (!fs.existsSync(logPath)) {
                    fs.mkdirSync(path.dirname(logPath), { recursive: true });
                    fs.writeFileSync(logPath, tableHeader);
                }

                // Log file is too large (>50MB)
                const logStats = fs.statSync(logPath);
                if (logStats.size > 50 * 1024 * 1024 || logStats.size < 50) {
                    fs.writeFileSync(logPath, tableHeader);
                }

                // Prepare the stream
                tools.logStream = fs.createWriteStream(logPath, { flags: "a" });
            }

            // Prepare the stack
            const error = new Error();
            Error.captureStackTrace(error);
            const stack = error.stack.split("\n");
            const stackInfo = new RegExp(/at ((\S+)\s)?\(?([^:]+):(\d+):(\d+)/).exec(stack[3].trim());

            // Append to logged data
            if (Array.isArray(stackInfo)) {
                // File
                rowData.push(stackInfo[3] ? stackInfo[3].replace(/^.*?\/kronup\-sdk\-generator\//gi, "") : "src/?");

                // Line
                rowData.push(stackInfo[4] ? stackInfo[4] : "?");

                // Function
                rowData.push(stackInfo[2] ? stackInfo[2] : "() => {}");
            }

            // Log the data
            data.forEach(row => {
                if ("string" !== typeof row) {
                    row = `[${typeof row}] ${JSON.stringify(row, null, 2)}`;
                }

                // Prepare the line
                `${row.trim()}`.split("\n").forEach(line => {
                    const lineTrimmed =
                        line.length > 5000
                            ? `${line.substring(0, 5000)}... (${new Intl.NumberFormat("en-GB").format(
                                  line.length - 5000
                              )} more characters)`
                            : line;

                    tools.logStream.write(`| ${rowData.join(" | ")} | ${lineTrimmed.replace(/\|/g, "¦")} |\n`);
                });
            });
        }
    }
};

/* eslint-disable prefer-rest-params */
module.exports = {
    tools: {
        /**
         * Show a heading
         * @param {string} text Heading
         */
        heading: function (text) {
            const headerText = `kronup - SDK Generator v.${version} - ${text.trim()}`;

            // Log the message
            console.log(`  \x1b[37m${headerText}\x1b[0m\n`);
            tools.log([`kronup v.${version}: ${text}`], tools.logLevels.info);
        },

        /**
         * Display the Logo
         */
        banner: function () {
            const bannerPath = path.join(path.dirname(path.dirname(__dirname)), "res", "banner.ans");
            console.log(fs.readFileSync(bannerPath).toString());
        },

        /**
         * Color a string with ANSI escape codes - Error
         *
         * @param {string} string String
         * @return {string}
         */
        colorError: function (string) {
            return `\x1b[31m${string}\x1b[0m`;
        },

        /**
         * Color a string with ANSI escape codes - Success
         *
         * @param {string} string String
         * @return {string}
         */
        colorSuccess: function (string) {
            return `\x1b[32m${string}\x1b[0m`;
        },

        /**
         * Color a string with ANSI escape codes - Warning
         *
         * @param {string} string String
         * @return {string}
         */
        colorWarn: function (string) {
            return `\x1b[33m${string}\x1b[0m`;
        },

        /**
         * Color a string with ANSI escape codes - Info
         *
         * @param {string} string String
         * @return {string}
         */
        colorInfo: function (string) {
            return `\x1b[36m${string}\x1b[0m`;
        },

        /**
         * Color a string with ANSI escape codes - Debug
         *
         * @param {string} string String
         * @return {string}
         */
        colorDebug: function (string) {
            return `"\x1b[0m${string}\x1b[0m`;
        }
    },

    /**
     * Output to console and log to file - Error
     */
    error: function () {
        console.log("\x1b[31m ", ...arguments, "\x1b[0m");
        tools.log([...arguments], tools.logLevels.error);
    },

    /**
     * Output to console and log to file - Warning
     */
    warn: function () {
        console.log("\x1b[33m ", ...arguments, "\x1b[0m");
        tools.log([...arguments], tools.logLevels.warn);
    },

    /**
     * Output to console and log to file - Information
     */
    info: function () {
        console.log("\x1b[36m ", ...arguments, "\x1b[0m");
        tools.log([...arguments], tools.logLevels.info);
    },

    /**
     * Output to console and log to file - Success
     */
    success: function () {
        console.log("\x1b[32m ", ...arguments, "\x1b[0m");
        tools.log([...arguments], tools.logLevels.info);
    },

    /**
     * Output to console and log to file - Debug
     */
    debug: function () {
        console.log("\x1b[0m ", ...arguments, "\x1b[0m");
        tools.log([...arguments], tools.logLevels.debug);
    },

    /**
     * Log to file only - Error
     */
    silentError: function () {
        tools.log([...arguments], tools.logLevels.error);
    },

    /**
     * Log to file only - Information
     */
    silentInfo: function () {
        tools.log([...arguments], tools.logLevels.info);
    },
    /**
     * Log to file only - Debug
     */
    silentDebug: function () {
        tools.log([...arguments], tools.logLevels.debug);
    }
};
