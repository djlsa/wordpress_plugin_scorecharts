<?php
	function get_temp_dir() {
		if (file_exists('/dev/shm') ) { return '/dev/shm'; }
		if (!empty($_ENV['TMP'])) { return realpath($_ENV['TMP']); }
		if (!empty($_ENV['TMPDIR'])) { return realpath( $_ENV['TMPDIR']); }
		if (!empty($_ENV['TEMP'])) { return realpath( $_ENV['TEMP']); }
		$tempfile=tempnam(__FILE__,'');
		if (file_exists($tempfile)) {
			unlink($tempfile);
			return realpath(dirname($tempfile));
		}
		return null;
	}

	$cache_files = glob(get_temp_dir() . '/scorecharts/*.txt');
	foreach ($cache_files as $filename) {
		@unlink($filename);
	}
?>

Cache cleared