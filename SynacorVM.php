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
		private $reg = [0, 0, 0, 0, 0, 0, 0, 0];

		/** Stack. */
		private $stack = [];

		/** Known Operations */
		private $ops = [];

		/** Debug data. */
		private $debug = [];

		/** Debugging enabled? */
		private $debugging = false;

		/** Run return value */
		private $returnValue = 0;

		/**
		 * Create a new Synacor VM to run the given binary data.
		 *
		 * @param $binaryData Program data.
		 */
		public function __construct($binaryData) {
			// Load the application into memory.
			$this->data = array_values(unpack('v*', $binaryData));

			// Load the operations.
			foreach (get_declared_classes() as $class) {
				if (is_subclass_of($class, 'SynacorOP')) {
					$c = new $class();
					$this->ops[$c->code()] = $c;
				}
			}
		}

		/**
		 * Run the Program.
		 *
		 * @param $debugging Output debugging on exit?
		 * @return Return code from app (1 or 0)
		 */
		public function run($debugging = false) {
			$this->debugging = $debugging;

			if ($this->getLocation() != 0) { return -1; }

			while (($op = $this->getNext(1)) !== FALSE) {
				$loc = $this->getLocation();
				$op = $op[0];
				if (!isset($this->ops[$op])) {
					$this->haltvm('BAD OP: ' . $op);
				} else {
					$data = $this->getNext($this->ops[$op]->args());
					$this->debug(sprintf('%d %s (%s)', $loc - 1, get_class($this->ops[$op]), @implode(' ', $data)));
					$this->debug(sprintf('          Before: [%s] {%s}', @implode('|', $this->reg), @implode('<-', $this->stack)));
					$this->ops[$op]->run($this, $data);
					$this->debug(sprintf('           After: [%s] {%s}', @implode('|', $this->reg), @implode('<-', $this->stack)));
					$this->debug();
				}
			}

			return $this->returnValue;
		}

		/**
		 * Store some debug information.
		 *
		 * @param $data Data to store.
		 */
		public function debug($data = '') {
			$this->debug[] = $data;
			echo $data, "\n";
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

		/**
		 * Push an item onto the stack.
		 *
		 * @param $item Item to put on the stack
		 * @return The item added.
		 */
		public function push($item) {
			$this->debug('      pushed: '.$item);
			array_push($this->stack, $item);
			return $item;
		}

		/**
		 * Pop an item from the stack.
		 *
		 * @return Top item from the stack.
		 */
		public function pop() {
			$item = array_pop($this->stack);
			$this->debug('      popped: '.$item);
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
		 * @param $reg Register address (32768 .. 32775)
		 * @param $val Value to set.
		 */
		public function set($reg, $val) {
			if ($reg < 32768) { $this->haltvm('Bad Set'); }
			$this->debug('      Set: '.$reg.' to: '.$val);
			$this->reg[$reg - 32768] = $val;
		}

		/**
		 * Get the register at the given value.
		 * If value is a non-register address, then the raw value will
		 * be returned.
		 *
		 * @param $reg Register address (32768 .. 32775)
		 * @return Register value.
		 */
		public function get($reg) {
			$result = ($reg < 32768) ? $reg : $this->reg[$reg - 32768];
			$this->debug('      Get: '.$reg.' as: '.$result);
			return (int)$result;
		}

		/**
		 * Set the memory at the given location to the given value.
		 *
		 * @param $loc Location to change.
		 * @param $val Value to set.
		 */
		public function setData($loc, $val) {
			$this->debug('      SetData: '.$loc.' as: '.$val);
			$this->data[$loc] = $val;
		}

		/**
		 * Get the memory at the given location
		 *
		 * @param $loc Location to change.
		 * @return Value of Location.
		 */
		public function getData($loc) {
			$this->debug('      getData: '.$loc.' is: '.$this->data[$loc]);
			return (int)$this->data[$loc];
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
		 * Halt the VM.
		 *
		 * @param $reason Text reason for halting.
		 * @param $code Exit Code (default: 1)
		 */
		public function haltvm($reason, $code = 1) {
			echo '#### EXITED: ', $reason, "\n";
			if ($this->debugging) {
				echo implode("\n", $this->debug), "\n";
				echo '#### EXITED: ', $reason, "\n";
			}
			$this->returnValue = $code;
			$this->jump(-1);
		}
	}
