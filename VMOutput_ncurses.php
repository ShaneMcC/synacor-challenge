<?php

	class VMOutput_ncurses {

		private $nc = null;
		private $panels = array();
		private $vm = null;
		private $userInput = '';
		private $inputTitle = '';
		private $handlers = array();
		private $storedInput = array();

		private $outputData = array('');
		private $traceData = array();
		private $tracing = true;
		private $traceOnOutput = true;

		function __construct($vm) {
			// Init Display
			$ths->nc = ncurses_init();
			$this->vm = $vm;
			$this->refreshAll();
			$this->init();
			$this->refreshAll();
		}

		private function init() {
			// Get Sizes
			$top = $right = $bottom = $left = 0;
			ncurses_getmaxyx(STDSCR, $bottom, $right);

			// Panel Layouts
			$traceWidth = 55;
			$traceHeight = $bottom;

			$debugWidth = $right - $traceWidth;
			$debugHeight = 8;

			$inputWidth = $debugWidth;
			$inputHeight = 3;

			$outputWidth = $debugWidth;
			$outputHeight = $bottom - $debugHeight - $inputHeight;

			$this->panels['trace'] = ncurses_newwin($traceHeight, $traceWidth, $top, $right - $traceWidth);
			ncurses_wborder($this->panels['trace'], 0, 0, 0, 0, 0, 0, 0, 0);
			ncurses_mvwaddstr($this->panels['trace'], 0, 2, "[ Logging ]");
			ncurses_wmove($this->panels['trace'], 2, 2);

			$this->panels['debug'] = ncurses_newwin($debugHeight, $debugWidth, $bottom - $debugHeight, $left);

			$this->panels['input'] = ncurses_newwin($inputHeight, $inputWidth, $bottom - $debugHeight - $inputHeight, $left);

			$this->panels['output'] = ncurses_newwin($outputHeight, $outputWidth, $top, $left);
			ncurses_wborder($this->panels['output'], 0, 0, 0, 0, 0, 0, 0, 0);
			ncurses_mvwaddstr($this->panels['output'], 0, 2, "[ Output ]");
			ncurses_wmove($this->panels['output'], 2, 2);

			ncurses_curs_set(0);
			ncurses_cbreak();
			ncurses_noecho();
			$this->redrawAll();
		}

		public function setHandlers($outHandlers) {
			$this->handlers = $outHandlers;
		}

		public function getHandlers() {
			return $this->handlers;
		}

		public function redrawAll() {
			$this->redrawDebug();
			$this->redrawOutput();
			$this->redrawInput();
			$this->redrawTrace();
		}

		public function refreshAll() {
			foreach ($this->panels as $p) { ncurses_wrefresh($p); }
			ncurses_refresh();
		}

		public function end() {
			ncurses_end();
		}

		public function addOutput($output) {
			if (is_array($output)) {
				foreach ($output as $out) {
					$this->outputData[] = $out;
				}
				$this->outputData[] = '';
			} else {
				if ($output === 10) {
					$this->outputData[] = '';
				} else if (is_integer($output)) {
					$this->outputData[count($this->outputData) - 1] .= chr($output);
				} else {
					$this->outputData[count($this->outputData) - 1] .= $output;
				}
			}
			$this->redrawAll();
			$this->refreshAll();
		}

		public function redrawOutput() {
			ncurses_wclear($this->panels['output']);
			ncurses_wborder($this->panels['output'], 0, 0, 0, 0, 0, 0, 0, 0);
			ncurses_mvwaddstr($this->panels['output'], 0, 2, "[ Output ]");

			$y = $x = 2;

			ncurses_getmaxyx($this->panels['output'], $height, $width);

			$arrayStart = 0 - $height + 4;
			foreach (array_slice($this->outputData, $arrayStart) as $line) {
				ncurses_wmove($this->panels['output'], $y, $x);
				ncurses_waddstr($this->panels['output'], $line);
				$y++;
			}

			$this->refreshAll();
		}


		public function traceOn() {
			$this->tracing = true;
		}

		public function traceOff() {
			$this->tracing = false;
		}

		public function traceAll() {
			$this->traceOn();
			$this->traceOnOutput = false;
		}

		public function traceOnOutput() {
			$this->traceOn();
			$this->traceOnOutput = true;
		}

		public function tracing() {
			return $this->tracing;
		}

		public function addTrace($output) {
			$this->traceData[] = $output;
			if (!$this->traceOnOutput) {
				$this->redrawAll();
				$this->refreshAll();
			}

			global $__CLIOPTS;
			if (isset($__CLIOPTS['trace'])) {
				file_put_contents(getFilepath($__CLIOPTS['trace'], 'logs'), $output."\n", FILE_APPEND | LOCK_EX);
			}
		}

		public function redrawTrace() {
			ncurses_wclear($this->panels['trace']);
			ncurses_wborder($this->panels['trace'], 0, 0, 0, 0, 0, 0, 0, 0);
			ncurses_mvwaddstr($this->panels['trace'], 0, 2, "[ Logging ]");

			$y = $x = 2;
			ncurses_getmaxyx($this->panels['trace'], $height, $width);
			$arrayStart = 0 - $height + 4;

			foreach (array_slice($this->traceData, $arrayStart) as $line) {
				ncurses_wmove($this->panels['trace'], $y, $x);
				ncurses_waddstr($this->panels['trace'], $line);
				$y++;
			}

			$this->refreshAll();
		}

		public function addStoredInput($input) {
			$this->storedInput[] = $input;
		}

		public function getStoredInput() {
			return $this->storedInput;
		}

		public function setStoredInput($storedInput) {
			$this->storedInput = $storedInput;
		}

		public function inputTitle($inputTitle) {
			$this->inputTitle = $inputTitle;
			$this->addTrace('@' . $inputTitle);
		}

		public function redrawInput() {
			ncurses_wclear($this->panels['input']);
			ncurses_wborder($this->panels['input'], 0, 0, 0, 0, 0, 0, 0, 0);
			ncurses_mvwaddstr($this->panels['input'], 1, 1, ' > ' . $this->userInput);

			if (!empty($this->inputTitle)) {
				ncurses_mvwaddstr($this->panels['input'], 0, 2, "[ " . $this->inputTitle . " ]");
			}
		}

		public function redrawDebug() {
			ncurses_wclear($this->panels['debug']);
			ncurses_wborder($this->panels['debug'], 0, 0, 0, 0, 0, 0, 0, 0);

			ncurses_mvwaddstr($this->panels['debug'], 2, 2, "R1: [ " . $this->vm->get(0) . " ]");
			ncurses_mvwaddstr($this->panels['debug'], 3, 2, "R2: [ " . $this->vm->get(1) . " ]");
			ncurses_mvwaddstr($this->panels['debug'], 4, 2, "R3: [ " . $this->vm->get(2) . " ]");

			ncurses_mvwaddstr($this->panels['debug'], 2, 27, "R4: [ " . $this->vm->get(3) . " ]");
			ncurses_mvwaddstr($this->panels['debug'], 3, 27, "R5: [ " . $this->vm->get(4) . " ]");
			ncurses_mvwaddstr($this->panels['debug'], 4, 27, "R6: [ " . $this->vm->get(5) . " ]");

			ncurses_mvwaddstr($this->panels['debug'], 2, 52, "R7: [ " . $this->vm->get(6) . " ]");
			ncurses_mvwaddstr($this->panels['debug'], 3, 52, "R8: [ " . $this->vm->get(7) . " ]");

			ncurses_mvwaddstr($this->panels['debug'], 6, 2, "Location: [ " . $this->vm->getLocation() . " ]");
			ncurses_mvwaddstr($this->panels['debug'], 6, 27, "Stack: [ " . implode(' ', $this->vm->getStack()) . " ]");

			ncurses_mvwaddstr($this->panels['debug'], 0, 2, "[ Debug ]");
		}

		public function waitForUser() {
			$this->redrawAll();
			$this->refreshAll();
			$pressed = ncurses_getch();
			$result = false;

			if ($pressed == 27) { // Escape Key, exit.
				$result = false;
			} else if ($pressed == 13) { // Enter Key
				$in = $this->userInput;
				$this->userInput = '';
				$this->inputTitle = '';
				$this->redrawInput();

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

		public function loop(){
			while ($this->waitForUser()) { }
			$this->end();
		}
	}
