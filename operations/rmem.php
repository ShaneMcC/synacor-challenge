<?php
	// rmem: 15 a b
	//   read memory at address <b> and write it to <a>
	class Synacor_rmem implements SynacorOP {
		function args() { return 2; }
		function run($vm, $data) {
			list($a, $b) = $data;
			$vm->asRegister($a);
			$vm->decode($b);

			$vm->set($a, $vm->getData($b));
		}
		function code() { return 15; }
		function name() { return 'rmem'; }
	}
