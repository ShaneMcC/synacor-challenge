<?php
	// jmp: 6 a
	//   jump to <a>
	class Synacor_jmp implements SynacorOP {
		function args() { return 1; }
		function run($vm, $data) {
			list($a) = $data;
			$vm->jump($vm->get($a));
		}
		function code() { return 6; }
	}
