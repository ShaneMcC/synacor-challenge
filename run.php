<?php
	require_once(dirname(__FILE__) . '/SynacorVM.php');

	$binaryData = file_get_contents(dirname(__FILE__) . '/challenge.bin');

	$debugvm = new SynacorVM($binaryData);
	$vm = new SynacorVM($binaryData);

	echo '----------', "\n";
	$debugvm->run(true);
	echo '----------', "\n";
	$vm->run();
	echo '----------', "\n";

	die("\n");
