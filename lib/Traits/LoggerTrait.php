<?php

namespace OCA\FaceRecognition\Traits;
use \Psr\Log\LoggerInterface;
use OCA\FaceRecognition\AppInfo\Application;
use Symfony\Component\Console\Output\OutputInterface;

trait LoggerTrait {
    /** @var LoggerInterface */
    protected static ?LoggerInterface $logger = null;

    /** @var OutputInterface|null */
    protected static ?OutputInterface $output = null;

    // Add static verbosity storage
    private static int $currentVerbosity = OutputInterface::VERBOSITY_NORMAL;

    // Add verbosity mapping
    private static array $verbosityLevels = [
        'debug' => OutputInterface::VERBOSITY_DEBUG,      // -vvv
        'info' => OutputInterface::VERBOSITY_VERY_VERBOSE, // -vv
        'notice' => OutputInterface::VERBOSITY_VERBOSE, // -v
        'warning' => OutputInterface::VERBOSITY_NORMAL,
        'error' => OutputInterface::VERBOSITY_NORMAL,
        'critical' => OutputInterface::VERBOSITY_NORMAL
    ];

    /**
     * Set the main PSR-3 logger (required).
     */
    public function setLogger(LoggerInterface $logger): void {
        if (self::$logger !== null) {
            error_log(sprintf(
                '[LoggerTrait Warning] Logger already set! Old=%s, New=%s',
                get_class(self::$logger),
                get_class($logger)
            ));
        }
        
        if (self::$currentVerbosity >= OutputInterface::VERBOSITY_DEBUG) {
            error_log(sprintf(
                '[LoggerTrait Debug] Setting logger: class=%s',
                get_class($logger)
            ));
        }
        self::$logger = $logger;
    }

    /**
     * Optionally set a console OutputInterface for mirrored messages.
     */
    public function setOutput(OutputInterface $output): void {
        if (self::$output !== null) {
            if (self::$logger) {
                self::$logger->warning(sprintf(
                    'Output already set! Old=%s, New=%s',
                    get_class(self::$output),
                    get_class($output)
                ));
            }
        }

        if (self::$currentVerbosity >= OutputInterface::VERBOSITY_DEBUG) {
            error_log(sprintf(
                '[LoggerTrait Debug] Setting output: class=%s, verbosity=%d',
                get_class($output),
                $output->getVerbosity()
            ));
        }
        
        self::$output = $output;
        self::setVerbosity($output->getVerbosity());
    }

    /**
     * Get the shared output interface
     */
    protected static function getOutput(): ?OutputInterface {
        return self::$output;
    }

    /**
     * Build a consistent context array for all log entries.
     */
    protected function logContext(array $extra = []): array {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 5);

        return array_merge([
            'app'    => Application::APP_NAME,
            'method' => $trace[2]['function'] ?? 'unknown',
            'class'  => $trace[2]['class'] ?? 'unknown',
            'file'   => $trace[1]['file'] ?? __FILE__,
            'line'   => $trace[1]['line'] ?? __LINE__,
        ], $extra);
    }

    /**
     * Unified logging logic.
     * Always logs to ILogger, optionally mirrors to console output.
     */
    protected function writeLog(string $level, string $message, array $context = []): void {
        if (!isset(self::$logger)) {
            throw new \RuntimeException('LoggerInterface must be set before logging.');
        }

        $contextData = $this->logContext($context);

        // Always log to PSR logger
        self::$logger->log($level, $message, $contextData);

        // Check if we should output based on verbosity
        if (self::$output && $this->shouldOutputForVerbosity($level)) {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            
            // Count depth only from FaceRecognition namespace calls
            $depth = 0;
            foreach ($trace as $call) {
                if (isset($call['class']) && strpos($call['class'], 'OCA\\FaceRecognition') === 0) {
                    $depth++;
                }
            }
            // Subtract 3 to account for writeLog and logXXX methods
            $depth = max(0, $depth - 3);
            if ($depth > 1) {
                $indent="\t";
            }
            
            // Define colors for different log levels
            $levelColors = [
                'debug' => '<fg=cyan>',
                'info' => '<fg=blue>',
                'warning' => '<fg=yellow>',
                'error' => '<fg=red>',
                'critical' => '<fg=red;bg=white>'
            ];

            $padLength = 9; // Length of 'CRITICAL: ' which is the longest level
            $levelStr = strtoupper($level) . ':';
            
            // Apply color formatting
            $colorStart = $levelColors[$level] ?? '<fg=white>';
            $colorEnd = '</>';
            
            $formatted = $indent . $colorStart . str_pad($levelStr, $padLength, ' ') . $colorEnd . ' ' . $message;
            self::$output->writeln($formatted);
        }
    }

    /**
     * Set verbosity level for all instances
     */
    public static function setVerbosity(int $verbosity): void {
        self::$currentVerbosity = $verbosity;
    }

    /**
     * Get current verbosity level
     */
    public static function getVerbosity(): int {
        return self::$currentVerbosity;
    }

    /**
     * Check if message should be output based on verbosity level
     */
    private function shouldOutputForVerbosity(string $level): bool {
        if (!self::$output) {
            return false;
        }

        $requiredVerbosity = self::$verbosityLevels[$level] ?? OutputInterface::VERBOSITY_NORMAL;
        return self::$currentVerbosity >= $requiredVerbosity;
    }

    /** Convenience wrappers for logger levels **/

    protected function logDebug(string $message, array $context = []): void {
        $this->writeLog('debug', $message, $this->logContext($context));
    }

    protected function logInfo(string $message, array $context = []): void {
        $this->writeLog('info', $message, $this->logContext($context));
    }
    
    protected function logNotice(string $message, array $context = []): void {
        $this->writeLog('notice', $message, $this->logContext($context));
    }

    protected function logWarning(string $message, array $context = []): void {
        $this->writeLog('warning', $message, $this->logContext($context));
    }

    protected function logError(string $message, array $context = []): void {
        $this->writeLog('error', $message, $this->logContext($context));
    }
    
    protected function logCrit(string $message, array $context = []): void {
        $this->writeLog('critical', $message, $this->logContext($context));
    }
}
