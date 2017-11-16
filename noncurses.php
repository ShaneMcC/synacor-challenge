<?php
	declare(ticks = 1);

	/**
	 * Implement required ncurses functions for VMOutput_ncurses when not present.
	 *
	 * This does not use the ncurses library at all, and is not a direct
	 * implementation of ncurses. It only implements enough to make the
	 * VMOutput_ncurses module work when ncurses is not installed.
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
			if ($overwrite) {
				$this->buffer = [];
			}

			for ($y = 0; $y < max($this->lines, count($this->buffer)); $y++) {
				if ($y >= $this->lines) {
					unset($this->buffer[$y]);
					continue;
				}

				if ($overwrite || !isset($this->buffer[$y])) {
					$this->buffer[$y] = [];
				}

				for ($x = 0; $x < max($this->cols, count($this->buffer[$y])); $x++) {
					if ($x >= $this->cols) {
						unset($this->buffer[$y][$x]);
						continue;
					}

					if ($overwrite || !isset($this->buffer[$y][$x])) {
						$this->buffer[$y][$x] = $character;
					}
				}
			}
		}

		private function setBufferChar($x, $y, $char) {
			if (isset($this->buffer[$y][$x])) {
				$this->buffer[$y][$x] = $char;
			}
		}

		private function getBufferChar($x, $y) {
			if (isset($this->buffer[$y][$x])) {
				return $this->buffer[$y][$x];
			} else {
				// throw new NonCursesException('Unknown buffer char: (' . $x . ', ' . $y . ')');
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
			if ($this->destroyed) { return; }
			list($top, $left, $bottom, $right) = $this->getBounds();

			// Draw the window
			for ($line = $top; $line < $bottom; $line++) {
				for ($col = $left; $col < $right; $col++) {
					$output = $clear ? ' ' : $this->getBufferChar($col - $left, $line - $top);

					if ($this->parent == null) {
						NonCursesScreen::get()->moveCursor($line, $col);
						echo $output;
					} else {
						// TODO: This probably shouldn't need to be so horrible
						//       Need to go through all the code though to make
						//       sure everything is treated as 1-index not 0-index :(
						$this->parent->setBufferChar($col - $this->parent->getRelX(), $line - $this->parent->getRelY(), $output);
					}
				}
			}
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

			$this->topleft = [1, 1];
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
		 * @param $line Line to move to.
		 * @param $col Column to move to.
		 */
		public function moveCursor($line, $col) { echo "\033[" . $line . ';' . $col . 'H'; }

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
		declare(ticks = 1);
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
