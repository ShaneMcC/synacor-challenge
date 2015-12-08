<?php
	// jt: 7 a b
	//   if <a> is nonzero, jump to <b>
	class Synacor_jt implements SynacorOP {
		function args() { return 2; }
		function run($vm, $data) {
			list($a, $b) = $data;
			$vm->decode($a);
			$vm->decode($b);

			if ($a !== 0) {
				$vm->jump($b);
			}
		}
		function code() { return 7; }
	}
