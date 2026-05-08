const { spawnSync } = require('child_process');
const { join } = require('path');
const { ensureLocalNode, resolveNodeBinary, root } = require('./local-node.cjs');

const viteCli = join(root, 'node_modules', 'vite', 'bin', 'vite.js');
const ensureNodeStatus = ensureLocalNode();

if (ensureNodeStatus !== 0) {
    process.exit(ensureNodeStatus);
}

const nodeBinary = resolveNodeBinary();

const result = spawnSync(nodeBinary, [viteCli, ...process.argv.slice(2)], {
    cwd: root,
    stdio: 'inherit',
});

process.exit(result.status ?? 1);
