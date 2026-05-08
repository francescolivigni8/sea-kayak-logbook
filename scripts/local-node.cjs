const { spawnSync } = require('child_process');
const { existsSync } = require('fs');
const { dirname, join } = require('path');

const root = dirname(__dirname);
const nodePackageDir = join(root, 'node_modules', 'node');
const localNode = join(nodePackageDir, 'bin', 'node');
const installer = join(nodePackageDir, 'installArchSpecificPackage.js');
const platform = process.platform === 'win32' ? 'win' : process.platform;
const arch = platform === 'win' && process.arch === 'ia32' ? 'x86' : process.arch;
const packagePrefix =
    process.platform === 'darwin' && process.arch === 'arm64' ? 'node-bin' : 'node';
const packageBinary = process.platform === 'win32' ? 'node.exe' : 'node';
const packagedNode = join(
    root,
    'node_modules',
    'node',
    'node_modules',
    `${packagePrefix}-${platform}-${arch}`,
    'bin',
    packageBinary,
);

function ensureLocalNode() {
    if (existsSync(localNode) || !existsSync(installer)) {
        return 0;
    }

    const result = spawnSync(process.execPath, [installer], {
        cwd: nodePackageDir,
        stdio: 'inherit',
    });

    return result.status ?? 1;
}

function resolveNodeBinary() {
    return existsSync(localNode)
        ? localNode
        : (existsSync(packagedNode) ? packagedNode : process.execPath);
}

module.exports = {
    ensureLocalNode,
    resolveNodeBinary,
    root,
};
