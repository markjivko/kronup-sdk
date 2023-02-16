/**
 * Configuration
 *
 * @desc      Common configuration utility
 * @copyright (c) 2022-2023 kronup.com
 * @author    Mark Jivko
 */
const path = require("path");
const fs = require("fs-extra");

// Caches
let configApplication = null;

/* eslint-disable prefer-rest-params */
module.exports = {
    /**
     * Initialize and return the application cofiguration
     *
     * @param {boolean} reload
     * @return {string|null}
     * @throws {Error}
     */
    application: function (reload) {
        if (reload || null === configApplication) {
            const pathRoot = path.dirname(path.dirname(__dirname));
            const pathConfig = path.join(pathRoot, "config", "application.json");
            const pathConfigSample = path.join(pathRoot, "config", "application-sample.json");

            // Create the file from sample
            if (!fs.existsSync(pathConfig)) {
                fs.copyFileSync(pathConfigSample, pathConfig);
            }

            try {
                configApplication = JSON.parse(fs.readFileSync(pathConfig));
            } catch (e) {
                fs.copyFileSync(pathConfigSample, pathConfig);
                configApplication = JSON.parse(fs.readFileSync(pathConfigSample));
            }
        }

        return configApplication;
    },

    /**
     * Initialize and return the OpenAPI specification data
     *
     * @return {string|null}
     * @throws {Error}
     */
    openApi: function () {
        let result = null;
        const pathRoot = path.dirname(path.dirname(__dirname));
        const pathSpec = path.join(pathRoot, "config", "openapi.json");
        const pathSpecSample = path.join(pathRoot, "config", "openapi-sample.json");

        // Create the file from sample
        if (!fs.existsSync(pathSpec)) {
            fs.copyFileSync(pathSpecSample, pathSpec);
        }

        try {
            result = JSON.parse(fs.readFileSync(pathSpec));
        } catch (e) {
            throw new Error(`File "config/openapi.json" is malformed: ${e}`);
        }

        return result;
    }
};
