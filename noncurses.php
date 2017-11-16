<?php
	/**
	 * Implement some ncurses functions as a polyfil for when ncurses is not
	 * present.
	 *
	 * This does not use the ncurses library at all, and is not a direct
	 * implementation of ncurses. Only some features are implemented, with
	 * others left as empty polyfils for future implementation.
	 */

	/**
	 * Class representing a NonCurses Exception
	 */
	class NonCursesException extends Exception { }

	/**
	 * Class representing a NonCurses Window
	 */
	class NonCursesWindow {
		/** Our parent window. */
		protected $parent;

		/** How many columns wide are we? */
		protected $cols = 1;

		/** How many lines tall are we? */
		protected $lines = 1;

		/** Where is our top-left corner relative to our parent? */
		protected $topleft = [0, 0];

		/** Where is our per-window cursor? */
		protected $cursor = [0, 0];

		/** Have we been destroyed? */
		protected $destroyed = false;

		/** Our current buffer. */
		protected $buffer = [];

		/** Our last buffer. */
		protected $lastBuffer = [];

		/** DrawQueue. */
		protected $drawQueue = [];

		/** Colours used for drawing debugging. */
		protected $debugColours = ["\033[91m", "\033[92m", "\033[93m", "\033[94m", "\033[95m", "\033[96m"];

		/** Last colour used for drawing debugging. */
		protected $debugColour = 0;

		/* Enable render debugging? */
		protected $renderDebug = FALSE;

		/* Enable render debugging of blanks? */
		protected $renderDebugBlank = FALSE;

		/* Use queue-based drawing rather than full-screen drawing? */
		protected $useDrawQueue = TRUE;

		/**
		 * Create a new NonCursesWindow Window
		 *
		 * @param $parent Parent window
		 */
		public function __construct($parent) {
			if ($parent == null) {
				throw new NonCursesException('Parent window must be non-null');
			}
			$this->parent = $parent;

			$this->resetBuffer();
		}

		function resetBuffer($overwrite = true, $character = ' ') {
			$this->drawQueue = [];
			$this->buffer = new SplFixedArray($this->lines);
			$this->lastBuffer = new SplFixedArray($this->lines);

			for ($y = 0; $y < $this->lines; $y++) {
				$this->buffer[$y] = new SplFixedArray($this->cols);
				$this->lastBuffer[$y] = new SplFixedArray($this->cols);

				for ($x = 0; $x < $this->cols; $x++) {
					$this->buffer[$y][$x] = $character;
					$this->lastBuffer[$y][$x] = NULL;
				}
			}

			return;
		}

		private function setBufferChar($x, $y, $char) {
			if ($x < $this->cols && $y < $this->lines) {
				$this->buffer[$y][$x] = $char;

				if (isset($this->lastBuffer[$y][$x]) && $this->lastBuffer[$y][$x] != $char) {
					$this->drawQueue[$y][$x] = $char;
				}
			}
		}

		private function getBufferChar($x, $y) {
			if ($x < $this->cols && $y < $this->lines) {
				return $this->buffer[$y][$x];
			}
		}

		/**
		 * Destroy this window. This will make all paint operations noop.
		 */
		public function destroy() {
			$this->destroyed = true;
		}

		/** Set how many columns wide this window is. */
		public function setCols($cols) { $this->cols = $cols; $this->resetBuffer(false); }

		/** Set how many lines tall this window is. */
		public function setLines($lines) { $this->lines = $lines; $this->resetBuffer(false); }

		/** Get how many columns wide this window is. */
		public function getCols() { return $this->cols; }

		/** Get how many lines tall this window is. */
		public function getLines() { return $this->lines; }

		/** Set where our X position is relative to our parent. */
		public function setRelX($relX) { $this->topleft[0] = $relX; }

		/** Set where our Y position is relative to our parent. */
		public function setRelY($relY) { $this->topleft[1] = $relY; }

		/** Get where our X position is relative to our parent. */
		public function getRelX() { return $this->topleft[0]; }

		/** Get where our Y position is relative to our parent. */
		public function getRelY() { return $this->topleft[1]; }

		/** Set the X position of our cursor. */
		public function setCursorX($relX) { $this->cursor[0] = $relX; }

		/** Set the Y position of our cursor. */
		public function setCursorY($relY) { $this->cursor[1] = $relY; }

		/** Get the X position of our cursor. */
		public function getCursorX() { return $this->cursor[0]; }

		/** Get the Y position of our cursor. */
		public function getCursorY() { return $this->cursor[1]; }

		/** Get our absolute X position on the screen. */
		public function getAbsX() { return $this->parent->getAbsX() + $this->topleft[0]; }

		/** Get our absolute Y position on the screen. */
		public function getAbsY() { return $this->parent->getAbsY() + $this->topleft[1]; }

		/**
		 * Get the bounding box for this window.
		 *
		 * @return Array [$top, $left, $bottom, $right];
		 */
		public function getBounds() {
			$top = $this->getAbsY();
			$bottom = $this->getAbsY() + $this->getLines();
			$left = $this->getAbsX();
			$right = $this->getAbsX() + $this->getCols();

			return [$top, $left, $bottom, $right];
		}

		/**
		 * Add a string of text in the given location.
		 *
		 * @param $line Line to add
		 * @param $x X Position to add at (or null for cursor X)
		 * @param $y Y Position to add at (or null for cursor X)
		 */
		public function addString($line, $x = null, $y = null) {
			if ($this->destroyed) { return; }

			if ($x === null) { $x = $this->getCursorX(); }
			if ($y === null) { $y = $this->getCursorY(); }

			for ($i = 0; $i < strlen($line); $i++) {
				if (!isset($this->buffer[$y][$x + $i])) { continue; }

				$this->setBufferChar($x + $i, $y, $line[$i]);

				$this->setCursorX(min(count($this->buffer[$y]), $x + $i + 1));
				$this->setCursorY($y);
			}
		}

		/**
		 * Draw this window on the screen.
		 *
		 * @param $clear Clear the screen instead of drawing?
		 */
		public function draw($clear = false) {
			if ($this->useDrawQueue) {
				$this->drawQueue($clear);
			} else {
				$this->drawAll($clear);
			}
		}

		/**
		 * Draw the window using queue-based drawing rather than full-screen
		 * drawing.
		 *
		 * @param $clear Clear the screen instead of drawing?
		 */
		private function drawQueue($clear = false) {
			if ($this->destroyed) { return; }

			if ($this->renderDebug) { $this->debugColour = ($this->debugColour + 1) % count($this->debugColours); }

			if ($clear) {
				$this->drawQueue = [];
				for ($y = 0; $y < $this->lines; $y++) {
					for ($x = 0; $x < $this->cols; $x++) {
						if ($this->lastBuffer[$y][$x] != ' ' && $this->lastBuffer[$y][$x] != ' ') {
							$this->drawQueue[$y][$x] = ' ';
						}
					}
				}
				return;
			}

			foreach ($this->drawQueue as $line => $dql) {
				foreach ($dql as $col => $char) {
					if ($this->parent == null) {
						NonCursesScreen::get()->moveCursor($line, $col);
						if ($this->renderDebug) {
							echo $this->debugColours[$this->debugColour];
						}
						echo ($char == ' ' && $this->renderDebugBlank) ? '█' : $char;
						$this->lastBuffer[$line][$col] = $char;
					} else {
						$this->parent->setBufferChar($this->getRelX() + $col, $this->getRelY() + $line, $char);
					}
				}
			}

			$this->drawQueue = [];
			return;
		}


		/**
		 * Redraw the whole screen.
		 *
		 * @param $clear Clear the screen instead of drawing?
		 */
		private function drawAll($clear = false) {
			if ($this->destroyed) { return; }
			list($top, $left, $bottom, $right) = $this->getBounds();

			if ($this->renderDebug) { $this->debugColour = ($this->debugColour + 1) % count($this->debugColours); }

			// Draw the window
			for ($line = $top; $line < $bottom; $line++) {
				for ($col = $left; $col < $right; $col++) {
					$thisCol = $col - $left;
					$thisLine = $line - $top;
					$output = $clear ? ' ' : $this->getBufferChar($thisCol, $thisLine);

					if ($this->parent == null) {
						if ($this->lastBuffer[$thisLine][$thisCol] != $this->buffer[$thisLine][$thisCol]) {
							NonCursesScreen::get()->moveCursor($line, $col);
							if ($this->renderDebug) {
								if ($output == ' ' && $this->renderDebugBlank) { $output = '█'; }
								echo $this->debugColours[$this->debugColour];
							}
							echo $output;
							$this->lastBuffer[$thisLine][$thisCol] = $this->buffer[$thisLine][$thisCol];
						}
					} else {
						$this->parent->setBufferChar($col, $line, $output);
					}
				}
			}

			$this->drawQueue = [];
			return;
		}

		/**
		 * Clear the space occupied by this window.
		 */
		public function clear() {
			if ($this->destroyed) { return; }
			$this->resetBuffer(true);
			$this->draw(true);
			$this->setCursorX(0);
			$this->setCursorY(0);
		}

		/**
		 * Draw a border on this window.
		 * (A character of 0 == default)
		 *
		 * @param $b_left Left border character
		 * @param $b_right Right border character
		 * @param $b_top Top border character
		 * @param $b_bottom Bottom border character
		 * @param $b_tl_corner Top-left border character
		 * @param $b_tr_corner Top-right border character
		 * @param $b_bl_corner bottom-left border character
		 * @param $b_br_corner bottom-right border character
		 */
		public function drawBorder($b_left, $b_right, $b_top, $b_bottom, $b_tl_corner, $b_tr_corner, $b_bl_corner, $b_br_corner) {
			if ($this->destroyed) { return; }

			$b_left = ($b_left == 0 ? ACS_VLINE : $b_left);
			$b_right = ($b_right == 0 ? ACS_VLINE : $b_right);
			$b_top = ($b_top == 0 ? ACS_HLINE : $b_top);
			$b_bottom = ($b_bottom == 0 ? ACS_HLINE : $b_bottom);
			$b_tl_corner = ($b_tl_corner == 0 ? ACS_ULCORNER : $b_tl_corner);
			$b_tr_corner = ($b_tr_corner == 0 ? ACS_URCORNER : $b_tr_corner);
			$b_bl_corner = ($b_bl_corner == 0 ? ACS_LLCORNER : $b_bl_corner);
			$b_br_corner = ($b_br_corner == 0 ? ACS_LRCORNER : $b_br_corner);

			// Get the bounds for this window
			list($top, $left, $bottom, $right) = [0, 0, $this->lines, $this->cols];

			// Draw the border
			for ($line = $top; $line < $bottom; $line++) {
				for ($col = $left; $col < $right; $col++) {
					$char = NULL;

					if ($col == $left && $line == $top ) { $char = $b_tl_corner; }
					else if ($col == $right - 1 && $line == $top ) { $char = $b_tr_corner; }
					else if ($col == $left && $line == $bottom - 1 ) { $char = $b_bl_corner; }
					else if ($col == $right - 1 && $line == $bottom - 1 ) { $char = $b_br_corner; }
					else if ($col == $left) { $char = $b_left; }
					else if ($col == $right - 1) { $char = $b_right; }
					else if ($line == $top) { $char = $b_top; }
					else if ($line == $bottom - 1) { $char = $b_bottom; }

					if ($char != NULL) {
						$this->setBufferChar($col, $line, $char);
					}

					// Skip to right for middle-rows
					if ($col == $left && $line != $top && $line != $bottom - 1) {
						// $right - 2 makes sure that the for loop is hit.
						$col = $right - 2;
					}
				}
			}

			$this->setCursorX(0);
			$this->setCursorY(0);
		}
	}

	/**
	 * Class to deal with the screen as a whole.
	 *
	 * This is also technically a NonCursesWindow to make things easier.
	 */
	class NonCursesScreen extends NonCursesWindow {
		/** stty settings saved from startup to restore. */
		private $stty = '';

		/** Singleton instance of main screen. */
		private static $me;

		/** Are we constructing a new me using the get() function? */
		private static $newMe = false;

		/**
		 * Get the instance of the NonCursesScreen window.
		 *
		 * @return Singleton instance of main screen
		 */
		public static function get() {
			if (is_null(self::$me)) {
				self::$newMe = true;
				self::$me = new self();
				self::$newMe = false;
			}

			return self::$me;
		}

		/**
		 * Create the NonCursesScreen.
		 *
		 * This should never be called directly.
		 */
		public function __construct() {
			if (!is_null(self::$me)) { throw new NonCursesException('Tried to create duplicate NonCursesScreen.'); }
			if (!self::$newMe) { throw new NonCursesException('Tried to create NonCursesScreen manually.'); }

			// Test that tput works, will throw NonCursesException if not.
			$this->tput("lines");
			$this->tput("cols");

			// Look for stty.
			if (!file_exists('/bin/stty')) { throw new NonCursesException('Unable to locate stty.'); }

			$this->topleft = [0, 0];
		}

		/**
		 * Destroy the main screen.
		 *
		 * This should never be called.
		 */
		public function destroy() { throw new NonCursesException('Tried to destroy main screen.'); }

		/**
		 * Get a value from tput
		 *
		 * @param $var Var to get value of
		 * @return Var from tput
		 */
		private function tput($var) {
			$out = []; $return = 0;
			@exec('tput ' . escapeshellarg($var), $out, $return);

			if ($return === 0) { return implode("\n", $out); }
			else { throw new NonCursesException('Unable to get values from tput.'); }
		}

		/** Get how many columns wide this screen is. */
		public function getCols() {
			$result = $this->tput('cols');
			if ($result != $this->cols) {
				$this->cols = $result;
				$this->resetBuffer(false);
			}

			return $result;
		}

		/** Get how many lines tall this window is. */
		public function getLines() {
			$result = $this->tput('lines');
			if ($result != $this->lines) {
				$this->lines = $result;
				$this->resetBuffer(false);
			}

			return $result;
		}

		/** Get our absolute X position on the screen. */
		public function getAbsX() { return $this->topleft[0]; }

		/** Get our absolute Y position on the screen. */
		public function getAbsY() { return $this->topleft[1]; }

		/** We can't setCols on the screen. */
		public function setCols($cols) { throw new NonCursesException('Tried to setCols on main screen.'); }

		/** We can't setLines on the screen. */
		public function setLines($lines) { throw new NonCursesException('Tried to setLines on main screen.'); }

		/** We can't setRelX on the screen. */
		public function setRelX($relX) { throw new NonCursesException('Tried to setRelX on main screen.'); }

		/** We can't setRelY on the screen. */
		public function setRelY($relY) { throw new NonCursesException('Tried to setRelY on main screen.'); }


		/**
		 * Initialise the screen ready for use.
		 */
		public function init() {
			$this->smcup();
			$this->clear();
			$this->wrapOff();
			$this->stty = exec('/bin/stty -g');
			exec('/bin/stty min 0 time 0');
			echo "\033[39m";
		}

		/**
		 * Uninitialise the screen following use.
		 */
		public function deinit() {
			system('/bin/stty ' . escapeshellarg($this->stty));
			$this->wrapOn();
			$this->echoOn();
			$this->icanonOn();
			$this->showCursor();
			$this->rmcup();
		}

		/** Move to "alernate" screen. */
		public function smcup() { echo "\033[?47h"; }

		/** Return from "alernate" screen. */
		public function rmcup() { echo "\033[?47l"; }

		/** Clear the whole screen. */
		public function clear() { echo "\033[2J"; $this->moveCursor(0, 0); }

		/**
		 * Move the drawing cursor position.
		 *
		 * We are 0-based, but the terminal is 1-based, so + 1s to everything.
		 *
		 * @param $line Line to move to.
		 * @param $col Column to move to.
		 */
		public function moveCursor($line, $col) { echo "\033[" . ($line + 1) . ';' . ($col + 1). 'H'; }

		/**
		 * Move the cursor up.
		 *
		 * @param $count How many lines to move cursor up.
		 */
		public function cursorUp($count = 1) { echo "\033[" . $count . 'A'; }

		/**
		 * Move the cursor down.
		 *
		 * @param $count How many lines to move cursor down.
		 */
		public function cursorDown($count = 1) { echo "\033[" . $count . 'B'; }

		/**
		 * Move the cursor right.
		 *
		 * @param $count How many lines to move cursor right.
		 */
		public function cursorRight($count = 1) { echo "\033[" . $count . 'C'; }

		/**
		 * Move the cursor left.
		 *
		 * @param $count How many lines to move cursor left.
		 */
		public function cursorLeft($count = 1) { echo "\033[" . $count . 'D'; }

		/** Turn on automatic line wrap. */
		public function wrapOn() { echo "\033[?7h"; }

		/** Turn off automatic line wrap. */
		public function wrapOff() { echo "\033[?7l"; }

		/** Hide the cursor. */
		public function hideCursor() { echo "\033[?25l"; }

		/** Show the cursor. */
		public function showCursor() { echo "\033[?25h"; }

		/** Disable local-echo of input. */
		public function echoOff() { system('stty -echo'); }

		/** Enable local-echo of input. */
		public function echoOn() { system('stty echo'); }

		/** Disable icanon input mode. */
		public function icanonOff() { system('stty -icanon'); }

		/** Enable icanon input mode. */
		public function icanonOn() { system('stty icanon'); }
	}

	// Define some NCURSES Constants
	if (!function_exists('ncurses_init')) {
		define('NONCURSES_USED', true);

		define('STDSCR', null);
		define('ACS_ULCORNER', '┌');
		define('ACS_LLCORNER', '└');
		define('ACS_URCORNER', '┐');
		define('ACS_LRCORNER', '┘');
		define('ACS_LTEE', '├');
		define('ACS_RTEE', '┤');
		define('ACS_BTEE', '┴');
		define('ACS_TTEE', '┬');
		define('ACS_HLINE', '─');
		define('ACS_VLINE', '│');
		define('ACS_PLUS', '┼');
		define('ACS_S1', '1');
		define('ACS_S9', '9');
		define('ACS_DIAMOND', '◆');
		define('ACS_CKBOARD', '▒');
		define('ACS_DEGREE', '°');
		define('ACS_PLMINUS', '±');
		define('ACS_BULLET', '·');
		define('ACS_LARROW', '←');
		define('ACS_RARROW', '→');
		define('ACS_DARROW', '↓');
		define('ACS_UARROW', '↑');
		define('ACS_BOARD', '▒');
		define('ACS_LANTERN', '␋');
		define('ACS_BLOCK', '▮');

		/**
		 * Initializes the ncurses interface.
		 *
		 * This function must be used before any other ncurses function call.
		 */
		function ncurses_init() {
			NonCursesScreen::get()->init();
			return true;
		}

		/**
		 * Stop using ncurses, clean up the screen
		 */
		function ncurses_end() {
			NonCursesScreen::get()->deinit();
			return true;
		}

		/**
		 * Returns the size of a window
		 *
		 * @param $window The measured window
		 * @param &$y This will be set to the window height
		 * @param &$x This will be set to the window width
		 */
		function ncurses_getmaxyx($window, &$y, &$x) {
			if ($window == STDSCR) { $window = NonCursesScreen::get(); }

			$y = $window->getLines();
			$x = $window->getCols();
		}

		/**
		 * Create a new window
		 *
		 * @param $lines How many lines tall?
		 * @param $cols How many cols wide?
		 * @param $x AbsX location
		 * @param $y AbsY location
		 * @return New instance of NonCursesWindow
		 */
		function ncurses_newwin($lines, $cols, $y, $x) {
			$window = new NonCursesWindow(NonCursesScreen::get());

			$window->setLines($lines);
			$window->setCols($cols);
			$window->setRelX($x);
			$window->setRelY($y);

			return $window;
		}

		/**
		 * Delete a window.
		 *
		 * @param $window Window to delete
		 */
		function ncurses_delwin($window) {
			if ($window == STDSCR) { throw new NonCursesException("delwin called on main screen."); }

			$window->destroy();
		}

		/**
		 * Set cursor state
		 *
		 * @param $visibility 0 for invisible, anything else for visible.
		 */
		function ncurses_curs_set($visibility) {
			if ($visibility == 0) {
				NonCursesScreen::get()->hideCursor();
			} else {
				NonCursesScreen::get()->showCursor();
			}
		}

		/**
		 * Switch off input buffering
		 */
		function ncurses_cbreak() { NonCursesScreen::get()->icanonOff(); }

		/**
		 * Switch terminal to cooked mode
		 */
		function ncurses_nocbreak() { NonCursesScreen::get()->icanonOn(); }

		/**
		 * Switch off keyboard input echo
		 */
		function ncurses_noecho() { NonCursesScreen::get()->echoOff(); }

		/**
		 * Activate keyboard input echo
		 */
		function ncurses_echo() { NonCursesScreen::get()->echoOn(); }

		/**
		 * Refresh screen
		 */
		function ncurses_refresh() { NonCursesScreen::get()->draw(); }

		/**
		 * Clear screen
		 */
		function ncurses_clear() { NonCursesScreen::get()->clear(); }

		/**
		 * Refresh window on terminal screen
		 *
		 * @param $window Window to refresh
		 */
		function ncurses_wrefresh($window) {
			if ($window == STDSCR) { throw new NonCursesException("wrefresh called on main screen."); }

			$window->draw();
		}

		/**
		 * Clears window
		 *
		 * @param $window Window to clear
		 */
		function ncurses_wclear($window) {
			if ($window == STDSCR) { throw new NonCursesException("wclear called on main screen."); }

			$window->clear();
		}

		/**
		 * Move cursor within the given window.
		 *
		 * @param $window Window to move cursor within.
		 * @param $y New Y location
		 * @param $x New X location
		 */
		function ncurses_wmove($window, $y, $x) {
			if ($window == STDSCR) { throw new NonCursesException("wmove called on main screen."); }

			$window->setCursorY($y);
			$window->setCursorX($x);
		}

		/**
		 * Outputs text at current postion in window
		 *
		 * @param $window Window to add text to
		 * @param $line Text to add
		 */
		function ncurses_waddstr($window, $line) {
			if ($window == STDSCR) { throw new NonCursesException("waddstr called on main screen."); }

			$window->addString($line);
		}

		/**
		 * Draws the specified lines and corners around the passed window.
		 * (Passing 0 will use the default character for that location)
		 *
		 * @param $window The window on which we operate
		 * @param $left Left character
		 * @param $right Right character
		 * @param $top Top characated
		 * @param $bottom Bottom character
		 * @param $tl_corner Top left corner character
		 * @param $tr_corner Top right corner character
		 * @param $bl_corner Bottom left corner character
		 * @param $br_corner Bottom right corner character
		 */
		function ncurses_wborder($window, $left, $right, $top, $bottom, $tl_corner, $tr_corner, $bl_corner, $br_corner) {
			if ($window == STDSCR) { throw new NonCursesException("wborder called on main screen."); }

			$window->drawBorder($left, $right, $top, $bottom, $tl_corner, $tr_corner, $bl_corner, $br_corner);
		}

		/**
		 * Add string at new position in window
		 *
		 * @param $window Window to add text to
		 * @param $y Relative Y location to add text
	 	 * @param $x Relative X location to add text
		 * @param $text Text to add
		 */
		function ncurses_mvwaddstr($window, $y, $x, $text) {
			if ($window == STDSCR) { throw new NonCursesException("mvwaddstr called on main screen."); }

			$window->addString($text, $x, $y);
		}

		/**
		 * Read a character from keyboard
		 *
		 * @return Character from keyboard as an int
		 */
		function ncurses_getch() {
			while (true) {
				usleep(1000);
				$in = fread(STDIN, 10);
				// stty ensures we only get 1 key-press at a time. Some keys
				// generate escape sequences, that we recieve ALL AT ONCE, so
				// we can happily disregard them! :D
				if (strlen($in) == 1) {
					// ncurses lib appears to return 263 for backspace, so we should also.
					return (ord($in) == '127') ? 263 : ord($in);;
				}
			}
		}

		/**
		 * NCURSES Constants that we do not use, to complete the polyfil.
		 */

		define('NCURSES_COLOR_BLACK', 0);
		define('NCURSES_COLOR_WHITE', 7);
		define('NCURSES_COLOR_RED', 1);
		define('NCURSES_COLOR_GREEN', 2);
		define('NCURSES_COLOR_YELLOW', 3);
		define('NCURSES_COLOR_BLUE', 4);
		define('NCURSES_COLOR_CYAN', 6);
		define('NCURSES_COLOR_MAGENTA', 5);

		define('NCURSES_A_ALTCHARSET', 4194304);
		define('NCURSES_A_ATTRIBUTES', -256);
		define('NCURSES_A_BLINK', 524288);
		define('NCURSES_A_BOLD', 2097152);
		define('NCURSES_A_CHARTEXT', 255);
		define('NCURSES_A_COLOR', 65280);
		define('NCURSES_A_DIM', 1048576);
		define('NCURSES_A_HORIZONTAL', 33554432);
		define('NCURSES_A_INVIS', 8388608);
		define('NCURSES_A_LEFT', 67108864);
		define('NCURSES_A_LOW', 134217728);
		define('NCURSES_A_NORMAL', 0);
		define('NCURSES_A_PROTECT', 16777216);
		define('NCURSES_A_REVERSE', 262144);
		define('NCURSES_A_RIGHT', 268435456);
		define('NCURSES_A_STANDOUT', 65536);
		define('NCURSES_A_TOP', 536870912);
		define('NCURSES_A_UNDERLINE', 131072);
		define('NCURSES_A_VERTICAL', 1073741824);

		define('NCURSES_KEY_A1', 348);
		define('NCURSES_KEY_A3', 349);
		define('NCURSES_KEY_B2', 350);
		define('NCURSES_KEY_BACKSPACE', 263);
		define('NCURSES_KEY_BEG', 354);
		define('NCURSES_KEY_BREAK', 257);
		define('NCURSES_KEY_BTAB', 353);
		define('NCURSES_KEY_C1', 351);
		define('NCURSES_KEY_C3', 352);
		define('NCURSES_KEY_CANCEL', 355);
		define('NCURSES_KEY_CATAB', 342);
		define('NCURSES_KEY_CLEAR', 333);
		define('NCURSES_KEY_CLOSE', 356);
		define('NCURSES_KEY_COMMAND', 357);
		define('NCURSES_KEY_COPY', 358);
		define('NCURSES_KEY_CREATE', 359);
		define('NCURSES_KEY_CTAB', 341);
		define('NCURSES_KEY_DC', 330);
		define('NCURSES_KEY_DL', 328);
		define('NCURSES_KEY_DOWN', 258);
		define('NCURSES_KEY_EIC', 332);
		define('NCURSES_KEY_END', 360);
		define('NCURSES_KEY_ENTER', 343);
		define('NCURSES_KEY_EOL', 335);
		define('NCURSES_KEY_EOS', 334);
		define('NCURSES_KEY_EXIT', 361);
		define('NCURSES_KEY_F0', 264);
		define('NCURSES_KEY_F1', 265);
		define('NCURSES_KEY_F10', 274);
		define('NCURSES_KEY_F11', 275);
		define('NCURSES_KEY_F12', 276);
		define('NCURSES_KEY_F13', 277);
		define('NCURSES_KEY_F14', 278);
		define('NCURSES_KEY_F15', 279);
		define('NCURSES_KEY_F16', 280);
		define('NCURSES_KEY_F17', 281);
		define('NCURSES_KEY_F18', 282);
		define('NCURSES_KEY_F19', 283);
		define('NCURSES_KEY_F2', 266);
		define('NCURSES_KEY_F20', 284);
		define('NCURSES_KEY_F21', 285);
		define('NCURSES_KEY_F22', 286);
		define('NCURSES_KEY_F23', 287);
		define('NCURSES_KEY_F24', 288);
		define('NCURSES_KEY_F25', 289);
		define('NCURSES_KEY_F26', 290);
		define('NCURSES_KEY_F27', 291);
		define('NCURSES_KEY_F28', 292);
		define('NCURSES_KEY_F29', 293);
		define('NCURSES_KEY_F3', 267);
		define('NCURSES_KEY_F30', 294);
		define('NCURSES_KEY_F31', 295);
		define('NCURSES_KEY_F32', 296);
		define('NCURSES_KEY_F33', 297);
		define('NCURSES_KEY_F34', 298);
		define('NCURSES_KEY_F35', 299);
		define('NCURSES_KEY_F36', 300);
		define('NCURSES_KEY_F37', 301);
		define('NCURSES_KEY_F38', 302);
		define('NCURSES_KEY_F39', 303);
		define('NCURSES_KEY_F4', 268);
		define('NCURSES_KEY_F40', 304);
		define('NCURSES_KEY_F41', 305);
		define('NCURSES_KEY_F42', 306);
		define('NCURSES_KEY_F43', 307);
		define('NCURSES_KEY_F44', 308);
		define('NCURSES_KEY_F45', 309);
		define('NCURSES_KEY_F46', 310);
		define('NCURSES_KEY_F47', 311);
		define('NCURSES_KEY_F48', 312);
		define('NCURSES_KEY_F49', 313);
		define('NCURSES_KEY_F5', 269);
		define('NCURSES_KEY_F50', 314);
		define('NCURSES_KEY_F51', 315);
		define('NCURSES_KEY_F52', 316);
		define('NCURSES_KEY_F53', 317);
		define('NCURSES_KEY_F54', 318);
		define('NCURSES_KEY_F55', 319);
		define('NCURSES_KEY_F56', 320);
		define('NCURSES_KEY_F57', 321);
		define('NCURSES_KEY_F58', 322);
		define('NCURSES_KEY_F59', 323);
		define('NCURSES_KEY_F6', 270);
		define('NCURSES_KEY_F60', 324);
		define('NCURSES_KEY_F61', 325);
		define('NCURSES_KEY_F62', 326);
		define('NCURSES_KEY_F63', 327);
		define('NCURSES_KEY_F7', 271);
		define('NCURSES_KEY_F8', 272);
		define('NCURSES_KEY_F9', 273);
		define('NCURSES_KEY_FIND', 362);
		define('NCURSES_KEY_HELP', 363);
		define('NCURSES_KEY_HOME', 262);
		define('NCURSES_KEY_IC', 331);
		define('NCURSES_KEY_IL', 329);
		define('NCURSES_KEY_LEFT', 260);
		define('NCURSES_KEY_LL', 347);
		define('NCURSES_KEY_MARK', 364);
		define('NCURSES_KEY_MAX', 511);
		define('NCURSES_KEY_MESSAGE', 365);
		define('NCURSES_KEY_MIN', 257);
		define('NCURSES_KEY_MOUSE', 409);
		define('NCURSES_KEY_MOVE', 366);
		define('NCURSES_KEY_NEXT', 367);
		define('NCURSES_KEY_NPAGE', 338);
		define('NCURSES_KEY_OPEN', 368);
		define('NCURSES_KEY_OPTIONS', 369);
		define('NCURSES_KEY_PPAGE', 339);
		define('NCURSES_KEY_PREVIOUS', 370);
		define('NCURSES_KEY_PRINT', 346);
		define('NCURSES_KEY_REDO', 371);
		define('NCURSES_KEY_REFERENCE', 372);
		define('NCURSES_KEY_REFRESH', 373);
		define('NCURSES_KEY_REPLACE', 374);
		define('NCURSES_KEY_RESET', 345);
		define('NCURSES_KEY_RESIZE', 410);
		define('NCURSES_KEY_RESTART', 375);
		define('NCURSES_KEY_RESUME', 376);
		define('NCURSES_KEY_RIGHT', 261);
		define('NCURSES_KEY_SAVE', 377);
		define('NCURSES_KEY_SBEG', 378);
		define('NCURSES_KEY_SCANCEL', 379);
		define('NCURSES_KEY_SCOMMAND', 380);
		define('NCURSES_KEY_SCOPY', 381);
		define('NCURSES_KEY_SCREATE', 382);
		define('NCURSES_KEY_SDC', 383);
		define('NCURSES_KEY_SDL', 384);
		define('NCURSES_KEY_SELECT', 385);
		define('NCURSES_KEY_SEND', 386);
		define('NCURSES_KEY_SEOL', 387);
		define('NCURSES_KEY_SEXIT', 388);
		define('NCURSES_KEY_SF', 336);
		define('NCURSES_KEY_SFIND', 389);
		define('NCURSES_KEY_SHELP', 390);
		define('NCURSES_KEY_SHOME', 391);
		define('NCURSES_KEY_SIC', 392);
		define('NCURSES_KEY_SLEFT', 393);
		define('NCURSES_KEY_SMESSAGE', 394);
		define('NCURSES_KEY_SMOVE', 395);
		define('NCURSES_KEY_SNEXT', 396);
		define('NCURSES_KEY_SOPTIONS', 397);
		define('NCURSES_KEY_SPREVIOUS', 398);
		define('NCURSES_KEY_SPRINT', 399);
		define('NCURSES_KEY_SR', 337);
		define('NCURSES_KEY_SREDO', 400);
		define('NCURSES_KEY_SREPLACE', 401);
		define('NCURSES_KEY_SRESET', 344);
		define('NCURSES_KEY_SRIGHT', 402);
		define('NCURSES_KEY_SRSUME', 403);
		define('NCURSES_KEY_SSAVE', 404);
		define('NCURSES_KEY_SSUSPEND', 405);
		define('NCURSES_KEY_STAB', 340);
		define('NCURSES_KEY_SUNDO', 406);
		define('NCURSES_KEY_SUSPEND', 407);
		define('NCURSES_KEY_UNDO', 408);
		define('NCURSES_KEY_UP', 259);

		define('NCURSES_BUTTON1_RELEASED', 1);
		define('NCURSES_BUTTON1_PRESSED', 2);
		define('NCURSES_BUTTON1_CLICKED', 4);
		define('NCURSES_BUTTON1_DOUBLE_CLICKED', 8);
		define('NCURSES_BUTTON1_TRIPLE_CLICKED', 16);
		define('NCURSES_BUTTON2_RELEASED', 64);
		define('NCURSES_BUTTON2_PRESSED', 128);
		define('NCURSES_BUTTON2_CLICKED', 256);
		define('NCURSES_BUTTON2_DOUBLE_CLICKED', 512);
		define('NCURSES_BUTTON2_TRIPLE_CLICKED', 1024);
		define('NCURSES_BUTTON3_RELEASED', 4096);
		define('NCURSES_BUTTON3_PRESSED', 8192);
		define('NCURSES_BUTTON3_CLICKED', 16384);
		define('NCURSES_BUTTON3_DOUBLE_CLICKED', 32768);
		define('NCURSES_BUTTON3_TRIPLE_CLICKED', 65536);
		define('NCURSES_BUTTON4_RELEASED', 262144);
		define('NCURSES_BUTTON4_PRESSED', 524288);
		define('NCURSES_BUTTON4_CLICKED', 1048576);
		define('NCURSES_BUTTON4_DOUBLE_CLICKED', 2097152);
		define('NCURSES_BUTTON4_TRIPLE_CLICKED', 4194304);
		define('NCURSES_BUTTON_CTRL', 16777216);
		define('NCURSES_BUTTON_SHIFT', 33554432);
		define('NCURSES_BUTTON_ALT', 67108864);
		define('NCURSES_ALL_MOUSE_EVENTS', 134217727);
		define('NCURSES_REPORT_MOUSE_POSITION', 134217728);

		/**
		 * To ensure complete API Compatability (if not functionality) below are all the
		 * currently unimplemented methods to complete the polyfil.
		 */

		function ncurses_addch() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_addchnstr() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_addchstr() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_addnstr() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_addstr() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_assume_default_colors() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_attroff() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_attron() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_attrset() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_baudrate() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_beep() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_bkgd() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_bkgdset() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_border() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_bottom_panel() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_can_change_color() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_clrtobot() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_clrtoeol() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_color_content() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_color_set() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_def_prog_mode() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_def_shell_mode() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_define_key() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_del_panel() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_delay_output() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_delch() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_deleteln() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_doupdate() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_echochar() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_erase() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_erasechar() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_filter() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_flash() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_flushinp() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_getmouse() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_getyx() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_halfdelay() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_has_colors() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_has_ic() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_has_il() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_has_key() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_hide_panel() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_hline() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_inch() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_init_color() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_init_pair() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_insch() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_insdelln() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_insertln() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_insstr() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_instr() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_isendwin() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_keyok() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_keypad() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_killchar() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_longname() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_meta() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_mouse_trafo() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_mouseinterval() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_mousemask() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_move_panel() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_move() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_mvaddch() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_mvaddchnstr() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_mvaddchstr() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_mvaddnstr() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_mvaddstr() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_mvcur() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_mvdelch() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_mvgetch() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_mvhline() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_mvinch() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_mvvline() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_napms() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_new_panel() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_newpad() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_nl() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_nonl() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_noqiflush() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_noraw() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_pair_content() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_panel_above() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_panel_below() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_panel_window() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_pnoutrefresh() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_prefresh() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_putp() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_qiflush() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_raw() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_replace_panel() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_reset_prog_mode() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_reset_shell_mode() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_resetty() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_savetty() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_scr_dump() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_scr_init() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_scr_restore() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_scr_set() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_scrl() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_show_panel() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_slk_attr() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_slk_attroff() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_slk_attron() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_slk_attrset() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_slk_clear() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_slk_color() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_slk_init() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_slk_noutrefresh() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_slk_refresh() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_slk_restore() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_slk_set() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_slk_touch() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_standend() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_standout() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_start_color() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_termattrs() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_termname() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_timeout() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_top_panel() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_typeahead() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_ungetch() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_ungetmouse() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_update_panels() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_use_default_colors() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_use_env() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_use_extended_names() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_vidattr() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_vline() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_waddch() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_wattroff() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_wattron() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_wattrset() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_wcolor_set() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_werase() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_wgetch() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_whline() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_wmouse_trafo() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_wnoutrefresh() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_wstandend() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_wstandout() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
		function ncurses_wvline() { if (!defined('NONCURSES_SILENT')) { throw new NonCursesException('Not implemented yet.'); } }
	}
