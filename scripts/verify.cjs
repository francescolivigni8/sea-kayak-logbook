const { runSteps, root } = require('./check-runner.cjs');
const { ensureLocalNode } = require('./local-node.cjs');

async function main() {
    const ensureNodeStatus = ensureLocalNode();

    if (ensureNodeStatus !== 0) {
        process.exit(ensureNodeStatus);
    }

    await runSteps([
        {
            label: 'Wayfinder generate',
            command: 'php',
            args: ['artisan', 'wayfinder:generate', '--with-form', '--ansi'],
            options: { cwd: root },
        },
        {
            label: 'Vue type check',
            command: 'node_modules/vue-tsc/bin/vue-tsc.js',
            args: ['--noEmit'],
            options: { cwd: root, node: true },
        },
        {
            label: 'Vite production build',
            command: 'scripts/run-vite.cjs',
            args: ['build'],
            options: { cwd: root, node: true },
        },
        {
            label: 'PHP test suite',
            command: 'script',
            args: ['-q', '/dev/null', 'php', 'vendor/phpunit/phpunit/phpunit'],
            options: { cwd: root },
        },
    ]);
}

main().catch((error) => {
    process.stderr.write(`${error.message}\n`);
    process.exit(1);
});
