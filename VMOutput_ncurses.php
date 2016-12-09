<?php

	/**
	 * NCurses based VMOutput
	 */
	class VMOutput_ncurses extends VMOutput {

		/** NCurses pointer. */
		private $nc = null;
		/** Array of ncurses windows we have created. */
		private $panels = array();
		/** Output Data buffer. */
		private $outputData = array('');
		/** Trace Data buffer. */
		private $traceData = array();
		/** Input Bar Title. */
		private $inputTitle = '';
		/** Last height of the display. */
		private $lastBottom = 0;
		/** Last Width of the display/ */
		private $lastRight = 0;

		/**
		 * Create a new NCurses based VMOutput.
		 *
		 * @param $vm VM to display.
		 */
		function __construct($vm) {
			parent::__construct($vm);

			// Init Display
			ncurses_init();
			$this->refreshAll();
			$this->update();
		}

		/**
		 * Check if the display size has changed, and destroy/recreate our
		 * panels if it has.
		 */
		private function checkSize() {
			$bottom = $left = 0;
			ncurses_getmaxyx(STDSCR, $bottom, $right);
			if ($bottom != $this->lastBottom || $right != $this->lastRight) {
				foreach ($this->panels as $p) {
					ncurses_delwin($p);
				}
				$this->panels = array();
				$this->init();
			}
		}

		/**
		 * Initiate our NCurses panels.
		 */
		private function init() {
			// Get Sizes
			$top = $right = $bottom = $left = 0;
			ncurses_getmaxyx(STDSCR, $bottom, $right);

			$this->lastBottom = $bottom;
			$this->lastRight = $right;

			// Panel Layouts
			$traceWidth = min($right/2, 55);
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

		/**
		 * Redraw all our panels to show updated content.
		 */
		public function redrawAll() {
			$this->redrawDebug();
			$this->redrawOutput();
			$this->redrawInput();
			$this->redrawTrace();
		}

		/**
		 * Update the display.
		 * - Check size
		 * - Redraw Content
		 * - Refresh display
		 */
		public function update() {
			parent::update();

			$this->checkSize();
			$this->redrawAll();
			$this->refreshAll();
		}

		/**
		 * Refresh All Panels.
		 */
		public function refreshAll() {
			foreach ($this->panels as $name => $p) { ncurses_wrefresh($p); }
			ncurses_refresh();
		}

		/**
		 * End the output, clean up ncurses.
		 */
		public function end() {
			ncurses_end();
		}

		/**
		 * Add some output to be displayed in the output panel.
		 * Output can be an array of lines to add, or a single character.
		 * Integers passed will be converted to characters.
		 * Some subclasses may buffer this output, some may just echo it
		 * immediately.
		 *
		 * @param $output Output to display.
		 */
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

		/**
		 * Redraw the output panel.
		 */
		public function redrawOutput() {
			ncurses_wclear($this->panels['output']);
			ncurses_wborder($this->panels['output'], 0, 0, 0, 0, 0, 0, 0, 0);
			ncurses_mvwaddstr($this->panels['output'], 0, 2, "[ Output ]");

			$this->drawTextToWindow($this->panels['output'], $this->outputData);
		}

		/**
		 * Add some output to the trace panel.
		 *
		 * @param $output Line of output to add to trace.
		 */
		public function addTrace($output) {
			$this->traceData[] = $output;
			if (!$this->traceOnOutput) {
				$this->update();
			}

			global $__CLIOPTS;
			if (isset($__CLIOPTS['trace'])) {
				file_put_contents(getFilepath($__CLIOPTS['trace'], 'logs'), $output."\n", FILE_APPEND | LOCK_EX);
			}
		}

		/**
		 * Redraw the trace panel.
		 */
		public function redrawTrace() {
			ncurses_wclear($this->panels['trace']);
			ncurses_wborder($this->panels['trace'], 0, 0, 0, 0, 0, 0, 0, 0);
			ncurses_mvwaddstr($this->panels['trace'], 0, 2, "[ Logging ]");

			$this->drawTextToWindow($this->panels['trace'], $this->traceData);
		}

		/**
		 * Draw an array of text to a given panel, with line wraps.
		 * This makes sure we only draw text within the panel borders, not over
		 * the top of them.
		 *
		 * @param $panel Panel we are drawing text on.
		 * @param Array of text to draw.
		 */
		public function drawTextToWindow($panel, $text) {
			$y = 1;
			$x = 2;
			ncurses_getmaxyx($panel, $height, $width);
			$arrayStart = 0 - $height + 2;

			$out = array();
			foreach (array_slice($text, $arrayStart) as $line) {
				$line = wordwrap($line, $width - 4, "\n", true);
				foreach (explode("\n", $line) as $l) {
					$out[] = $l;
				}
			}
			foreach (array_slice($out, $arrayStart) as $line) {
				ncurses_wmove($panel, $y, $x);
				ncurses_waddstr($panel, $line);
				$y++;
			}
		}

		/**
		 * Set the title of the input bar.
		 *
		 * @param $inputTitle New title.
		 */
		public function inputTitle($inputTitle) {
			$this->inputTitle = $inputTitle;
			$this->addTrace('@' . $inputTitle);
		}

		/**
		 * Redraw the input bar.
		 */
		public function redrawInput() {
			ncurses_wclear($this->panels['input']);
			ncurses_wborder($this->panels['input'], 0, 0, 0, 0, 0, 0, 0, 0);

			if (!empty($this->inputTitle)) {
				ncurses_mvwaddstr($this->panels['input'], 0, 2, "[ " . $this->inputTitle . " ]");
			}

			$this->drawTextToWindow($this->panels['input'], array('> ' . $this->getUserInput()));
		}

		/**
		 * Redraw the debug Panel.
		 */
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

		/**
		 * Get a single character of input from the terminal.
		 *
		 * @return A single character of input.
		 */
		public function getInput() {
			$this->redrawInput();
			return ncurses_getch();
		}
	}
