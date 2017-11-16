#!/usr/bin/php
<?php
	require_once(dirname(__FILE__) . '/SynacorVM.php');
	require_once(dirname(__FILE__) . '/VMOutput.php');
	require_once(dirname(__FILE__) . '/VMOutput_ncurses.php');

	/**
	 * Get path to file from given input.
	 * This ensures we only load files from within our directory
	 *
	 * @param $name File name
	 * @param $dir Subdirectory file resides in.
	 * @return File path.
	 */
	function getFilepath($name, $dir) {
		$name = str_replace('/', '', $name);
		$path = dirname(__FILE__) . '/' . $dir . '/' . $name;
		return str_replace(getcwd(), '.', $path);
	}

	// Parse command line.
	try {
		$__CLIOPTS = @getopt("hr", array('help', 'file:', 'state:', 'input:', 'log:', 'trace:', 'run', 'autorun', 'nocurses'));
		if (isset($__CLIOPTS['h']) || isset($__CLIOPTS['help'])) {
			echo 'Usage: ', $_SERVER['argv'][0], ' [options]', "\n";
			echo '', "\n";
			echo 'Valid options', "\n";
			echo '  -h, --help               Show this help output', "\n";
			echo '      --file <file>        Use ./bin/<file> for the binary file rather than "./bin/challenge.bin"', "\n";
			echo '      --state <file>       Preload State from ./states/<file>', "\n";
			echo '      --input <file>       Preload Input from ./inputs/<file>', "\n";
			echo '      --log <file>         Log all output to ./logs/<file>', "\n";
			echo '      --trace <file>       Log all trace to ./logs/<file>', "\n";
			echo '  -r, --run                Start executing immediately.', "\n";
			echo '      --autorun            Enable autorun on input.', "\n";
			echo '      --nocurses           Run without ncurses frontend.', "\n";
			die();
		}
	} catch (Exception $e) { /* Do nothing. */ }

	// Load in the binary for the challenge
	$binaryFile = isset($__CLIOPTS['file']) && file_exists(getFilepath($__CLIOPTS['file'], 'bin')) ? getFilepath($__CLIOPTS['file'], 'bin') : getFilepath('challenge.bin', 'bin');
	$binaryData = @file_get_contents($binaryFile);

	// Start a VM
	$vm = new SynacorVM($binaryData);

	// Load our ncurses emulator if ncurses lib is not installed.
	if (!function_exists('ncurses_init')) {
		// Emulator only works on Linux, so don't bother trying on windows.
		if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
			require_once(dirname(__FILE__) . '/noncurses.php');
		}
	}

	// Do we have ncurses, or our ncurses emulator? If not, disable curses.
	if (!function_exists('ncurses_init')) { $__CLIOPTS['nocurses'] = true; }

	// Create an output for the given vm.
	$out = (isset($__CLIOPTS['nocurses'])) ? new VMOutput($vm) : new VMOutput_ncurses($vm);

	// Enable Autorun if required.
	$autorun = isset($__CLIOPTS['autorun']);

	// Handle SIGINT/SIGTERM.
	if (function_exists("pcntl_signal")) {
		pcntl_signal(SIGINT, function() use ($out) { $out->end(); die(); });
		pcntl_signal(SIGTERM, function() use ($out) { $out->end(); die(); });
		if (function_exists("pcntl_async_signals")) {
			pcntl_async_signals(true);
		}
	}

	/**
	 * Handler for user input from the Challenge UI.
	 *
	 * @param $out VMOutput that wanted output.
	 * @param $vm VM that we are running
	 * @param $input Input from user
	 * @return False to abort VM, else true to continue.
	 */
	$outHandlers['gotInput'] = function ($out, $vm, $input) use ($binaryData, &$autorun) {
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

			} else if ($bits[0] == 'autorun') {
				$autorun = !$autorun;
				$out->inputTitle('Autorun is now: ' . ($autorun ? 'on' : 'off'));

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
				$out->inputTitle('LOADBIN FROM ./bin/' . $bits[1]);
				$vm->loadbin(getFilepath($bits[1], 'bin'));

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

				if (!isset($bits[1]) && !isset($bits[2])) { $bits[1] = $bits[2] = '#'; }
				if (!isset($bits[2])) { $bits[2] = $bits[1]; }

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
				$val = $bits[2];
				if (!is_numeric($bits[2])) {
					$op = $vm->getOpByName($bits[2]);
					if ($op->name() != 'NONE') { $val = $op->code(); }
				}
				if (preg_match('#R([1-8])#i', $bits[2], $m)) {
					$val = ($m[1] - 1);
					$vm->toRegister($val);
				}

				$loc = ($bits[1] == '#') ? $vm->getLocation() : $bits[1];
				if (is_numeric($loc)) {
					$vm->setData($loc, $val);
					$out->inputTitle('SETMEM ' . $loc . ': ' . $val);
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
				$out->inputTitle('SAVE TO ' . getFilepath($file, 'states'));
				$vm->saveState(getFilepath($file, 'states'));
				$out->inputTitle('SAVED TO ' . getFilepath($file, 'states'));

			// ========================================
			// Load the VM State from the given file.
			// ========================================
			} else if ($bits[0] == 'load' && isset($bits[1])) {
				$out->inputTitle('LOAD FROM ' . getFilepath($bits[1], 'states'));
				$vm->loadState(getFilepath($bits[1], 'states'));
				$out->update();
				$out->inputTitle('LOADED FROM ' . getFilepath($bits[1], 'states'));

			// ========================================
			// Preload input.
			// ========================================
			} else if ($bits[0] == 'in' && isset($bits[1])) {
				$out->inputTitle('LOAD INPUT FROM ' . $bits[1]);
				$in = file(getFilepath($bits[1], 'inputs'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
				$h = $out->getHandlers();
				foreach ($in as $i) {
					$out->addTrace('Loaded input: ' . $i);
					$h['gotInput']($out, $vm, $i);
				}
				$out->inputTitle('LOADED INPUT FROM ' . $bits[1]);

			// ========================================
			// Send a line of input.
			// ========================================
			} else if ($bits[0] == 'send' && isset($bits[1])) {
				$out->addStoredInput($bits[1]);
				$out->inputTitle('Stored Input: ' . $bits[1]);
				if ($autorun) {
					$h = $out->getHandlers();
					$h['gotInput']($out, $vm, '!run');
				}

			// ========================================
			// Fix parts of memory to advance.
			// ========================================
			} else if ($bits[0] == 'show') {

				$out->addTrace("R1: [ " . $vm->get(0) . " ]");
				$out->addTrace("R2: [ " . $vm->get(1) . " ]");
				$out->addTrace("R3: [ " . $vm->get(2) . " ]");
				$out->addTrace("R4: [ " . $vm->get(3) . " ]");
				$out->addTrace("R5: [ " . $vm->get(4) . " ]");
				$out->addTrace("R6: [ " . $vm->get(5) . " ]");
				$out->addTrace("R7: [ " . $vm->get(6) . " ]");
				$out->addTrace("R8: [ " . $vm->get(7) . " ]");
				$out->addTrace("Location: [ " . $vm->getLocation() . " ]");
				$out->addTrace("Stack: [ " . implode(' ', $vm->getStack()) . " ]");

			// ========================================
			// Fix parts of memory to advance.
			// ========================================
			} else if ($bits[0] == 'fix' && isset($bits[1])) {
				if ($bits[1] == 'teleporter') {
					$out->inputTitle('Fixing Teleporter..');

					$r1 = $vm->getRegLocation(1 -1);
					$r2 = $vm->getRegLocation(2 -1);

					$vm->set(7, 25734);
					$vm->setData(5489, $vm->getOpByName('set')->code());
					$vm->setData(5490, $r2);
					$vm->setData(5491, 1);
					$vm->setData(5492, $vm->getOpByName('set')->code());
					$vm->setData(5493, $r1);
					$vm->setData(5494, 0);

					$out->inputTitle('Fixed Teleporter!');
				}

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
				$helpdata[] = '  !autorun                          - Toggle autorun (automatically run after a non-command input)';
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
				$helpdata[] = '  !getmem <#>                       - Get the memory at <#> (<#> can be "#" for the current location)';
				$helpdata[] = '  !dump <#1> [<#2>]                 - Dump the instructions between <#1> and <#2> to the trace window (<#1> or <#2> can be "#" for the current location)';
				$helpdata[] = '  !dumpall <#1> [<#2>]              - Same as !dump, but also include non-instructional data.';
				$helpdata[] = '  !setmem <#1> <#2>                 - Set memory location <#1> to be the raw value <#2> (<#2> can also be the name of an operation, and <#1> can be "#" for the current location)';
				$helpdata[] = '  !setreg <#1> <#2>                 - Set Register <#1> to be the raw value <#2>';
				$helpdata[] = '  !push <#>                         - Push the raw value <#> to the stack.';
				$helpdata[] = '  !pop                              - Pop the stack';
				$helpdata[] = '  !save [filename]                  - Save the current VM State to [filename] (Autogenerated if not supplied)';
				$helpdata[] = '  !load <filename>                  - Load the VM State from <filename>';
				$helpdata[] = '  !in <filename>                    - PreLoad the input buffer from <filename>';
				$helpdata[] = '  !send <text>                      - Add <text> to the input buffer.';
				$helpdata[] = '  !show                             - Show registers, stack and current location. (Useful for non-curses output).';
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
			if ($autorun) {
				$h = $out->getHandlers();
				$h['gotInput']($out, $vm, '!run');
			}
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
			file_put_contents(getFilepath($__CLIOPTS['log'], 'logs'), chr($output), FILE_APPEND | LOCK_EX);
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
						$val = $vm->get($da - 1);

						if ($op->name() == 'out') {
							if ($val == '10') {
								$val .= ':"\n"';
							} else {
								$val .= ':"'.chr($val).'"';
							}
						}

						$s .= '{R'.$da.':'.$val.'}';
					} else if ($op->name() == 'out') {
						if ($da == '10') {
							$s .= '{'.$da.':"\n"}';
						} else {
							$s .= '{'.$da.':'.chr($da).'}';
						}
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
		// $out->addTrace('Input: ' . ($return === FALSE ? 'FALSE' : $return));

		// Log to the output window aswell.
		if ($return !== FALSE) { $out->addOutput($return); }

		$out->update();
		return $return;
	};

	// Set the VM handlers.
	$vm->setHandlers($vmHandlers);

	/**
	 * Load CLI state to the given vm and output.
	 *
	 * @param $vm VM we are loading state for
	 * @param $out Output we are loading state for
	 */
	function initStateFromCLI($vm, $out) {
		global $__CLIOPTS;

		$outHandlers = $out->getHandlers();
		if (isset($__CLIOPTS['state'])) {
			$outHandlers['gotInput']($out, $vm, '!load ' . $__CLIOPTS['state']);
		}

		if (isset($__CLIOPTS['input'])) {
			$outHandlers['gotInput']($out, $vm, '!in ' . $__CLIOPTS['input']);
		}
	}
	// Load state and commands that were passed to the CLI.
	initStateFromCLI($vm, $out);

	// If we want to start running immediately, do so.
	if (isset($__CLIOPTS['r']) || isset($__CLIOPTS['run'])) {
		$outHandlers['gotInput']($out, $vm, '!run');
	}

	// Begin the UI Loop!
	$out->loop();
