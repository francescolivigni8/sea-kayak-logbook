#!/usr/bin/env node

const { execFileSync } = require('node:child_process');
const { copyFileSync, existsSync, mkdirSync } = require('node:fs');
const { dirname, resolve } = require('node:path');

const repoRoot = resolve(__dirname, '..');
const sourceIcon = resolve(repoRoot, 'public/brand/ykj-logo-circle.png');
const sourceLaunchLogo = resolve(repoRoot, 'public/brand/ykj-logo-clean.png');
const outDir = resolve(repoRoot, 'resources/native/ios');

if (!existsSync(sourceIcon) || !existsSync(sourceLaunchLogo)) {
    console.error('Missing brand source images in public/brand.');
    process.exit(1);
}

mkdirSync(outDir, { recursive: true });

const outputFiles = [
    {
        source: sourceIcon,
        target: resolve(outDir, 'AppStoreIcon-1024.png'),
        size: 1024,
    },
    {
        source: sourceIcon,
        target: resolve(outDir, 'AppIcon-512.png'),
        size: 512,
    },
    {
        source: sourceLaunchLogo,
        target: resolve(outDir, 'LaunchLogo-1024.png'),
        size: 1024,
    },
];

for (const file of outputFiles) {
    mkdirSync(dirname(file.target), { recursive: true });

    execFileSync('sips', ['-z', String(file.size), String(file.size), file.source, '--out', file.target], {
        stdio: 'ignore',
    });
}

copyFileSync(sourceIcon, resolve(outDir, 'AppIcon-source.png'));
copyFileSync(sourceLaunchLogo, resolve(outDir, 'LaunchLogo-source.png'));

console.log(`Generated iOS brand assets in ${outDir}`);
