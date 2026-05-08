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
    ]);
}

main().catch((error) => {
    process.stderr.write(`${error.message}\n`);
    process.exit(1);
});
