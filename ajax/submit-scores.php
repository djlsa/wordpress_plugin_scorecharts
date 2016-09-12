<?php
	include '../../../../wp-load.php';

	function _get_temp_dir() {
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

	$scores = $_REQUEST["scores"];
	if($scores != NULL) {
		foreach($scores as $score) {
			if(isset($score['user_id']) && isset($score['game_id']) && isset($score['game_date'])) {
				$data = array(
					'user_id' => $score['user_id'],
					'game_id' => strtolower($score['game_id']),
					'game_date' => gmdate('Y-m-d H:i:s', $score['game_date'])
				);
				$format = array(
					'%d',
					'%s',
					'%s'
				);
				$duration = 'ALL';
				if(isset($score['game_duration'])) {
					$data['game_duration'] = $score['game_duration'];
					array_push($format, '%f');
					$duration = $score['game_duration'];
				}
				$score_data = array();
				if(isset($score['score_errors'])) {
					$data['score_errors'] = $score['score_errors'];
					array_push($format, '%f');
					array_push($score_data, 'errors');
				}
				if(isset($score['score_accuracy'])) {
					$data['score_accuracy'] = $score['score_accuracy'];
					array_push($format, '%f');
					array_push($score_data, 'accuracy');
				}
				if(isset($score['score_accuracy2'])) {
					$data['score_accuracy2'] = $score['score_accuracy2'];
					array_push($format, '%f');
					array_push($score_data, 'accuracy2');
				}
				if(isset($score['score_reactiontime'])) {
					$data['score_reactiontime'] = $score['score_reactiontime'];
					array_push($format, '%f');
					array_push($score_data, 'reactiontime');
				}
				$wpdb->insert($wpdb->prefix . 'pat_scorecharts', $data, $format);

				$cache_files = glob(_get_temp_dir() . '/pat_scorecharts/' . 'user_' . $score['user_id'] . '_*');
				foreach($cache_files as $file) {
					@unlink($file);
				}
			}
		}
	}
?>