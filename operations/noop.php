<?php
	// noop: 21
	//   no operation
	class Synacor_noop implements SynacorOP {
		function args() { return 0; }
		function run($vm, $data) { /* Do Nothing. */ }
		function code() { return 21; }
		function name() { return 'noop'; }
	}
