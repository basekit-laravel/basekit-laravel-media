const path = require("path");

// The build only inlines the `@import`ed partials under `resources/css/src/`
// into the files under `resources/css/dist/`. No transpiling: the panel is a
// Filament front end, and preset-env would rewrite logical properties and add
// vendor prefixes for browsers the panel does not support.
module.exports = {
  plugins: [
    require("postcss-import")({
      path: [
        path.resolve(__dirname, "node_modules"),
        path.resolve(process.cwd(), "node_modules"),
      ],
    }),
  ],
};
