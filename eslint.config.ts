import js from "@eslint/js";
import stylistic from "@stylistic/eslint-plugin";
import importPlugin from "eslint-plugin-import";
import eslintPluginPrettierRecommended from "eslint-plugin-prettier/recommended";
import react from "eslint-plugin-react";
import reactHooks from "eslint-plugin-react-hooks";
import { defineConfig, globalIgnores } from "eslint/config";
import globals from "globals";
import ts from "typescript";
import tseslint from "typescript-eslint";

export default defineConfig([
  js.configs.recommended,
  ...tseslint.configs.recommended,
  react.configs.flat.recommended,
  react.configs.flat["jsx-runtime"],
  reactHooks.configs.flat.recommended,
  importPlugin.flatConfigs.recommended,
  importPlugin.flatConfigs.typescript,
  {
    files: ["**/*.{js,mjs,cjs,jsx,ts,mts,cts,tsx}"],
    languageOptions: {
      globals: {
        ...globals.browser,
        ...globals.node,
      },
      parserOptions: {
        ecmaFeatures: { jsx: true },
        projectService: true,
      },
    },
    plugins: {
      "@stylistic": stylistic,
    },
    settings: {
      react: {
        version: "detect",
      },
      "import/resolver": {
        typescript: {
          alwaysTryTypes: true,
          project: "./tsconfig.json",
          extensionAlias: {
            ".js": [".js", ".ts", ".tsx", ".d.ts"],
            ".mjs": [".mjs", ".mts", ".d.mts"],
            ".cjs": [".cjs", ".cts", ".d.cts"],
          },
        },
        node: {
          extensions: [".js", ".jsx", ".ts", ".tsx"],
        },
      },
    },
    rules: {
      eqeqeq: "error",
      curly: "error",
      "no-constant-condition": "error",
      "no-fallthrough": "error",
      "no-redeclare": "error",
      "no-console": "warn",
      "no-unused-vars": "off",
      "@typescript-eslint/no-unused-vars": [
        "error",
        {
          argsIgnorePattern: "^_",
          varsIgnorePattern: "^_",
          caughtErrorsIgnorePattern: "^_",
        },
      ],
      "@typescript-eslint/consistent-type-imports": "warn",
      "@typescript-eslint/no-explicit-any": "warn",
      "@typescript-eslint/no-non-null-assertion": "warn",
      "react/react-in-jsx-scope": "off",
      "react/prop-types": "off",
      "sort-imports": "off",
      "import/order": "off",
      "import/no-duplicates": "error",
      "import/newline-after-import": "warn",
      "import/no-unresolved": "error",
      "@stylistic/lines-between-class-members": ["error", "always"],
    },
  },
  eslintPluginPrettierRecommended,
  globalIgnores([
    "vendor/**",
    "node_modules/**",
    "bootstrap/cache/**",
    "public/build/**",
    "storage/**",
    "resources/js/shared/wayfinder/**",
    "resources/js/shared/components/ui/**",
    "resources/js/shared/components/reactBits/**",
    "eslint.config.ts",
    "vite.config.ts",
    "tsconfig.json",
  ]),
]);
