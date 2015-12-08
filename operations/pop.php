<?php
	// pop: 3 a
	//   remove the top element from the stack and write it into <a>; empty stack = error
	class Synacor_pop implements SynacorOP {
		function args() { return 1; }
		function run($vm, $data) {
			list($a) = $data;
			$vm->asRegister($a);

			$v = $vm->pop();
			if ($v === null) { $vm->haltvm('Bad Pop'); }
			$vm->set($a, $v);
		}
		function code() { return 3; }
	}
