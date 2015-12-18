<?php

	class VMOutput_ncurses extends VMOutput {

		private $nc = null;
		private $panels = array();
		private $outputData = array('');
		private $traceData = array();
		private $inputTitle = '';

		function __construct($vm) {
			parent::__construct($vm);

			// Init Display
			$ths->nc = ncurses_init();
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

		public function redrawAll() {
			$this->redrawDebug();
			$this->redrawOutput();
			$this->redrawInput();
			$this->redrawTrace();
		}

		public function update() {
			parent::update();

			$this->redrawAll();
			$this->refreshAll();
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

		public function inputTitle($inputTitle) {
			$this->inputTitle = $inputTitle;
			$this->addTrace('@' . $inputTitle);
		}

		public function redrawInput() {
			ncurses_wclear($this->panels['input']);
			ncurses_wborder($this->panels['input'], 0, 0, 0, 0, 0, 0, 0, 0);
			ncurses_mvwaddstr($this->panels['input'], 1, 1, ' > ' . $this->getUserInput());

			if (!empty($this->inputTitle)) {
				ncurses_mvwaddstr($this->panels['input'], 0, 2, "[ " . $this->inputTitle . " ]");
			}
		}

		public function redrawDebug() {
			ncurses_wclear($this->panels['debug']);
			ncurses_wborder($this->panels['debug'], 0, 0, 0, 0, 0, 0, 0, 0);

			ncurses_mvwaddstr($this->panels['debug'], 2, 2, "R1: [ " . $this->getVM()->get(0) . " ]");
			ncurses_mvwaddstr($this->panels['debug'], 3, 2, "R2: [ " . $this->getVM()->get(1) . " ]");
			ncurses_mvwaddstr($this->panels['debug'], 4, 2, "R3: [ " . $this->getVM()->get(2) . " ]");

			ncurses_mvwaddstr($this->panels['debug'], 2, 27, "R4: [ " . $this->getVM()->get(3) . " ]");
			ncurses_mvwaddstr($this->panels['debug'], 3, 27, "R5: [ " . $this->getVM()->get(4) . " ]");
			ncurses_mvwaddstr($this->panels['debug'], 4, 27, "R6: [ " . $this->getVM()->get(5) . " ]");

			ncurses_mvwaddstr($this->panels['debug'], 2, 52, "R7: [ " . $this->getVM()->get(6) . " ]");
			ncurses_mvwaddstr($this->panels['debug'], 3, 52, "R8: [ " . $this->getVM()->get(7) . " ]");

			ncurses_mvwaddstr($this->panels['debug'], 6, 2, "Location: [ " . $this->getVM()->getLocation() . " ]");
			ncurses_mvwaddstr($this->panels['debug'], 6, 27, "Stack: [ " . implode(' ', $this->getVM()->getStack()) . " ]");

			ncurses_mvwaddstr($this->panels['debug'], 0, 2, "[ Debug ]");
		}

		public function getInput() {
			$this->redrawInput();
			return ncurses_getch();
		}
	}
