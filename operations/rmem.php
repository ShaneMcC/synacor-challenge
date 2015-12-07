<?php
	// rmem: 15 a b
	//   read memory at address <b> and write it to <a>
	class Synacor_rmem implements SynacorOP {
		function args() { return 2; }
		function run($vm, $data) {
			list($a, $b) = $data;

			$vm->set($a, $vm->getData($vm->get($b)));
		}
		function code() { return 15; }
	}
