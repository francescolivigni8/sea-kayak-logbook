#!/usr/bin/env node

const { execFileSync } = require('node:child_process');
const { existsSync } = require('node:fs');

function run(command, args = []) {
    return execFileSync(command, args, {
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'pipe'],
    }).trim();
}

function tryRun(command, args = []) {
    try {
        return { ok: true, output: run(command, args) };
    } catch (error) {
        return {
            ok: false,
            output: error?.stderr?.toString?.() || error?.message || '',
        };
    }
}

const developerDir = tryRun('xcode-select', ['-p']);
const xcodebuild = tryRun('xcodebuild', ['-version']);
const pod = tryRun('pod', ['--version']);
const fullXcodePath = '/Applications/Xcode.app/Contents/Developer';
const hasFullXcodeInstall = existsSync(fullXcodePath);
const usingCommandLineTools = developerDir.ok && developerDir.output.includes('CommandLineTools');

const failures = [];
const warnings = [];

if (!developerDir.ok) {
    failures.push('`xcode-select -p` failed. Xcode tools are not configured.');
}

if (!hasFullXcodeInstall) {
    failures.push('Full Xcode is not installed at `/Applications/Xcode.app`.');
}

if (usingCommandLineTools) {
    failures.push('The active developer directory is Command Line Tools, not full Xcode.');
}

if (!xcodebuild.ok) {
    failures.push('`xcodebuild -version` failed. Full Xcode is required for iOS builds.');
}

if (!pod.ok) {
    warnings.push('CocoaPods is not installed. Default Capacitor iOS flows usually need it.');
}

if (failures.length === 0 && developerDir.output !== fullXcodePath) {
    warnings.push(
        `Full Xcode exists, but the active developer directory is \`${developerDir.output}\`. You likely want: sudo xcode-select --switch ${fullXcodePath}`,
    );
}

console.log('iOS toolchain check');
console.log(`- xcode-select: ${developerDir.ok ? developerDir.output : 'missing'}`);
console.log(`- xcodebuild: ${xcodebuild.ok ? xcodebuild.output.split('\n')[0] : 'missing'}`);
console.log(`- CocoaPods: ${pod.ok ? pod.output : 'missing'}`);

if (warnings.length > 0) {
    console.log('\nWarnings:');
    for (const warning of warnings) {
        console.log(`- ${warning}`);
    }
}

if (failures.length > 0) {
    console.error('\nBlocking issues:');
    for (const failure of failures) {
        console.error(`- ${failure}`);
    }

    process.exit(1);
}

console.log('\nThe iOS toolchain looks ready.');
