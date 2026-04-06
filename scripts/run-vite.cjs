const { existsSync } = require('fs');
const { dirname, join } = require('path');
const { spawnSync } = require('child_process');

const root = dirname(__dirname);
const localNode = join(root, 'node_modules', 'node', 'bin', 'node');
const platform = process.platform === 'win32' ? 'win' : process.platform;
const arch = platform === 'win' && process.arch === 'ia32' ? 'x86' : process.arch;
const packagePrefix = process.platform === 'darwin' && process.arch === 'arm64' ? 'node-bin' : 'node';
const packageBinary = process.platform === 'win32' ? 'node.exe' : 'node';
const packagedNode = join(root, 'node_modules', `${packagePrefix}-${platform}-${arch}`, 'bin', packageBinary);
const viteCli = join(root, 'node_modules', 'vite', 'bin', 'vite.js');
const nodeBinary = existsSync(localNode)
    ? localNode
    : (existsSync(packagedNode) ? packagedNode : process.execPath);

const result = spawnSync(nodeBinary, [viteCli, ...process.argv.slice(2)], {
    cwd: root,
    stdio: 'inherit',
});

process.exit(result.status ?? 1);
