const { spawn } = require('child_process');
const { resolveNodeBinary, root } = require('./local-node.cjs');

const DEFAULT_HEARTBEAT_MS = 15000;

function formatDuration(ms) {
    const totalSeconds = Math.floor(ms / 1000);
    const minutes = Math.floor(totalSeconds / 60);
    const seconds = totalSeconds % 60;

    if (minutes === 0) {
        return `${seconds}s`;
    }

    return `${minutes}m ${seconds}s`;
}

function runCommand(label, command, args = [], options = {}) {
    const startedAt = Date.now();
    const heartbeatMs = options.heartbeatMs ?? DEFAULT_HEARTBEAT_MS;
    const cwd = options.cwd ?? root;
    const isNodeCommand = options.node === true;
    const commandToRun = isNodeCommand ? resolveNodeBinary() : command;
    const argsToRun = isNodeCommand ? [command, ...args] : args;

    return new Promise((resolve, reject) => {
        process.stdout.write(`\n==> ${label}\n`);

        const child = spawn(commandToRun, argsToRun, {
            cwd,
            stdio: ['ignore', 'inherit', 'inherit'],
            env: process.env,
        });

        const heartbeat = setInterval(() => {
            process.stdout.write(
                `[${label}] still running after ${formatDuration(Date.now() - startedAt)}\n`,
            );
        }, heartbeatMs);

        const forwardSignal = (signal) => {
            if (!child.killed) {
                child.kill(signal);
            }
        };

        process.once('SIGINT', forwardSignal);
        process.once('SIGTERM', forwardSignal);

        child.on('error', (error) => {
            clearInterval(heartbeat);
            process.off('SIGINT', forwardSignal);
            process.off('SIGTERM', forwardSignal);
            reject(error);
        });

        child.on('exit', (code, signal) => {
            clearInterval(heartbeat);
            process.off('SIGINT', forwardSignal);
            process.off('SIGTERM', forwardSignal);

            if (signal) {
                reject(new Error(`${label} ended by signal ${signal}`));
                return;
            }

            if ((code ?? 1) !== 0) {
                reject(new Error(`${label} failed with exit code ${code ?? 1}`));
                return;
            }

            process.stdout.write(
                `[${label}] finished in ${formatDuration(Date.now() - startedAt)}\n`,
            );
            resolve();
        });
    });
}

async function runSteps(steps) {
    for (const step of steps) {
        await runCommand(step.label, step.command, step.args ?? [], step.options ?? {});
    }
}

module.exports = {
    runCommand,
    runSteps,
    root,
};
