const { existsSync } = require('fs');
const { dirname, join } = require('path');
const { spawnSync } = require('child_process');

const root = dirname(__dirname);
const nodePackageDir = join(root, 'node_modules', 'node');
const localNode = join(nodePackageDir, 'bin', 'node');
const installer = join(nodePackageDir, 'installArchSpecificPackage.js');

if (existsSync(localNode) || !existsSync(installer)) {
    process.exit(0);
}

const result = spawnSync(process.execPath, [installer], {
    cwd: nodePackageDir,
    stdio: 'inherit',
});

process.exit(result.status ?? 1);
