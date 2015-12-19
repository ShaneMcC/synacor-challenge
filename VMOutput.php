<?php

	/**
	 * Class to deal with linking the console with the a VM.
	 */
	class VMOutput {

		/** VM we are displaying. */
		private $vm = null;
		/** Partial user input. */
		private $userInput = '';
		/** Our Event Handlers. */
		private $handlers = array();
		/** Input buffer for when the VM asks for input. */
		private $storedInput = array();
		/** Are we tracing? */
		protected $tracing = true;
		/** Are we only tracing on output or as soon as we get the trace? */
		protected $traceOnOutput = true;

		/**
		 * Create a new VMOutput
		 *
		 * @param $vm VM to Wrap
		 */
		function __construct($vm) {
			// Init Display
			$this->vm = $vm;
		}


		/**
		 * Change the given Handlers.
		 *
		 * @param $outHandlers array of new handlers.
		 */
		public final function setHandlers($outHandlers) {
			foreach ($outHandlers as $name => $handler) {
				$this->handlers[$name] = $handler;
			}
		}

		/**
		 * Get the current Handlers.
		 *
		 * @return Array of current handlers.
		 */
		public final function getHandlers() {
			return $this->handlers;
		}

		/**
		 * Update the screen.
		 * This does nothing in this class, but subclasses may do something
		 * with it.
		 */
		public function update() { }

		/**
		 * End the output.
		 * This allows us to do any cleanup we need.
		 */
		public function end() {
			die();
		}

		/**
		 * Get the VM we are wrapping.
		 *
		 * @return The VM we are wrapping.
		 */
		public function getVM() {
			return $this->vm;
		}

		/**
		 * Display some output.
		 * Output can be an array of lines to add, or a single character.
		 * Integers passed will be converted to characters.
		 * Some subclasses may buffer this output, some may just echo it
		 * immediately.
		 *
		 * @param $output Output to display.
		 */
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

		/**
		 * Enable tracing.
		 */
		public final function traceOn() {
			$this->tracing = true;
		}

		/**
		 * Disable tracing.
		 */
		public final function traceOff() {
			$this->tracing = false;
		}

		/**
		 * Enable tracing, and switch to immediate-output mode.
		 */
		public final function traceAll() {
			$this->traceOn();
			$this->traceOnOutput = false;
		}

		/**
		 * Enable tracing, and switch to trace-on-output mode.
		 */
		public final function traceOnOutput() {
			$this->traceOn();
			$this->traceOnOutput = true;
		}

		/**
		 * Are we tracing?
		 *
		 * @return True if tracing is enabled.
		 */
		public final function tracing() {
			return $this->tracing;
		}

		/**
		 * Add trace output.
		 *
		 * @param $output Line of output to add to trace.
		 */
		public function addTrace($output) {
			echo '{-- TRACE: ', $output, ' --}', "\n";

			global $__CLIOPTS;
			if (isset($__CLIOPTS['trace'])) {
				file_put_contents(getFilepath($__CLIOPTS['trace'], 'logs'), $output."\n", FILE_APPEND | LOCK_EX);
			}
		}

		/**
		 * Add a line of stored input to the buffer.
		 *
		 * @param $input Line of input to buffer.
		 */
		public final function addStoredInput($input) {
			$this->storedInput[] = $input;
		}

		/**
		 * Get the current stored input buffer.
		 *
		 * @return Array of stored input.
		 */
		public final function getStoredInput() {
			return $this->storedInput;
		}

		/**
		 * Override the entire stored input buffer with the provided buffer.
		 *
		 * @param $storedInput New input buffer.
		 */
		public final function setStoredInput($storedInput) {
			$this->storedInput = $storedInput;
		}

		/**
		 * Helper function for UI outputs to set a title.
		 * Non-UI outputs should just pass it to addTrace()
		 *
		 * @param $inputTitle New title.
		 */
		public function inputTitle($inputTitle) {
			$this->addTrace('@' . $inputTitle);
		}

		/**
		 * Get the current user input string.
		 *
		 * @return Current user input string.
		 */
		public function getUserInput() {
			return $this->userInput;
		}

		/**
		 * Get a single character of input from the terminal.
		 *
		 * @return A single character of input.
		 */
		public function getInput() {
			return ord(fread(STDIN, 1));
		}

		/**
		 * Get some input from the user and attempt to process it.
		 * This should be called in a loop.
		 *
		 * @return True if we should keep looping, or False to exit.
		 */
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

		/**
		 * Loop waitForUser() and then exit if it returns FALSE.
		 */
		public final function loop(){
			while ($this->waitForUser()) { }
			$this->end();
		}
	}
