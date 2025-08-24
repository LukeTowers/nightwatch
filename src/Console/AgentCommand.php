<?php

namespace Laravel\Nightwatch\Console;

use Illuminate\Console\Command;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Traits\ForwardsCalls;
use SensitiveParameter;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;

use function app;
use function date;

/**
 * @internal
 */
#[AsCommand(name: 'nightwatch:agent', description: 'Run the Nightwatch agent.')]
final class AgentCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'nightwatch:agent
        {--listen-on=}
        {--auth-connection-timeout=}
        {--auth-timeout=}
        {--ingest-connection-timeout=}
        {--ingest-timeout=}
        {--server=}
        {--silent : Do not output any message}';

    /**
     * @var string
     */
    protected $description = 'Run the Nightwatch agent.';

    public function __construct(
        #[SensitiveParameter] private ?string $token,
        private ?string $server,
        private ?string $ingestUri,
    ) {
        parent::__construct();
    }

    public function handle(): void
    {
        // TODO: Nightwatch::disable();
        $refreshToken = $this->token;

        $listenOn = $this->option('listen-on') ?? $this->ingestUri;

        $authenticationConnectionTimeout = $this->option('auth-connection-timeout');

        $authenticationTimeout = $this->option('auth-timeout');

        $ingestConnectionTimeout = $this->option('ingest-connection-timeout');

        $ingestTimeout = $this->option('ingest-timeout');

        $server = $this->option('server') ?? $this->server;

        $silent = $this->option('silent') ?: null;

        $quiet = $this->option('quiet') ?: null;

        // todo agent should use correct output.
        require __DIR__.'/../../agent/build/agent.phar';

        app()->terminating(function () {
            unlink(__DIR__.'/Foo.php');
            new Foo;
        });

        $handler = app(ExceptionHandler::class);
        app()->forgetInstance(ExceptionHandler::class);
        app()->instance(ExceptionHandler::class, new class($handler) implements ExceptionHandler
        {
            use ForwardsCalls;

            public function __construct(
                private ExceptionHandler $handler,
            ) {
                //
            }

            public function report(Throwable $e)
            {
                $this->handler->report($e);
            }

            public function shouldReport(Throwable $e)
            {
                return $this->handler->shouldReport($e);
            }

            public function render($request, Throwable $e)
            {
                return $this->handler->render($request, $e);
            }

            public function renderForConsole($output, Throwable $e)
            {
                $warning = static function (string $message): string {
                    return date('Y-m-d H:i:s').' [WARNING] '.$message.PHP_EOL;
                };

                $output->writeln($warning('An unhandled error occurred after shutdown.'));
                $output->writeln("{$e->getMessage()} in {$e->getFile()}:{$e->getLine()}");
                $output->writeln($output->isVerbose()
                    ? <<<MESSAGE
                        Stack trace:
                        {$e->getTraceAsString()}
                        MESSAGE
                    : <<<'MESSAGE'
                        To see a full stack trace, pass the `-v` flag when calling the the agent command, e.g., `php artisan nightwatch:agent -v`
                        MESSAGE
                );
                $output->writeln('This should not impact the operation of Nightwatch.');
            }
        });
    }
}
