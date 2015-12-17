<?php
	// none
	//   Not a real op, used for datadump to allow displaying non-ops.
	class Synacor_none implements SynacorOP {
		function args() { return 1; }
		function run($vm, $data) { /* */ }
		function code() { return -1; }
		function name() { return '---'; }
	}
