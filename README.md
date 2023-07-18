# Kronup - SDK Generator

## Table of Contents
- [Kronup - SDK Generator](#kronup---sdk-generator)
  - [Table of Contents](#table-of-contents)
  - [Compatibility](#compatibility)
  - [Installation](#installation)
  - [Quick Start](#quick-start)
    - [Adding new generators](#adding-new-generators)
    - [Developing a generator](#developing-a-generator)
      - [Generator structure](#generator-structure)
        - [config.yml](#configyml)
        - [hook.js](#hookjs)
        - [template directory](#template-directory)
    - [Running unit tests](#running-unit-tests)
    - [Building an SDK](#building-an-sdk)
    - [Configuration management](#configuration-management)
    - [Debugging and logging](#debugging-and-logging)
  - [Tips and tricks](#tips-and-tricks)
    - [Fetch latest OpenAPI specification](#fetch-latest-openapi-specification)
    - [Trigger package build](#trigger-package-build)
  - [Copyright and Licensing](#copyright-and-licensing)

## Compatibility

The kronup SDK Generator requires Node.js version 18+.

## Installation

Clone this Git repository and run ```npm install```.

## Quick Start

Available tools:

* ```npm run init [{client}]``` - [Add a new generator](#adding-new-generators)
* ```npm run dev [{client}]``` - [Develop a generator](#developing-a-generator)
* ```npm run test [{client}|local]``` - [Run unit tests](#running-unit-tests)
* ```npm run build [{client}]``` - [Build an SDK](#building-an-sdk)
* ```npm run config``` [Configuration management](#configuration-management)
* ```npm run lint``` - Perform static code analysis with ES Lint on the SDK Generator
* ```npm run format``` - Perform code formatting on the SDK Generator

### Adding new generators

Run ```npm run init``` and choose from the available clients or specify a client directly with ```npm run init {client}```.

This will automatically export the template files in ```src/generators/{client}``` and initialize the [**hook.js**](#hookjs) file.

### Developing a generator

Run ```npm run dev``` and select your target client or specify a client directly with ```npm run dev {client}```.

This tool will listen to changes made to the [generator files](#generator-structure) and automatically re-generate the SDK client.

File changes are gracefully propagated from the temporary output folder to ```out/{client}``` so that you can safely manage those files with any IDE.

#### Generator structure

```
src/generators/{client}
|   config.yml
|   hook.js
|   
└---dev
|  |   junit.jar
|  |   ... (support files)
|   
└---template
   |   model.mustache
   |   ... (template files)
```
##### config.yml

The **config.yml** contains OpenAPI Generator configuration to properly register new mustache templates (see [docs](https://github.com/OpenAPITools/openapi-generator/blob/master/docs/customization.md), [selective generation](https://openapi-generator.tech/docs/customization/#selective-generation)) and [template variables](https://github.com/swagger-api/swagger-codegen/wiki/Mustache-Template-Variables).

##### hook.js

The **hook.js** file contains 2 hooks that get executed automatically before and after the **build** step:

```js
    /**
     * Perform changes before build
     */
    async preBuild() {
        // @example Make changes to this.openApi object
    }

    /**
     * Perform changes after build
     */
    async postBuild() {
        // @example Append extra files to the output
        // @example this.mustacheFolder('extra'); 

        // @idea Perform regular expression changes to output
        // @idea Generate or copy images/other file formats
        // @idea Run any system-level command (email, curl, archive, ant build etc.)
    }

    /**
     * Run unit tests
     *
     * @param {string} pathOut    Path to output folder
     * @param {string} pathOutDev Path to output development folder
     */
    async test(pathOut, pathOutDev) {
        // @example Copy files from `${this.srcPath}/dev` to pathOutDev
        // @example Run PHPUnit (for PHP), jUnit (for Java), jest (for JS) etc.
    }
```
##### template directory

The **template** directory includes the ```.mustache``` files, as extracted by the OpenAPI Generator utility.

Newly added files can be included in the generated client in 2 ways:

1. Declare them in the [**config.yml**](#configyml) file
2. Append them using the ```postBuild()``` hook of [**hook.js**](#hookjs) with ``fs-extra`` functions or one of the pre-defined utilities

### Running unit tests

Run ```npm run test``` and choose from the available clients or specify a client directly with ```npm run test {client}```.

This will automatically execute the `test()` hook from [hook.js](#hookjs) for any of the available clients 
or it will run `jest` on the local source code if `local` is specified instead.

You can optionally pass the following environment variable to the unit test command:

- `KRONUP_API_KEY`

These variables get passed along to the unit testing utility.

Example: `KRONUP_API_KEY="api-key" npm run test php`

If these environment variables are not specified, values will default to `apiKey` from `application.json`.

### Building an SDK

Run ```npm run build``` and choose from the available clients or specify a client directly with ```npm run build {client}```.

This will execute all the tasks from [Develop a generator](#developing-a-generator) only once and store the output in the `/out` directory.

### Configuration management

All tools use the `config/openapi.json` file as input.

If the `config/openapi.json` file is missing, it is automatically created from `openapi-sample.json`.

You have the option to manage any number of configuration files with ```npm run config```.

Please note that all files except `openapi-sample.json` are ignored by Git.

### Debugging and logging

You can use the ```logger``` utility to both output text to the terminal and store data in the `out/activity.log` file.

`activity.log` is a simple text file that contains a markdown-compatible pipe-delimited table.
This format allows us to share issues captured by logs more easily in markdown-enabled collaboration environments.

Example log file contents:

| Type | Datetime | File | Line | Function | Message |
|------|----------|------|------|----------|---------|
| INFO  | 30/11/2022, 15:03:03.321 | src/actions/develop.js | 18 | () => {} | Kronup SDK Generator v.0.0.1 ¦ Development mode |
| INFO  | 30/11/2022, 15:03:03.391 | src/actions/develop.js | 34 | () => {} | Listening to "src/generators/php" for changes, re-building to "out/php"... |
| INFO  | 30/11/2022, 15:03:04.798 | src/utils/generators.js | 217 | () => {} | $ npx openapi-generator-cli generate -i /tmp/kronup-sdk/openapi-php.json -g php -c src/generators/php/config.yml -o /tmp/kronup-sdk/php |
| DEBUG | 30/11/2022, 15:03:25.613 | src/utils/generators.js | 230 | Socket.\<anonymous\> | [main] INFO  o.o.codegen.TemplateManager - writing file /tmp/kronup-sdk/php/.openapi-generator/FILES |
| DEBUG | 30/11/2022, 15:03:26.424 | src/utils/watcher.js | 89 | Object.synchronize | ⛳  15:03:26 (no changes) |

You can change the minimum log level from `config/application.json`.

Allowed values for `logLevel`: 
* debug
* info
* warn
* error

## Tips and tricks

### Fetch latest OpenAPI specification

Run the following command: 

```npm run config fetch sample```

### Trigger package build

In order to trigger a GitHub action to rebuild SDKs, just specify one of the following tags in your commit message:

* `[build-php]`

Workflows are defined in `.github/workflows/*.yml`.

## Copyright and Licensing

The license summary below may be copied.

```text
Copyright 2022-2023 kronup.com

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

    http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
```
