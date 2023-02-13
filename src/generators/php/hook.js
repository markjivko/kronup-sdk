/**
 * PHP SDK hooks
 *
 * @package   Kronup
 * @author    Mark Jivko
 */
const iHook = require("../../lib/iHook");
const { readdirRelative } = require("../../utils/file");
const logger = require("../../utils/logger");
const config = require("../../utils/config");
const fs = require("fs-extra");
const path = require("path");
const { spawnSync } = require("child_process");
const md5File = require("md5-file");
const Mustache = require("mustache");

/* eslint-disable guard-for-in */
module.exports = class hook extends iHook {
    /**
     * Perform changes after build
     */
    async postBuild() {
        // Add extra files
        ["lib", "test", "docs"].forEach(dir => this.mustacheFolder(dir));

        // Go through the documents
        for (const relativePath in readdirRelative(path.join(this.buildPath, "docs"))) {
            const docFileName = path.basename(relativePath);
            const docFilePath = path.join(this.buildPath, "docs", relativePath);
            const contentsOriginal = fs.readFileSync(docFilePath).toString();

            // Fix variable values
            const contentsNew = contentsOriginal.replace(/(\$arg_\w+)\s+=\s+(.*?);/gis, (match, varName, varValue) => {
                // IPFS works with SplFileObject
                if ("IPFSApi.md" === docFileName && "$arg_file" === varName) {
                    match = `${varName} = new \\SplFileObject('${varValue.replace(/['"]+/g, "")}');`;
                } else {
                    // Provide a real mnemonic
                    if (varValue.match(/(?:mnemonic.*example)/gi)) {
                        match = `${varName} = 'police hat quantum advance enroll glove thank build warfare inject invite obscure enable inflict seek web cable answer chest suspect comfort tent source seven';`;
                    } else {
                        // Poorly escaped string
                        const strMatch = varValue.match(/"'(.*?)'"/i);
                        if (null !== strMatch) {
                            match = `${varName} = '${strMatch[1]}';`;
                        }
                    }
                }

                return match;
            });

            // Save the file
            if (contentsNew !== contentsOriginal) {
                fs.writeFileSync(docFilePath, contentsNew);
            }
        }

        // Remove extra files
        const filesToRemove = [".php-cs-fixer.dist.php", ".travis.yml"];

        // Remove unreliable tests in production
        if (config.application().production) {
            fs.readdirSync(path.join(this.buildPath, "test", "Api"))
                .filter(fileName => {
                    return !fileName.match(/^(?:Exception)Test\.php$/g);
                })
                .forEach(fileName => filesToRemove.push(`test/Api/${fileName}`));
        }

        filesToRemove.forEach(item => {
            const itemPath = path.join(this.buildPath, item);
            if (fs.existsSync(itemPath)) {
                if (fs.lstatSync(itemPath).isDirectory()) {
                    fs.rmSync(itemPath, { recursive: true, force: true });
                } else {
                    fs.unlinkSync(itemPath);
                }
            }
        });

        // Move examples
        await this.moveExamples(
            "examples",
            (contents, path, docPath) => {
                const parentDepth = 1 + (docPath.match(/\//g) || []).length;

                return contents.replace(/__DIR__/g, `dirname(__DIR__, ${parentDepth})`);
            },
            "php -f"
        );
    }

    /**
     * Run unit tests
     *
     * @param {string} pathOut    Path to output folder
     * @param {string} pathOutDev Path to output development folder
     */
    async test(pathOut, pathOutDev) {
        // Autoloader not added - script failure
        if (!fs.existsSync(path.join(pathOut, "autoload.php"))) {
            logger.error("OpenAPI generator failure\n");
            process.exit(1);
        }

        // Dev folder path
        const pathSrcDev = `${this.srcPath}/dev`;

        // Copy composer.phar if missing or changed
        const changedPhar = this._testUpdateComposerPhar(pathSrcDev, pathOutDev);

        // Update composer.json if missing or changed
        const changedJson = this._testUpdateComposerJson(pathOutDev);

        // Update the phpunit.xml.dist
        this._testUpdatePhpUnitXml(pathOutDev);

        // Composer binary or definition changed
        if (changedPhar || changedJson) {
            // Clean-up vendor and lock
            ["composer.lock", "vendor"].forEach(item => {
                const pathItem = path.join(pathOutDev, item);
                if (fs.existsSync(pathItem)) {
                    fs.lstatSync(pathItem).isDirectory()
                        ? fs.rmSync(pathItem, { recursive: true, force: true })
                        : fs.unlinkSync(pathItem);
                }
            });
        }

        // Vendor directory missing, rebuild
        if (!fs.existsSync(path.join(pathOutDev, "vendor"))) {
            logger.info("✨ Rebuilding packages...");
            spawnSync("php", ["composer.phar", "install"], {
                cwd: pathOutDev,
                stdio: ["ignore", process.stdout, process.stderr]
            });
        }

        // Run unit tests
        const code = spawnSync("vendor/bin/phpunit", [], {
            cwd: pathOutDev,
            stdio: ["ignore", process.stdout, process.stderr],
            env: this.getEnv()
        });

        // Fail to prevent further actions in GitHub workflow
        process.exit(code.status);
    }

    /**
     * Update "composer.phar"
     *
     * @param {string} pathSrcDev Path to generator source
     * @param {string} pathOutDev Path to generator output
     * @return {boolean} True if file changed, false otherwise
     */
    _testUpdateComposerPhar(pathSrcDev, pathOutDev) {
        let changed = false;

        const composerPharSrc = path.join(pathSrcDev, "composer.phar");
        const composerPharOut = path.join(pathOutDev, "composer.phar");

        if (!fs.existsSync(composerPharOut) || md5File.sync(composerPharSrc) !== md5File.sync(composerPharOut)) {
            logger.info(`✨ Wrote file "${path.basename(composerPharOut)}"`);
            fs.copyFileSync(composerPharSrc, composerPharOut);
            changed = true;
        }

        return changed;
    }

    /**
     * Update "composer.json"
     *
     * @param {string} pathOutDev Path to generator output
     * @return {boolean} True if file changed, false otherwise
     */
    _testUpdateComposerJson(pathOutDev) {
        let changed = false;

        const composerJsonSrc = path.join(this.srcPath, "template", "composer.mustache");
        const composerJsonOut = path.join(pathOutDev, "composer.json");

        const composerJsonSrcContents = JSON.parse(
            Mustache.render(fs.readFileSync(composerJsonSrc).toString(), {
                escapedInvokerPackage: "Kronup",
                srcBasePath: "../php/lib",
                testBasePath: "../php/test"
            })
        );

        const composerJsonSrcData = {};
        ["require", "require-dev", "autoload", "autoload-dev"].forEach(key => {
            composerJsonSrcData[key] = composerJsonSrcContents[key];
        });

        const composerJsonSrcString = JSON.stringify(composerJsonSrcData, null, 4);
        const composerJsonOutString = fs.existsSync(composerJsonOut) ? fs.readFileSync(composerJsonOut).toString() : "";

        if (composerJsonSrcString !== composerJsonOutString) {
            logger.info(`✨ Wrote file "${path.basename(composerJsonOut)}"`);
            fs.writeFileSync(composerJsonOut, composerJsonSrcString);
            changed = true;
        }

        return changed;
    }

    /**
     * Update "phpunit.xml.dist"
     *
     * @param {string} pathOutDev Path to generator output
     * @return {boolean} True if file created, false otherwise
     */
    _testUpdatePhpUnitXml(pathOutDev) {
        let changed = false;

        const phpunitXmlOut = path.join(pathOutDev, "phpunit.xml.dist");
        if (!fs.existsSync(phpunitXmlOut)) {
            const phpunitXmlSrc = path.join(this.srcPath, "template", "phpunit.xml.mustache");

            const phpunitXmlContents = Mustache.render(fs.readFileSync(phpunitXmlSrc).toString(), {
                escapedInvokerPackage: "Kronup",
                srcBasePath: "../php/lib",
                testBasePath: "../php/test"
            });

            logger.info(`✨ Wrote file "${path.basename(phpunitXmlOut)}"`);
            fs.writeFileSync(phpunitXmlOut, phpunitXmlContents);
            changed = true;
        }

        return changed;
    }
};
