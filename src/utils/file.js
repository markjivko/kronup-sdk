/**
 * File utilities
 *
 * @copyright (c) 2022-2023 kronup.com
 * @author    Mark Jivko
 */
const fs = require("fs-extra");
const path = require("path");

module.exports = {
    /**
     * Read all files in a directory recursively and return only the relative paths
     * The result is a hashmap for O(n) optimizations
     *
     * @param {string} dir Directory path
     * @return {object} Hashmap: file paths as keys
     */
    readdirRelative: dir => {
        /**
         * Read all files in a directory recursively
         *
         * @param {string}   p Path
         * @param {string[]} a Result
         * @return {string[]} Paths
         */
        const readdirSync = (p, a = []) => {
            if (fs.existsSync(p) && fs.statSync(p).isDirectory()) {
                fs.readdirSync(p).map(f => readdirSync(a[a.push(path.join(p, f)) - 1], a));
            }

            return a;
        };

        return fs.existsSync(dir)
            ? readdirSync(dir)
                  .map(p => {
                      return p.substring(dir.length).replace(/^\/+/, "");
                  })
                  .filter(p => {
                      return !fs.statSync(path.join(dir, p)).isDirectory();
                  })
                  .reduce((map, obj) => {
                      map[obj] = 1;

                      return map;
                  }, {})
            : {};
    }
};
