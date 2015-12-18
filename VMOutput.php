<?php

	class VMOutput {

		private $vm = null;
		private $userInput = '';
		private $handlers = array();
		private $storedInput = array();
		private $tracing = true;
		private $traceOnOutput = true;

		function __construct($vm) {
			// Init Display
			$this->vm = $vm;
		}

		public final function setHandlers($outHandlers) {
			$this->handlers = $outHandlers;
		}

		public final function getHandlers() {
			return $this->handlers;
		}

		public function update() { }

		public function end() {
			die();
		}

		public function getVM() {
			return $this->vm;
		}

		public function addOutput($output) {
			if (is_array($output)) {
				echo "\n", implode("\n", $output), "\n";
			} else {
				if ($output === 10) {
					echo "\n";
				} else if (is_integer($output)) {
					echo chr($output);
				} else {
					echo $output;
				}
			}
		}

		public final function traceOn() {
			$this->tracing = true;
		}

		public final function traceOff() {
			$this->tracing = false;
		}

		public final function traceAll() {
			$this->traceOn();
			$this->traceOnOutput = false;
		}

		public final function traceOnOutput() {
			$this->traceOn();
			$this->traceOnOutput = true;
		}

		public final function tracing() {
			return $this->tracing;
		}

		public function addTrace($output) {
			echo '{-- TRACE: ', $output, ' --}', "\n";

			global $__CLIOPTS;
			if (isset($__CLIOPTS['trace'])) {
				file_put_contents(getFilepath($__CLIOPTS['trace'], 'logs'), $output."\n", FILE_APPEND | LOCK_EX);
			}
		}

		public final function addStoredInput($input) {
			$this->storedInput[] = $input;
		}

		public final function getStoredInput() {
			return $this->storedInput;
		}

		public final function setStoredInput($storedInput) {
			$this->storedInput = $storedInput;
		}

		public function inputTitle($inputTitle) {
			$this->addTrace('@' . $inputTitle);
		}

		public function getUserInput() {
			return $this->userInput;
		}

		public function getInput() {
			return ord(fread(STDIN, 1));
		}

		public final function waitForUser() {
			$this->update();
			$pressed = $this->getInput();
			$result = false;

			if ($pressed == 27) { // Escape Key, exit.
				$result = false;
			} else if ($pressed == 10 || $pressed == 13) { // Enter Key
				$in = $this->userInput;
				$this->userInput = '';

				$this->addTrace('Input: ' . $in);
				try {
					$result = $this->handlers['gotInput']($this, $this->vm, $in);
				} catch (Exception $e) {
					$this->outputData[] = '==========';
					$this->outputData[] = 'Caught Exception: ' . $e->getMessage();
					$this->outputData[] = '';
					foreach (explode("\n", $e->getTraceAsString()) as $t) {
						$this->outputData[] = $t;
					}
					$this->outputData[] = '==========';
					$this->outputData[] = '';
					$result = true;
				}
			} else if ($pressed == 263) { // Backspace
				$this->userInput = substr($this->userInput, 0, -1);
				$result = true;
			} else if ($pressed >= 32 && $pressed <= 126) {
				$this->userInput .= chr($pressed);
				$result = true;
			} else {
				echo 'UNKNOWN: ', $pressed, "\n";
				$result = true;
			}

			return $result;
		}

		public final function loop(){
			while ($this->waitForUser()) { }
			$this->end();
		}
	}
