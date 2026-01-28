import js from "@eslint/js";
import prettier from "eslint-config-prettier/flat";
import importPlugin from "eslint-plugin-import";
import perfectionist from "eslint-plugin-perfectionist";
import prettierPlugin from "eslint-plugin-prettier";
import react from "eslint-plugin-react";
import reactHooks from "eslint-plugin-react-hooks";
import globals from "globals";
import typescript from "typescript-eslint";

/** @type {import('eslint').Linter.Config[]} */
export default [
  js.configs.recommended,
  ...typescript.configs.recommended,
  {
    files: ["**/*.{js,jsx,ts,tsx}"],
    plugins: {
      prettier: prettierPlugin,
      import: importPlugin,
      perfectionist: perfectionist,
    },
    settings: {
      "import/resolver": {
        typescript: {
          alwaysTryTypes: true,
          project: "./tsconfig.json",
        },
        node: {
          extensions: [".js", ".jsx", ".ts", ".tsx"],
        },
      },
    },
    rules: {
      "prettier/prettier": "warn",
      "sort-imports": "off",
      "import/order": "off",
      "no-unused-vars": "off",
      "@typescript-eslint/no-unused-vars": "error",
      "import/no-duplicates": "error",
      "import/newline-after-import": "warn",
      "import/named": "error",
      "import/default": "error",
      "import/no-unresolved": "error",
      "perfectionist/sort-enums": "warn",
      "perfectionist/sort-switch-case": "warn",
      "perfectionist/sort-interfaces": "warn",
      "perfectionist/sort-variable-declarations": "warn",
    },
  },
  {
    ...react.configs.flat.recommended,
    ...react.configs.flat["jsx-runtime"], // Required for React 17+
    languageOptions: {
      globals: {
        ...globals.browser,
      },
    },
    rules: {
      "react/react-in-jsx-scope": "off",
      "react/prop-types": "off",
      "react/no-unescaped-entities": "off",
    },
    settings: {
      react: {
        version: "detect",
      },
    },
  },
  {
    plugins: {
      "react-hooks": reactHooks,
    },
    rules: {
      "react-hooks/rules-of-hooks": "error",
      "react-hooks/exhaustive-deps": "warn",
    },
  },
  {
    ignores: [
      "vendor",
      "node_modules",
      "public",
      "bootstrap/ssr",
      "tailwind.config.js",
      "resources/js/components/ui",
      "resources/js/actions",
      "resources/js/routes",
      "resources/js/wayfinder",
      "eslint.config.js",
      "vite.config.js",
      "tsconfig.json",
    ],
  },
  prettier, // Turn off all rules that might conflict with Prettier
];
