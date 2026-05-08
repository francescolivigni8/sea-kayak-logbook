const { runCommand, root } = require('./check-runner.cjs');
const { ensureLocalNode } = require('./local-node.cjs');

async function main() {
    const ensureNodeStatus = ensureLocalNode();

    if (ensureNodeStatus !== 0) {
        process.exit(ensureNodeStatus);
    }

    await runCommand(
        'Vite production build',
        'scripts/run-vite.cjs',
        ['build'],
        { cwd: root, node: true },
    );
}

main().catch((error) => {
    process.stderr.write(`${error.message}\n`);
    process.exit(1);
});
