<?php
	// jf: 8 a b
	//   if <a> is zero, jump to <b>
	class Synacor_jf implements SynacorOP {
		function args() { return 2; }
		function run($vm, $data) {
			list($a, $b) = $data;
			if ($vm->get($a) === 0) {
				$vm->jump($vm->get($b));
			}
		}
		function code() { return 8; }
	}
