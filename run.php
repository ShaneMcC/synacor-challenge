<?php
	require_once(dirname(__FILE__) . '/SynacorVM.php');

	$binaryData = file_get_contents(dirname(__FILE__) . '/challenge.bin');

	$vm = new SynacorVM($binaryData);

	$vm->run();
	die("\n");
