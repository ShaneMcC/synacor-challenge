<?php
	require_once(dirname(__FILE__) . '/SynacorVM.php');
	require_once(dirname(__FILE__) . '/VMOutput_ncurses.php');


	// Parse command line.
	try {
		$__CLIOPTS = @getopt("hr", array('help', 'file:', 'state:', 'input:', 'log:', 'trace:', 'run'));
		if (isset($__CLIOPTS['h']) || isset($__CLIOPTS['help'])) {
			echo 'Usage: ', $_SERVER['argv'][0], ' [options]', "\n";
			echo '', "\n";
			echo 'Valid options', "\n";
			echo '  -h, --help               Show this help output', "\n";
			echo '      --file <file>        Use <file> for the binary file rather than "challenge.bin"', "\n";
			echo '      --state <file>       Preload State from <file>', "\n";
			echo '      --input <file>       Preload Input from <file>', "\n";
			echo '      --log <file>         Log all output to <file>', "\n";
			echo '      --trace <file>       Log all trace to <file>', "\n";
			echo '  -r, --run                Auto run', "\n";
			die();
		}
	} catch (Exception $e) { /* Do nothing. */ }

	// Load in the binary for the challenge
	$binaryFile = isset($__CLIOPTS['file']) && file_exists($__CLIOPTS['file']) ? $__CLIOPTS['file'] : dirname(__FILE__) . '/challenge.bin';
	$binaryData = file_get_contents($binaryFile);

	// Start a VM
	$vm = new SynacorVM($binaryData);

	// Create an output for the given vm.
	$out = new VMOutput_ncurses($vm);

	/**
	 * Handler for user input from the Challenge UI.
	 *
	 * @param $out VMOutput that wanted output.
	 * @param $vm VM that we are running
	 * @param $input Input from user
	 * @return False to abort VM, else true to continue.
	 */
	$outHandlers['gotInput'] = function ($out, $vm, $input) use ($binaryData) {
		global $__LASTINPUT;

		if (substr($input, 0, 1) == '!') {
			$bits = explode(' ', substr($input, 1));

			// ========================================
			// Run Last Command
			// ========================================
			if ($bits[0] == '' || $bits[0] == '!') {
				$out->inputTitle('RUN LAST');
				if (isset($__LASTINPUT) && !empty($__LASTINPUT)) {
					$h = $out->getHandlers();
					$h['gotInput']($out, $vm, $__LASTINPUT);
				}

			// ========================================
			// Run/Trace through the byte code until input is requested.
			// Trace will output all the executed operations any time we output anything
			// TraceAll will output all the executed operations as they are executed.
			// ========================================
			} else if ($bits[0] == 'run' || $bits[0] == 'trace' || $bits[0] == 'traceall') {
				if ($bits[0] != 'trace') { $out->traceOff(); }
				if ($bits[0] == 'traceall') { $out->traceAll(); }
				$out->inputTitle(strtoupper($bits[0]));
				$result = $vm->run();
				$out->inputTitle(strtoupper($bits[0]) . ' [' . $result . ']');
				$out->traceOnOutput();

			// ========================================
			// Halt the VM.
			// This will prevent the VM running any further.
			// ========================================
			} else if ($bits[0] == 'halt') {
				$vm->haltvm('Requested halt');

			// ========================================
			// Reset the VM to the original state we loaded.
			// ========================================
			} else if ($bits[0] == 'reset') {
				$out->inputTitle('RESET');
				$vm->loadbin($binaryData);
				initStateFromCLI($vm, $out);

			// ========================================
			// Load the given binary into the app.
			// ========================================
			} else if ($bits[0] == 'loadbin' && isset($bits[1])) {
				$out->inputTitle('LOADBIN FROM ' . $bits[1]);
				$vm->loadbin(file_get_contents($bits[1]));

			// ========================================
			// Exit from the challenge interface.
			// ========================================
			} else if ($bits[0] == 'exit') {
				$vm->haltvm('Requested exit');
				$out->end();
				return false;

			// ========================================
			// Step forward through the code X steps
			// StepAll shows all the trace steps not just on output.
			// ========================================
			} else if (($bits[0] == 'step' || $bits[0] == 'stepall') && isset($bits[1])) {
				$out->inputTitle('STEP ' . $bits[1]);
				if ($bits[0] == 'stepall') { $out->traceAll(); }
				$vm->step($bits[1]);
				$out->traceOnOutput();

			// ========================================
			// Jump to X
			// ========================================
			} else if ($bits[0] == 'jump' && isset($bits[1])) {
				$out->inputTitle('JUMP ' . $bits[1]);
				$vm->jump($bits[1]);

			// ========================================
			// Add breakpoint at X
			// ========================================
			} else if ($bits[0] == 'break' && isset($bits[1])) {
				$out->inputTitle('BREAK ' . $bits[1]);
				$vm->addBreak($bits[1]);

			// ========================================
			// Remove breakpoint at X
			// ========================================
			} else if ($bits[0] == 'unbreak' && isset($bits[1])) {
				$out->inputTitle('UNBREAK ' . $bits[1]);
				$vm->delBreak($bits[1]);

			// ========================================
			// Clear break points
			// ========================================
			} else if ($bits[0] == 'nobreak') {
				$out->inputTitle('NOBREAK');
				$vm->clearBreak();

			// ========================================
			// Continue after breakpoint.
			// ========================================
			} else if ($bits[0] == 'continue') {
				$out->inputTitle('CONTINUE');
				$vm->step(0);

			// ========================================
			// Get the memory at position X
			// ========================================
			} else if ($bits[0] == 'getmem' && isset($bits[1])) {
				$loc = ($bits[1] == '#') ? $vm->getLocation() : $bits[1];
				if (is_numeric($loc)) {
					$val = $vm->getData($loc);

					$ops = $vm->getOps();
					if (isset($ops[$val])) {
						$val .= ' (' . $ops[$val]->name() . ')';
					}

					$out->inputTitle('GETMEM ' . $loc . ': ' . $val);
				} else {
					$out->inputTitle('INVALID GETMEM');
				}

			// ========================================
			// Dump Memory from X to Y
			// ========================================
			} else if (($bits[0] == 'dump' || $bits[0] == 'dumpall')) {
				$out->traceAll();

				if (!isset($bits[2])) { $bits[1] = $bits[2] = '#'; }

				$start = ($bits[1] == '#') ? $vm->getLocation() : $bits[1];
				$end = ($bits[2] == '#') ? $vm->getLocation() : $bits[2];

				$out->inputTitle('DUMP ' . $start . ' ' . $end);
				$out->addTrace('==[ dump ' . $start . ' ' . $end .' ] ==');
				$vm->dump($start, $end, ($bits[0] == 'dumpall'));
				$out->addTrace('==[ end dump ] ==========');
				$out->traceOnOutput();

			// ========================================
			// Set the memory at position X to Y
			// ========================================
			} else if ($bits[0] == 'setmem' && isset($bits[2])) {
				$op = $bits[2];
				if (!is_numeric($bits[2])) {
					$ops = $vm->getOps();
					foreach ($ops as $o) {
						if ($o->name() == $bits[2]) {
							$op = $o->code();
						}
					}
				}
				$loc = ($bits[1] == '#') ? $vm->getLocation() : $bits[1];
				if (is_numeric($loc)) {
					$vm->setData($loc, $op);
					$out->inputTitle('SETMEM ' . $loc . ': ' . $op);
				} else {
					$out->inputTitle('INVALID SETMEM');
				}

			// ========================================
			// Set register X to Y
			// ========================================
			} else if ($bits[0] == 'setreg' && isset($bits[2])) {
				$vm->set((int)$bits[1] - 1, (int)$bits[2]);
				$out->inputTitle('SETREG ' . $bits[1] . ': ' . $bits[2]);

			// ========================================
			// Push X onto the stack
			// ========================================
			} else if ($bits[0] == 'push' && isset($bits[1])) {
				$out->inputTitle('PUSH ' . $bits[1]);
				$vm->push($bits[1]);

			// ========================================
			// Pop the stack
			// ========================================
			} else if ($bits[0] == 'pop') {
				$val = $vm->pop();
				$out->inputTitle('POP ' . $val);

			// ========================================
			// Save the current VM state (Optionally to filename)
			// ========================================
			} else if ($bits[0] == 'save') {
				$file = isset($bits[1]) ? $bits[1] : 'savestate.' . time();
				$out->inputTitle('SAVE TO ' . $file);
				$vm->saveState($file);
				$out->inputTitle('SAVED TO ' . $file);

			// ========================================
			// Load the VM State from the given file.
			// ========================================
			} else if ($bits[0] == 'load' && isset($bits[1])) {
				$out->inputTitle('LOAD FROM ' . $bits[1]);
				$vm->loadState($bits[1]);
				$out->redrawAll();
				$out->refreshAll();
				$out->inputTitle('LOADED FROM ' . $bits[1]);

			// ========================================
			// Preload input.
			// ========================================
			} else if ($bits[0] == 'in' && isset($bits[1])) {
				$out->inputTitle('LOAD INPUT FROM ' . $bits[1]);
				$in = file($bits[1], FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				foreach ($in as $i) {
					$out->addStoredInput($i);
					$out->addTrace('Loaded input: ' . $i);
				}
				$out->inputTitle('LOADED INPUT FROM ' . $bits[1]);

			// ========================================
			// Help
			// ========================================
			} else if ($bits[0] == 'help') {
				$helpdata = array();
				$helpdata[] = '==========';
				$helpdata[] = 'Any input beginning with a ! is considered a command, otherwise it is added to the "input buffer" which gets depleted each time the VM asks for input.';
				$helpdata[] = 'If there is no input in the buffer, then we halt and wait for more to be added.';
				$helpdata[] = '';
				$helpdata[] = 'The available commands are:';
				$helpdata[] = '';
				$helpdata[] = '  ! | !!                            - Run the last command again.';
				$helpdata[] = '  !help                             - Show this help output.';
				$helpdata[] = '  !run | !trace | !traceall         - Run the VM as long as possible (trace shows output, slower)';
				$helpdata[] = '  !halt                             - halt the vm';
				$helpdata[] = '  !reset                            - reset the ui based on the inital launch parameters';
				$helpdata[] = '  !loadbin <filename>               - Load <filename> as a new binary';
				$helpdata[] = '  !exit                             - Exit the UI';
				$helpdata[] = '  !step <#> | !stepall <#>          - Step (and trace) through <#> instructions';
				$helpdata[] = '  !jump <#>                         - Set the location pointer to <#>';
				$helpdata[] = '  !break <#>                        - Add a breakpoint at <#>';
				$helpdata[] = '  !unbreak <#>                      - Remove a breakpoint at <#>';
				$helpdata[] = '  !nobreak                          - Remove all breakpoints';
				$helpdata[] = '  !continue                         - Execute at a breakpoint (!trace, !step, !run will not pass a breakpoint)';
				$helpdata[] = '  !getmem <#>                       - Get the memory at <#> (<#1> can be "#" for the current location)';
				$helpdata[] = '  !dump <#1> <#2>                   - Dump the instructions between <#1> and <#2> to the trace window (<#1> or <#2> can be "#" for the current location)';
				$helpdata[] = '  !dumpall <#1> <#2>                - Same as !dump, but also include non-instructional data.';
				$helpdata[] = '  !setmem <#1> <#2>                 - Set memory location <#1> to be the raw value <#2> (<#2> can also be the name of an operation, and <#1> can be "#" for the current location)';
				$helpdata[] = '  !setreg <#1> <#2>                 - Set Register <#1> to be the raw value <#2>';
				$helpdata[] = '  !push <#>                         - Push the raw value <#> to the stack.';
				$helpdata[] = '  !pop                              - Pop the stack';
				$helpdata[] = '  !save [filename]                  - Save the current VM State to [filename] (Autogenerated if not supplied)';
				$helpdata[] = '  !load <filename>                  - Load the VM State from <filename>';
				$helpdata[] = '  !in <filename>                    - PreLoad the input buffer from <filename>';
				$helpdata[] = '';
				$helpdata[] = '';
				$helpdata[] = 'By default the trace window only updates when there is output to one of the other windows (otherwise it gets to be quite slow).';
				$helpdata[] = 'The !stepall and !traceall variants of !step and !trace cause the trace output to to update every trace.';
				$helpdata[] = '==========';
				$out->addOutput($helpdata);


			// ========================================
			// Unknown.
			// ========================================
			} else {
				$out->inputTitle('Unknown Command: ' . $bits[0]);
			}

		// ========================================
		// Store input
		// ========================================
		} else {
			$out->addStoredInput($input);
			$out->inputTitle('Stored Input: ' . $input);
		}

		if ($input != '!' && $input != '!!') { $__LASTINPUT = $input; }

		return true;
	};
	$out->setHandlers($outHandlers);


	/**
	 * Handler for output from the VM.
	 *
	 * @param $vm The VM that called us
	 * @param $output Output from the vm.
	 * @uses $out The Challenge UI
	 */
	$vmHandlers['output'] = function ($vm, $output) use ($out) {
		$out->addOutput($output);

		global $__CLIOPTS;
		if (isset($__CLIOPTS['log'])) {
			file_put_contents($__CLIOPTS['log'], chr($output), FILE_APPEND | LOCK_EX);
		}
	};

	/**
	 * Handler for Traces from the VM.
	 *
	 * @param $vm The VM that called us
	 * @param $loc The current location of the vm
	 * @param $op The SynacorOP being executed
	 * @param $data The array of data being passed to the op
	 * @param $breaking Are we breaking here?
	 * @uses $out The Challenge UI
	 */
	$vmHandlers['trace'] = function ($vm, $loc, $op, $data, $breaking) use ($out) {
		if ($out->tracing()) {
			if (is_array($data)) {
				$d = array();
				foreach ($data as $da) {
					$s = '';
					if ($vm->isRegister($da)) {
						$vm->asRegister($da);
						$da++;
						$s .= '{R'.$da.':'.$vm->get($da - 1).'}';
					} else {
						$s .= $da;
					}
					$d[] = $s;
				}
				$d =  implode(', ', $d);
			} else { $d = $data; }

			$trace = sprintf("%8d | %6s %s", $loc, $op->name(), $d);
			if ($breaking) { $trace .= ' [!!]'; }
			$out->addTrace($trace);

			global $__CLIOPTS;
			if (isset($__CLIOPTS['trace'])) {
				file_put_contents($__CLIOPTS['trace'], $trace."\n", FILE_APPEND | LOCK_EX);
			}
		}
	};

	/**
	 * Handler for Dumps from the VM.
	 *
	 * @param $vm The VM that called us
	 * @param $loc The current location of the vm
	 * @param $op The SynacorOP being executed
	 * @param $data The array of data being passed to the op
	 * @uses $out The Challenge UI
	 */
	$vmHandlers['dump'] = function ($vm, $loc, $op, $data) use ($out) {
		if (is_array($data)) {
			$d = array();
			foreach ($data as $da) {
				$s = '';
				if ($vm->isRegister($da)) {
					$vm->asRegister($da);
					$da++;
					$s .= '{R'.$da.'}';
				} else {
					$s .= $da;
				}
				$d[] = $s;
			}
			$d =  implode(', ', $d);
		} else { $d = $data; }

		$trace = sprintf("DUMP: %8d | %6s %s", $loc, $op->name(), $d);
		$out->addTrace($trace);

		global $__CLIOPTS;
		if (isset($__CLIOPTS['trace'])) {
			file_put_contents($__CLIOPTS['trace'], $trace."\n", FILE_APPEND | LOCK_EX);
		}
	};

	/**
	 * Handler for input to the VM.
	 *
	 * @param $vm The VM that called us
	 * @uses $out The Challenge UI
	 */
	$vmHandlers['input'] = function ($vm) use ($out) {
		$return = FALSE;

		$storedInput = $out->getStoredInput();

		if (count($storedInput) > 0) {
			$line = $storedInput[0];
			if (empty($line)) {
				$return = 10;
				array_shift($storedInput);
			} else {
				$return = ord(substr($line, 0, 1));
				$storedInput[0] = strlen($line) > 1 ? substr($line, 1) : '';
			}
		}

		$out->setStoredInput($storedInput);
		$out->addTrace('Input: ' . ($return === FALSE ? 'FALSE' : $return));

		// Log to the output window aswell.
		if ($return !== FALSE) { $out->addOutput($return); }

		$out->refreshAll();
		return $return;
	};

	// Set the VM handlers.
	$vm->setHandlers($vmHandlers);

	function initStateFromCLI($vm, $out) {
		global $__CLIOPTS;

		$outHandlers = $out->getHandlers();
		if (isset($__CLIOPTS['state']) && file_exists($__CLIOPTS['state'])) {
			$outHandlers['gotInput']($out, $vm, '!load ' . $__CLIOPTS['state']);
		}

		if (isset($__CLIOPTS['input']) && file_exists($__CLIOPTS['input'])) {
			$outHandlers['gotInput']($out, $vm, '!in ' . $__CLIOPTS['input']);
		}
	}

	initStateFromCLI($vm, $out);

	if (isset($__CLIOPTS['r']) || isset($__CLIOPTS['run'])) {
		$outHandlers['gotInput']($out, $vm, '!run');
	}

	// Begin the UI Loop!
	$out->loop();
