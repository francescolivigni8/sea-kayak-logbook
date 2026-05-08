#!/usr/bin/env node

const { spawnSync } = require('node:child_process');
const { existsSync } = require('node:fs');
const { resolve } = require('node:path');

const repoRoot = resolve(__dirname, '..');
const xcodeProject = resolve(repoRoot, 'ios/App/App.xcodeproj');

if (!existsSync(xcodeProject)) {
    console.error('No iOS project found yet. Run `npm run ios:add` after installing full Xcode.');
    process.exit(1);
}

const result = spawnSync('./node_modules/.bin/cap', ['open', 'ios'], {
    cwd: repoRoot,
    stdio: 'inherit',
    shell: true,
});

process.exit(result.status ?? 1);
