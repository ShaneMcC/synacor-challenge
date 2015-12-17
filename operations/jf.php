<?php
	// jf: 8 a b
	//   if <a> is zero, jump to <b>
	class Synacor_jf implements SynacorOP {
		function args() { return 2; }
		function run($vm, $data) {
			list($a, $b) = $data;
			$vm->decode($a);
			$vm->decode($b);

			if ($a === 0) {
				$vm->jump($b);
			}
		}
		function code() { return 8; }
		function name() { return 'jf'; }
	}
