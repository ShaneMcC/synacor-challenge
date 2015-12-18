<?php
	// Load all operations.
	require_once(dirname(__FILE__) . '/operations/SynacorOP.php');
    foreach (glob(dirname(__FILE__) . '/operations/*.php') as $class) { require_once($class); }

    // Allow memory usage.
    ini_set('memory_limit', '-1');

	/**
	 * Class implementing the Synacor VM.
	 */
	class SynacorVM {
		/** Memory Data. */
		private $data = [];

		/** Current location in memory. */
		private $location = 0;

		/** Register Values. */
		private $reg;

		/** Stack. */
		private $stack = [];

		/** Known Operations */
		private $ops = [];

		/** Run return value */
		private $returnValue = 0;

		/** I/O handlers. */
		private $handlers = array();

		/** Break Points */
		private $breakPoints = array();

		/**
		 * Create a new Synacor VM to run the given binary data.
		 *
		 * @param $binaryData Program data.
		 */
		public function __construct($binaryData) {
			// Load the operations.
			foreach (get_declared_classes() as $class) {
				if (is_subclass_of($class, 'SynacorOP')) {
					$c = new $class();
					$this->ops[($class == 'Synacor_none') ? 'NONE' : $c->code()] = $c;
				}
			}

			// Prepare default functions.
			$this->handlers['output'] = function ($vm, $out) { echo chr($out); };
			$this->handlers['input'] = function ($vm) { return ord(fread(STDIN, 1)); };
			$this->handlers['trace'] = function ($vm, $loc, $op, $data) { };

			// Load the data.
			$this->loadbin($binaryData);
		}

		/**
		 * Load the VM with the the given binary data.
		 *
		 * @param $binaryData Program data.
		 */
		public function loadbin($binaryData) {
			// Load the application into memory.
			$this->data = array_values(unpack('v*', $binaryData));

			// Prepare data structures
			$this->reg = new SplFixedArray(8);
			for ($i=0; $i < 8; $i++) { $this->reg[$i] = 0; }

			// Init state.
			$this->stack = array();
			$this->breakPoints = array();
			$this->location = 0;
			$this->returnValue = 0;
		}

		/**
		 * Set the I/O handlers to the given functions.
		 * Only the functions given are changed, this does not remove any
		 * functions not passed in the array.
		 *
		 * @param $handlers Array of handlers.
		 */
		public function setHandlers($handlers) {
			foreach ($handlers as $type => $handler) {
				$this->handlers[$type] = $handler;
			}
		}

		/**
		 * Get the current I/O handlers.
		 *
		 * @return Array of handlers.
		 */
		public function getHandlers() {
			return $this->handlers[$type];
		}

		/**
		 * Get the known operations.
		 *
		 * @return Array of known operations.
		 */
		public function getOps() {
			return $this->ops;
		}

		/**
		 * Get the op code for the op of the given name.
		 *
		 * @param $name Name of op
		 * @return Code.
		 */
		public function getOpCode($name) {
			foreach ($this->ops as $o) {
				if ($o->name() == $name) {
					return $o->code();
				}
			}

			return $this->ops['NONE'];
		}

		/**
		 * Call the output function.
		 *
		 * @param $output Output to pass to function.
		 */
		function output($output) {
			$this->handlers['output']($this, $output);
		}

		/**
		 * Call the input function.
		 *
		 * @param $input Input to pass to function.
		 */
		function input() {
			return $this->handlers['input']($this);
		}

		/**
		 * Add a break point
		 *
		 * @param $break Break Point
		 */
		public function addBreak($break) {
			$this->breakPoints[$break] = true;
		}

		/**
		 * Remove a break point
		 *
		 * @param $break Break Point
		 */
		public function delBreak($break) {
			unset($this->breakPoints[$break]);
		}

		/**
		 * Clear all break points
		 */
		public function clearBreak() {
			$this->breakPoints = array();
		}

		/**
		 * Run the Program.
		 *
		 * @return Return code from app (1 or 0)
		 */
		public function run() {
			while ($this->step()) { }

			return $this->returnValue;
		}


		/**
		 * Step through $count calls.
		 *
		 * @param $count How far to step.
		 */
		public function step($count = 1) {
			$ignoreBreak = false;
			if ($count == 0) {
				$count = 1;
				$ignoreBreak = true;
			}
			for ($steps = 0; $steps < $count; $steps++) {
				$loc = $this->getLocation();
				$op = $this->getNext(1);
				if ($op === false) { return false; }
				$op = $op[0];

				if (!isset($this->ops[$op])) {
					$this->haltvm('BAD OP: ' . $op);
					return false;
				} else {
					$data = $this->getNext($this->ops[$op]->args());
					$breaking = (!$ignoreBreak && isset($this->breakPoints[$loc]));
					$this->handlers['trace']($this, $loc, $this->ops[$op], $data, $breaking);
					if ($breaking) {
						$result = FALSE;
					} else {
						$result = $this->ops[$op]->run($this, $data);
					}

					// INPUT can return false to let us hand back to
					// the display, or we could have hit a break point that we
					// weren't skipping over.
					if ($result === FALSE) {
						$this->jump($loc);
						return FALSE;
					}
				}
			}

			return true;
		}

		/**
		 * Dump memory from $start to $end through the dump handler as a series
		 * of commands (without executing them)
		 *
		 * @param $start Where to start.
		 * @param $end Where to end (if $loc is greater/equal than this, and end is not 0)
		 * @param $includeNone Include non-ops in dump?
		 */
		public function dump($start = 0, $end = 0, $includeNone = true) {
			$current = $this->getLocation();
			$this->jump($start);
			if ($end != 0 && $end == $start) { $end++; }
			while (true) {
				$loc = $this->getLocation();
				if ($end != 0 && $loc >= $end) { break; }
				$op = $this->getNext(1);
				if ($op === false) { break; }
				$op = $op[0];

				if (isset($this->ops[$op])) {
					$data = $this->getNext($this->ops[$op]->args());
				} else if ($includeNone) {
					$data = array($op);
					$op = 'NONE';
				} else {
					continue;
				}

				$this->handlers['dump']($this, $loc, $this->ops[$op], $data);
			}
			$this->jump($current);

			return true;
		}

		/**
		 * Get the next data from memory.
		 *
		 * @param $count How many bits of data do we want?
		 * @return Array containing the requested data, or FALSE if there is
		 *         no more.
		 */
		private function getNext($count = 1) {
			$result = array();

			for ($i = 0; $i < $count; $i++) {
				if ($this->location == -1) { return FALSE; }
				$val = isset($this->data[$this->location]) ? $this->data[$this->location] : FALSE;

				$result[] = $val;
				$this->location++;
			}

			return empty($result) ? FALSE : $result;
		}

		public function decode(&$input) {
			if ($this->isRegister($input)) {
				$this->asRegister($input);
				$input = $this->get($input);
			}
		}

		public function asRegister(&$input) {
			if ($this->isRegister($input)) {
				$input = $input - 32768;
			} else {
				$this->haltvm('Not a register.');
			}
		}

		public function toRegister(&$input) {
			if (isset($this->reg[$input])) {
				$input = $input + 32768;
			} else {
				$this->haltvm('Not a register.');
			}
		}

		public function getRegLocation($input) {
			$this->toRegister($input);
			return $input;
		}

		public function getRegID($input) {
			$this->asRegister($input);
			return $input;
		}

		public function isRegister($input) {
			return ($input >= 32768 && $input < 32776);
		}

		/**
		 * Push an item onto the stack.
		 *
		 * @param $item Item to put on the stack
		 * @return The item added.
		 */
		public function push($item) {
			$this->stack[] = $item;
			return $item;
		}

		/**
		 * Pop an item from the stack.
		 *
		 * @return Top item from the stack.
		 */
		public function pop() {
			$item = array_pop($this->stack);
			return $item;
		}

		/**
		 * Jump to a given location.
		 *
		 * @param $loc Location to jump to.
		 */
		public function jump($loc) {
			$this->location = $loc;
		}

		/**
		 * Set the register to the given value.
		 *
		 * @param $reg Register address
		 * @param $val Value to set.
		 */
		public function set($reg, $val) {
			$this->reg[$reg] = $val;
		}

		/**
		 * Get the register at the given value.
		 *
		 * @param $reg Register address
		 * @return Register value.
		 */
		public function get($reg) {
			return (int)$this->reg[$reg];
		}

		/**
		 * Get the stack.
		 *
		 * @return The stack.
		 */
		public function getStack() {
			return $this->stack;
		}

		/**
		 * Clear the stack.
		 */
		public function clearStack() {
			return $this->stack = array();
		}

		/**
		 * Set the memory at the given location to the given value.
		 *
		 * @param $loc Location to change.
		 * @param $val Value to set.
		 */
		public function setData($loc, $val) {
			$this->data[$loc] = $val;
		}

		/**
		 * Get the memory at the given location
		 *
		 * @param $loc Location to change.
		 * @return Value of Location.
		 */
		public function getData($loc) {
			return (int)$this->data[$loc];
		}

		/**
		 * Get the size of memory data.
		 *
		 * @return Size of memory data.
		 */
		public function getSize() {
			return count($this->data);
		}

		/**
		 * Get the current memory location.
		 *
		 * @return Current memory location.
		 */
		public function getLocation() {
			return (int)$this->location;
		}

		/**
		 * Get the length of the instructions.
		 *
		 * @return Length of instructions.
		 */
		public function getLength() {
			return (int)count($this->data);
		}

		/**
		 * Save the current state of the VM.
		 *
		 * @param $file File to save to (if '' then a name will be generated.)
		 */
		public function saveState($file = '') {
			$state = array();
			$state['memory'] = $this->data;
			$state['location'] = $this->location;
			$state['registers'] = $this->reg;
			$state['stack'] = $this->stack;
			$state['return'] = $this->returnValue;

			if (empty($file)) {
				$file = 'savestate.' . time();
			}
			file_put_contents($file, serialize($state));
		}

		/**
		 * Load a previous VM state.
		 *
		 * @param $file File to load from.
		 */
		public function loadState($file) {
			$state = unserialize(file_get_contents($file));

			$this->data = $state['memory'];
			$this->location = $state['location'];
			$this->reg = $state['registers'];
			$this->stack = $state['stack'];
			$this->returnValue = $state['return'];
		}

		/**
		 * Halt the VM.
		 *
		 * @param $reason Text reason for halting.
		 * @param $code Exit Code (default: 1)
		 */
		public function haltvm($reason, $code = 1) {
			$message = '#### EXITED: ' . $reason . "\n";
			foreach (str_split($message) as $m) {
				$this->handlers['output']($this, ord($m));
			}
			$this->returnValue = $code;
			$this->jump(-1);
		}
	}
