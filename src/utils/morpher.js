/**
 * Morpher - Parse OpenAPI v3 specification for better handling of "allOf" and "oneOf" syntax
 *
 * @copyright (c) 2022-2023 kronup.com
 * @author    Mark Jivko
 */

/* eslint-disable guard-for-in */
const morpher = {
    /**
     * Parse OpenAPI object
     *
     * @param {object} openApi OpenAPI object
     */
    parse(openApi) {
        // Remove deprecated operations
        for (const path in openApi.paths) {
            for (const verb in openApi.paths[path]) {
                if ("boolean" === typeof openApi.paths[path][verb].deprecated && openApi.paths[path][verb].deprecated) {
                    delete openApi.paths[path][verb];
                }
            }
        }

        // Model descriptions are unescaped, so we must replace | with / in order to keep the table structure intact
        for (const modelName in openApi.components.schemas) {
            for (const property in openApi.components.schemas[modelName].properties) {
                if ("string" === typeof openApi.components.schemas[modelName].properties[property].description) {
                    openApi.components.schemas[modelName].properties[property].description = openApi.components.schemas[
                        modelName
                    ].properties[property].description.replace(/\|/g, "/");
                }
            }
        }

        // Prepare known operation IDs hashmap to avoid name collisions
        const operationIds = {};
        for (const path in openApi.paths) {
            for (const verb in openApi.paths[path]) {
                operationIds[openApi.paths[path][verb].operationId] = `${path}-${verb}`;
            }
        }

        // "oneOf" request payloads become separate entrypoints
        for (const path in openApi.paths) {
            for (const verb in openApi.paths[path]) {
                if (
                    "object" === typeof openApi.paths[path][verb].requestBody &&
                    "object" === typeof openApi.paths[path][verb].requestBody.content["application/json"] &&
                    Array.isArray(openApi.paths[path][verb].requestBody.content["application/json"].schema.oneOf)
                ) {
                    // The current operation ID can be reused
                    delete operationIds[openApi.paths[path][verb].operationId];

                    // Flag for using default description (prevents duplicate content)
                    let usedDefaultDesc = false;

                    // Go through oneOf operations
                    openApi.paths[path][verb].requestBody.content["application/json"].schema.oneOf.forEach(item => {
                        if ("string" === typeof item["$ref"]) {
                            // Warn about references
                            if (!item["$ref"].match(/^#\/components\/schemas\//g)) {
                                console.warn("Invalid reference", path, verb);
                                process.exit(1);
                            }

                            // Get the operation name
                            const opName = item["$ref"].replace(/^#\/\w+\/\w+\//gi, "");

                            // Deep clone of new operation
                            const newOperation = JSON.parse(JSON.stringify(openApi.paths[path][verb]));

                            // New schema
                            newOperation.requestBody.content["application/json"].schema = { ...item };

                            do {
                                // No prefix needed
                                newOperation.operationId = opName[0].toUpperCase() + opName.substring(1);
                                if ("undefined" === typeof operationIds[newOperation.operationId]) {
                                    break;
                                }

                                // First three characters of first 2 words
                                const opPrefix = path
                                    .replace(/^\/v\d+\/|\/\{.*?\}/gi, "")
                                    .split("/")
                                    .slice(0, 2)
                                    .map(str => str[0].toUpperCase() + str.substring(1, 3))
                                    .join("");
                                newOperation.operationId = `${opPrefix}${opName}`;
                                if ("undefined" === typeof operationIds[newOperation.operationId]) {
                                    break;
                                }

                                console.warn(
                                    "Duplicate operation",
                                    newOperation.operationId,
                                    `${path}-${verb}`,
                                    operationIds[newOperation.operationId]
                                );
                                process.exit(1);
                            } while (false);

                            // Store the new operation id
                            operationIds[newOperation.operationId] = `${path}-${verb}`;

                            // New description
                            if (
                                "object" === typeof openApi.components.schemas[opName] &&
                                "string" === typeof openApi.components.schemas[opName].description
                            ) {
                                // Use model description
                                newOperation.description = openApi.components.schemas[opName].description;
                            } else {
                                if (usedDefaultDesc) {
                                    // Blank description
                                    newOperation.description = "";
                                } else {
                                    // Use default description
                                    usedDefaultDesc = true;
                                }
                            }

                            // Store the new path
                            const newPath = `${path}#${verb}-${opName}`;
                            if ("object" !== typeof openApi.paths[newPath]) {
                                openApi.paths[newPath] = {};
                            }
                            openApi.paths[newPath][verb] = newOperation;
                        }
                    });

                    // Remove this verb
                    delete openApi.paths[path][verb];

                    // Path is now empty, safe to remove it as well
                    if (!Object.keys(openApi.paths[path]).length) {
                        delete openApi.paths[path];
                    }
                }
            }
        }
    }
};

module.exports = morpher;
