<?php

namespace Miloshavlicek\Control;

use Nette\Application\AbortException;
use Nette\Utils\Strings;
use Tracy\Debugger;

/**
 *
 * @author Miloš Havlíček <miloshavlicek@gmail.com>
 */
class UserTask extends \Nette\Object {

	private $breakOnFirstFail = TRUE;
	private $data = [];
	private $forwardExceptions = FALSE;
	private $exceptions = [];
	private $messageFail;
	private $messageSuccess;
	private $name;
	private $presenter;
	private $redirectArgs;
	private $showDetails = TRUE;
	private $showFlash = TRUE;
	private $tasks = [];

	public function __construct($presenter, $name = NULL) {
		$this->presenter = $presenter;
		$this->name = $name;
	}

	public function addTask($task) {
		$this->tasks[] = $task;
	}

	public function executeOnly() {
		foreach ($this->tasks as $tasksOne) {
			try {
				$this->runTask($tasksOne);
			} catch (AbortException $e) {
				$this->terminate();
				throw $e;
			} catch (\Exception $e) {
				$this->catchException($e);
			}
		}
	}

	public function getData() {
		return (object) $this->data;
	}

	private function getMessageFail() {
		return $this->messageFail ? : ($this->name === NULL ? 'Bohužel došlo k chybě!' : 'Bohužel, při ' . $this->name . ' došlo k chybě.');
	}

	private function getMessageSuccess() {
		return $this->messageSuccess ? : ($this->name === NULL ? 'Akce byla úspěšně provedena.' : Strings::firstUpper($this->name) . ' proběhlo úspěšně.');
	}

	public function run() {
		$this->executeOnly();

		$this->terminate();
	}

	public function setBreakOnFirstFail($in) {
		$this->breakOnFirstFail = (bool) $in;
	}

	public function setForwardExceptions($in) {
		$this->forwardExceptions = (bool) $in;
	}

	public function setMessageFail($in) {
		$this->messageFail = (string) $in;
	}

	public function setMessageSuccess($in) {
		$this->messageSuccess = (string) $in;
	}

	public function setName($name) {
		$this->name = Strings::trim($name);
	}

	public function setRedirect() {
		$this->redirectArgs = func_get_args();
	}

	public function setShowDetails($value) {
		$this->showDetails = (bool) $value;
	}

	public function setShowFlash($value) {
		$this->showFlash = (bool) $value;
	}

	public function terminate() {
		$this->showFlash && $this->flash();
		!empty($this->redirectArgs) && call_user_func_array([$this->presenter, 'redirect'], $this->redirectArgs);
	}

	private function catchException($e) {
		try {
			Debugger::log($e, Debugger::ERROR);
		} catch (\Exception $e) {

		}

		if ($this->forwardExceptions) {
			throw $e;
		}
		$this->exceptions[] = $e;
		$this->breakOnFirstFail && $this->terminate();
	}

	private function runTask($task) {
		$data = $task($this->getData());
		is_array($data) && $this->data = array_merge($this->data, $data);
	}

	private function flash() {
		count($this->exceptions) ? $this->flashError() : $this->flashSuccess();
	}

	private function flashError() {
		$message = $this->getMessageFail();

		if ($this->showDetails) {
			$errors = $this->getErrorMessages();
			$message .= (empty($errors) ? '' : ' (' . implode(', ', $errors) . ')');
		}

		$this->presenter->flashMessage($message, 'danger');
	}

	private function flashSuccess() {
		$this->presenter->flashMessage($this->getMessageSuccess(), 'success');
	}

	private function getErrorMessages() {
		$out = [];

		foreach ($this->exceptions as $exception) {
			$out[] = $exception->getMessage();
		}

		return array_filter($out, function($var) {
			return !empty($var);
		});
	}

}
