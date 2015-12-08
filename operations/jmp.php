<?php
	// jmp: 6 a
	//   jump to <a>
	class Synacor_jmp implements SynacorOP {
		function args() { return 1; }
		function run($vm, $data) {
			list($a) = $data;
			$vm->decode($a);
			$vm->jump($a);
		}
		function code() { return 6; }
	}
