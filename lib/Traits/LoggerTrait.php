<?php

namespace OCA\FaceRecognition\Traits;
use \Psr\Log\LoggerInterface;
use OCA\FaceRecognition\AppInfo\Application;
use Symfony\Component\Console\Output\OutputInterface;

trait LoggerTrait {
	/** @var LoggerInterface */
	protected LoggerInterface $logger;

	/** @var OutputInterface|null */
	protected ?OutputInterface $output = null;

	/**
	 * Set the main PSR-3 logger (required).
	 */
	public function setLogger(LoggerInterface $logger): void {
		$this->logger = $logger;
	}

	/**
	 * Optionally set a console OutputInterface for mirrored messages.
	 */
	public function setOutput(OutputInterface $output): void {
		$this->output = $output;
	}
	/**
	 * Optionally get a console OutputInterface for mirrored messages.
	 */
	protected function getOutput(OutputInterface $output): OutputInterface {
		return $output;
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
		if (!isset($this->logger)) {
			throw new \RuntimeException('LoggerInterface must be set before logging.');
		}

		$contextData = $this->logContext($context);

		// Always log to PSR logger
		$this->logger->log($level, $message, $contextData);

		// Optionally mirror to CLI output
		if ($this->output) {
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
			$this->output->writeln($formatted);
		}
	}

	/** Convenience wrappers for logger levels **/

	protected function logDebug(string $message, array $context = []): void {
		$this->writeLog('debug', $message, $this->logContext($context));
	}

	protected function logInfo(string $message, array $context = []): void {
		$this->writeLog('info', $message, $this->logContext($context));
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
