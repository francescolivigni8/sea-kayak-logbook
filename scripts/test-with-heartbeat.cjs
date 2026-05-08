const { runCommand, root } = require('./check-runner.cjs');

async function main() {
    const forwardedArgs = process.argv.slice(2);

    await runCommand(
        'PHPUnit',
        'script',
        ['-q', '/dev/null', 'php', 'vendor/phpunit/phpunit/phpunit', ...forwardedArgs],
        { cwd: root },
    );
}

main().catch((error) => {
    process.stderr.write(`${error.message}\n`);
    process.exit(1);
});
