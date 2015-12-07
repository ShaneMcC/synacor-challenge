<?php
	// call: 17 a
	//   write the address of the next instruction to the stack and jump to <a>
	class Synacor_call implements SynacorOP {
		function args() { return 1; }
		function run($vm, $data) {
			list($a) = $data;
			$vm->push($vm->getLocation());
			$vm->jump($vm->get($a));
		}
		function code() { return 17; }
	}
