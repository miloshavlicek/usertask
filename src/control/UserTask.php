<?php

namespace Miloshavlicek\Control;

use Nette\Application\AbortException;
use Nette\Utils\Strings;
use Tracy\Debugger;

/**
 *
 * @author Miloš Havlíček <miloshavlicek@gmail.com>
 */
class UserTask
{
    private $breakOnFirstFail  = TRUE;
    private $data              = [];
    private $forwardExceptions = FALSE;
    private $exceptions        = [];
    private $messageFail;
    private $messageSuccess;
    private $name;
    private $presenter;
    private $redirectArgs;
    private $showDetails;
    private $showFlash         = TRUE;
    private $tasks             = [];

    public function __construct($presenter, $name = NULL)
    {
        $this->presenter = $presenter;
        $this->name      = $name;
    }

    public function addTask(callable $task): void
    {
        $this->tasks[] = $task;
    }

    public function executeOnly(): void
    {
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

    public function getData(): object
    {
        return (object) $this->data;
    }

    private function getMessageFail(): string
    {
        return $this->messageFail ?: ($this->name === NULL ? 'Bohužel došlo k chybě!'
                : 'Bohužel, při '.$this->name.' došlo k chybě.');
    }

    private function getMessageSuccess(): string
    {
        return $this->messageSuccess ?: ($this->name === NULL ? 'Akce byla úspěšně provedena.'
                : Strings::firstUpper($this->name).' proběhlo úspěšně.');
    }

    public function getShowDetails(): ?bool
    {
        return $this->showDetails !== NULL ? $this->showDetails : $_SERVER['REMOTE_ADDR']
            === '127.0.0.1' ? TRUE : FALSE;
    }

    public function run(): void
    {
        $this->executeOnly();

        $this->terminate();
    }

    public function setBreakOnFirstFail(bool $in): void
    {
        $this->breakOnFirstFail = $in;
    }

    public function setForwardExceptions(bool $in): void
    {
        $this->forwardExceptions = $in;
    }

    public function setMessageFail(string $in): void
    {
        $this->messageFail = $in;
    }

    public function setMessageSuccess(string $in): void
    {
        $this->messageSuccess = $in;
    }

    public function setName(string $name): void
    {
        $this->name = Strings::trim($name);
    }

    public function setRedirect(): void
    {
        $this->redirectArgs = func_get_args();
    }

    public function setShowDetails(bool $value): void
    {
        $this->showDetails = $value;
    }

    public function setShowFlash(bool $value): void
    {
        $this->showFlash = $value;
    }

    public function terminate(): void
    {
        $this->showFlash && $this->flash();
        !empty($this->redirectArgs) && call_user_func_array([$this->presenter, 'redirect'],
                $this->redirectArgs);
    }

    private function catchException(\Exception $e): void
    {
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

    private function runTask(callable $task): void
    {
        $data       = $task($this->getData());
        is_array($data) && $this->data = array_merge($this->data, $data);
    }

    private function flash(): void
    {
        count($this->exceptions) ? $this->flashError() : $this->flashSuccess();
    }

    private function flashError(): void
    {
        $message = $this->getMessageFail();

        if ($this->getShowDetails()) {
            $errors  = $this->getErrorMessages();
            $message .= Strings::fixEncoding(empty($errors) ? '' : ' ('.implode(', ',
                        $errors).')');
        }

        $this->presenter->flashMessage($message, 'danger');
    }

    private function flashSuccess(): void
    {
        $this->presenter->flashMessage($this->getMessageSuccess(), 'success');
    }

    private function getErrorMessages(): array
    {
        $out = [];

        foreach ($this->exceptions as $exception) {
            $out[] = $exception->getMessage();
        }

        return array_filter($out,
            function($var) {
            return !empty($var);
        });
    }
}