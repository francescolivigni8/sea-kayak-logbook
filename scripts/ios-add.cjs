#!/usr/bin/env node

const { spawnSync } = require('node:child_process');
const { existsSync } = require('node:fs');
const { resolve } = require('node:path');

const repoRoot = resolve(__dirname, '..');
const check = spawnSync(process.execPath, [resolve(__dirname, 'check-ios-toolchain.cjs')], {
    cwd: repoRoot,
    stdio: 'inherit',
});

if (check.status !== 0) {
    process.exit(check.status ?? 1);
}

if (existsSync(resolve(repoRoot, 'ios/App/App.xcodeproj'))) {
    console.log('iOS project already exists. Skipping `cap add ios`.');
    process.exit(0);
}

const result = spawnSync('./node_modules/.bin/cap', ['add', 'ios'], {
    cwd: repoRoot,
    stdio: 'inherit',
    shell: true,
});

process.exit(result.status ?? 1);
