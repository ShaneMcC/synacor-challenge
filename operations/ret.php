<?php
	// ret: 18
	//   remove the top element from the stack and jump to it; empty stack = halt
	class Synacor_ret implements SynacorOP {
		function args() { return 0; }
		function run($vm, $data) {
			$v = $vm->pop();
			if ($v === null) { $vm->haltvm('Empty Stack for Ret'); }
			$vm->jump($v);
		}
		function code() { return 18; }
	}
