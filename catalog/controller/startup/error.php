<?php
namespace Opencart\Catalog\Controller\Startup;
/**
 * Class Error
 *
 * @package Opencart\Catalog\Controller\Startup
 */
class Error extends \Opencart\System\Engine\Controller {
	/**
	 * Index
	 *
	 * @return void
	 */
	public function index(): void {
		$this->registry->set('log', new \Opencart\System\Library\Log($this->config->get('config_error_filename')));

		set_error_handler([$this, 'error']);
		set_exception_handler([$this, 'exception']);
	}

	/**
	 * Error
	 *
	 * @param int    $code
	 * @param string $message
	 * @param string $file
	 * @param int    $line
	 *
	 * @return bool
	 */
	public function error(int $code, string $message, string $file, int $line): bool {
		// PHP 8 compatible check for the @ suppression operator
		if (!(error_reporting() & $code)) {
			// Return false to let the standard PHP internal error handler take over (or do nothing)
			return false;
		}

		switch ($code) {
			case E_NOTICE:
			case E_USER_NOTICE:
				$error = 'Notice';
				break;
			case E_WARNING:
			case E_USER_WARNING:
				$error = 'Warning';
				break;
			case E_ERROR:
			case E_USER_ERROR:
				$error = 'Fatal Error';
				break;
			case E_DEPRECATED:
			case E_USER_DEPRECATED:
				$error = 'Deprecated';
				break;
			default:
				$error = 'Unknown';
				break;
		}

		if ($this->config->get('config_error_log')) {
			$this->log->write('PHP ' . $error . ':  ' . $message . ' in ' . $file . ' on line ' . $line);
		}

		if (in_array($code, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_PARSE, E_USER_ERROR, E_RECOVERABLE_ERROR], true)) {
			$this->outputError();
			exit(1);
		}

		return true;
	}

	/**
	 * Exception
	 *
	 * @param \Throwable $e
	 *
	 * @return void
	 */
	public function exception(\Throwable $e): void {
		$output  = 'Error: ' . $e->getMessage() . "\n";
		$output .= 'File: ' . $e->getFile() . "\n";
		$output .= 'Line: ' . $e->getLine() . "\n\n";

		foreach ($e->getTrace() as $key => $trace) {
			$output .= 'Backtrace: ' . $key . "\n";
			$output .= 'File: ' . ($trace['file'] ?? 'unknown') . "\n";
			$output .= 'Line: ' . ($trace['line'] ?? 'unknown') . "\n";

			if (isset($trace['class'])) {
				$output .= 'Class: ' . $trace['class'] . "\n";
			}

			$output .= 'Function: ' . $trace['function'] . "\n\n";
		}

		if ($this->config->get('config_error_log')) {
			$this->log->write(trim($output));
		}

		$this->outputError();
	}

	/**
	 * Output Error
	 *
	 * @return void
	 */
	private function outputError(): void {
		$this->response->addHeader(($this->request->server['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 500 Internal Server Error');
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->setOutput(json_encode([
			'success' => false,
			'error'   => 'Internal Server Error'
		]));
		$this->response->output();
	}
}
