<?php
/*
Plugin Name: Score Charts
Plugin URI: https://www.elance.com/s/djlsa
Description: Use the shortcode [scorechart game='game_id' data='score_type' duration='time_in_minutes (optional)' level='basic|advanced'] EXAMPLE: [scorechart game="control" data="errors" level="basic"] | valid values for data are 'errors', 'accuracy' and 'reactiontime'
Version: 1.0
Author: David Salsinha
Author URI: https://www.elance.com/s/djlsa
Author Email: davidsalsinha@gmail.com

*/

class ScoreCharts {

	function __construct() {

		add_action( 'init', array( $this, 'plugin_textdomain' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_plugin_scripts' ) );

		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
		register_uninstall_hook( __FILE__, array( $this, 'uninstall' ) );

		$plugin = plugin_basename(__FILE__);
		add_filter("plugin_action_links_$plugin", array( $this, 'filter_clear_cache_link' ) );
		add_action( 'wp_head', array( $this, 'action_add_flotr_include' ) );
		add_shortcode( 'scorechart', array( $this, 'shortcode_scorechart' ) );

	}

	public function activate( $network_wide ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'scorecharts';
		$sql = "CREATE TABLE IF NOT EXISTS $table_name (
				user_id bigint(20) unsigned NOT NULL,
				game_id varchar(32) NOT NULL,
				game_date datetime NOT NULL,
				game_duration int(20) DEFAULT NULL,
				score_errors double DEFAULT NULL,
				score_accuracy double DEFAULT NULL,
				score_accuracy2 double DEFAULT NULL,
				score_reactiontime double DEFAULT NULL,
				KEY user_id (user_id),
				KEY game_id (game_id),
				KEY game_date (game_date),
				KEY game_duration (game_duration)
		);";
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	public function deactivate( $network_wide ) {
	}

	public function uninstall( $network_wide ) {
	}

	public function plugin_textdomain() {
		$domain = 'scorecharts_locale';
		$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
		load_textdomain( $domain, WP_LANG_DIR.'/'.$domain.'/'.$domain.'-'.$locale.'.mo' );
		load_plugin_textdomain( $domain, FALSE, dirname( plugin_basename( __FILE__ ) ) . '/lang/' );

	}


	public function register_plugin_styles() {

		wp_enqueue_style( 'scorecharts_plugin_styles', plugins_url( 'scorecharts/css/display.css' ) );

	}

	public function register_plugin_scripts() {

		wp_enqueue_script( 'scorecharts_plugin_script', plugins_url( 'scorecharts/js/display.js' ), array('jquery') );

	}

	function filter_clear_cache_link($links) {
		$link = '<a href="' . plugins_url( 'scorecharts/clear-cache.php') . '">Clear cache</a>'; 
		array_unshift($links, $link); 
		return $links; 
	}

	function action_add_flotr_include() {
		echo "<!--[if IE]><script type=\"text/javascript\" src=\"" . plugins_url('scorecharts/js/flashcanvas.js') . "\"></script><![endif]-->";
		echo "<script type=\"text/javascript\" src=\"" . plugins_url('scorecharts/js/flotr2.min.js') . "\"></script>";
	}

	private function get_temp_dir() {
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

	private function write_cache($name, $data) {
		$dir = $this->get_temp_dir() . '/scorecharts/';
		@mkdir($dir);
		@file_put_contents($dir . $name, serialize($data));
	}

	private function read_cache($name, $expire) {
		$file = $this->get_temp_dir() . '/scorecharts/' . $name;
		clearstatcache();
		if(file_exists($file) && time() - filectime($file) < $expire)
			return unserialize(file_get_contents($file));
		@unlink($file);
		return null;
	}

	function shortcode_scorechart($atts) {
		global $wpdb;
		$user_id = wp_get_current_user()->ID;
		$atts = shortcode_atts( array(
			'game' => 'null',
			'duration' => '*',
			'data' => 'errors',
			'level' => 'basic'
		), $atts );
		
		$wpdb->query("UPDATE scorecharts SET score_accuracy = 0.5 WHERE score_accuracy < 0 OR score_accuracy > 1");
		$wpdb->query("UPDATE scorecharts SET score_accuracy2 = 0.5 WHERE score_accuracy2 < 0 OR score_accuracy2 > 1");
		$wpdb->query("UPDATE scorecharts SET score_reactiontime = 0 WHERE score_reactiontime < 0");

		$duration = ($atts['duration'] == '*' ? 'ALL' : $atts['duration'] * 60);

		$display_cache_time = 60;
		$display_cache_name = 'user_' . $user_id . '_display_' . $atts['game'] . '_' . $duration . '_' . $atts['data'] . '_' . $atts['level'] . '.txt';
		$display = $this->read_cache($display_cache_name, $display_cache_time);
		if($display === null) {
			$global_cache_time = 10 * 60;
			$local_cache_time = mktime(24,0,0) - time();

			$global_cache_name = 'global_' . $atts['game'] . '_' . $duration . '_' . $atts['data'] . '_' . $atts['level'] . '.txt';
			$global = $this->read_cache($global_cache_name, $global_cache_time);
			if($global === null) {
				$global = $wpdb->get_row(
							$wpdb->prepare(
								"SELECT MAX( score_" . $atts['data'] . " ) AS high, MIN( score_" . $atts['data'] . " ) AS low, AVG( score_" . $atts['data'] . " ) AS avg
								FROM " . $wpdb->prefix . "scorecharts WHERE game_id LIKE %s" . ($atts['duration'] == '*' ? '' : ' AND game_duration = %d'),
								array(
									strtolower($atts['game']) . '_' . strtolower($atts['level']),
									$atts['duration'],
								)
							)
						);
				$this->write_cache($global_cache_name, $global);
			}

			$user_global_chart_data_cache_name = 'user_' . $user_id . '_global_' . $atts['game'] . '_' . $duration . '_' . $atts['data'] . '_' . $atts['level'] . '.chartdata.txt';
			$global_chart_data = $this->read_cache($user_global_chart_data_cache_name, $global_cache_time);

			$force_local_refresh = false;

			if($global_chart_data === null) {
				$force_local_refresh = true;
				$global_chart_data_cache_name = 'global_' . $atts['game'] . '_' . $duration . '_' . $atts['data'] . '_' . $atts['level'] . '.chartdata.txt';
				$global_chart_data = $this->read_cache($global_chart_data_cache_name, $global_cache_time);
				if($global_chart_data === null) {
					$global_chart_data_result = $wpdb->get_results(
								$wpdb->prepare(
									"SELECT game_id, UNIX_TIMESTAMP(game_date) as date, AVG(score_" . $atts['data'] . ") as value FROM " . $wpdb->prefix . "scorecharts
									WHERE game_id LIKE %s" . ($atts['duration'] == '*' ? '' : ' AND game_duration = %d') . "
									GROUP BY YEAR(game_date), MONTH(game_date), DAY(game_date), game_id
									ORDER BY game_date ASC, game_id DESC",
									array(
										strtolower($atts['game']) . '_' . strtolower($atts['level']),
										$atts['duration'],
									)
								)
							);
					$global_chart_data = new stdClass();
					$global_chart_data->minval = 0;
					$global_chart_data->maxval = 0;
					$global_chart_data->data = array();
					foreach($global_chart_data_result as $row) {
						$pos = strpos($row->game_id, '_');
						$gamevar = substr($row->game_id, ($pos !== FALSE ? $pos + 1 : 0));
						if(!is_array($global_chart_data->data[$gamevar]))
							$global_chart_data->data[$gamevar] = array();
						$global_chart_data->data[$gamevar][] = $row;

						if($global_chart_data->minval == 0)
							$global_chart_data->minval = $row->value;
						else if($global_chart_data->minval > $row->value)
							$global_chart_data->minval = $row->value;

						if($global_chart_data->maxval == 0)
							$global_chart_data->maxval = $row->value;
						else if($global_chart_data->maxval < $row->value)
							$global_chart_data->maxval = $row->value;
					}
					if(count($global_chart_data->data) == 0) {
						$point = new stdClass();
						$point->date = time();
						$point->value = 0;
						$global_chart_data->data[''] = array();
						array_push($global_chart_data->data[''], $point);
					}
					foreach(array_keys($global_chart_data->data) as $k) {
						if(count($global_chart_data->data[$k]) == 1) {
							$point = new stdClass();
							$point->date = $global_chart_data->data[$k][0]->date + 24 * 60 * 60;
							$point->value = $global_chart_data->data[$k][count($global_chart_data->data[$k])-1]->value;
							if($global_chart_data->maxval != 0 && $global_chart_data->maxval < $point->value)
								$global_chart_data->maxval = $point->value;
							array_push($global_chart_data->data[$k], $point);
						}
					}

					$this->write_cache($global_chart_data_cache_name, $global_chart_data);
				}
			}

			$local_cache_name = 'user_' . $user_id . '_' . $atts['game'] . '_' . $duration . '_' . $atts['data'] . '_' . $atts['level'] . '.txt';
			$local = $this->read_cache($local_cache_name, $local_cache_time);
			if($local === null) {
				$local = $wpdb->get_row(
							$wpdb->prepare(
								"SELECT MAX( score_" . $atts['data'] . " ) AS high, MIN( score_" . $atts['data'] . " ) AS low, AVG( score_" . $atts['data'] . " ) AS avg
								FROM " . $wpdb->prefix . "scorecharts WHERE user_id = %d AND game_id LIKE  %s" . ($atts['duration'] == '*' ? '' : ' AND game_duration = %d'),
								array(
									$user_id,
									strtolower($atts['game']) . '_' . strtolower($atts['level']),
									$atts['duration'],
								)
							)
						);
				$this->write_cache($local_cache_name, $local);
			}

			$chart_data_cache_name = 'user_' . $user_id . '_' . $atts['game'] . '_' . $duration . '_' . $atts['data'] . '_' . $atts['level'] . '.chartdata.txt';
			global $chart_data;
			$chart_data = $this->read_cache($chart_data_cache_name, $local_cache_time);
			if($chart_data === null) {
				$chart_data_result = $wpdb->get_results(
							$wpdb->prepare(
								"SELECT game_id, UNIX_TIMESTAMP(game_date) as date, AVG(score_" . $atts['data'] . ") as value FROM " . $wpdb->prefix . "scorecharts
								WHERE user_id = %d AND game_id LIKE %s" . ($atts['duration'] == '*' ? '' : ' AND game_duration = %d') . "
								GROUP BY YEAR(game_date), MONTH(game_date), DAY(game_date), game_id
								ORDER BY game_date ASC, game_id DESC",
								array(
									$user_id,
									strtolower($atts['game']) . '_' . strtolower($atts['level']),
									$atts['duration'],
								)
							)
						);
				$chart_data = new stdClass();
				//$dates = array();
				$chart_data->mindate = 0;
				$chart_data->minval = 0;
				$chart_data->maxdate = 0;
				$chart_data->maxval = 0;
				$chart_data->data = array();
				foreach($chart_data_result as $row) {
					$pos = strpos($row->game_id, '_');
					$gamevar = substr($row->game_id, ($pos !== FALSE ? $pos + 1 : 0));
					if(!is_array($chart_data->data[$gamevar]))
						$chart_data->data[$gamevar] = array();
					$chart_data->data[$gamevar][] = $row;

					if($chart_data->mindate == 0)
						$chart_data->mindate = $row->date;
					else if($chart_data->mindate > $row->date)
						$chart_data->mindate = $row->date;

					if($chart_data->maxdate == 0)
						$chart_data->maxdate = $row->date;
					else if($chart_data->maxdate < $row->date)
						$chart_data->maxdate = $row->date;

					//array_push($dates, $row->date);

					if($chart_data->minval == 0)
						$chart_data->minval = $row->value;
					else if($chart_data->minval > $row->value)
						$chart_data->minval = $row->value;

					if($chart_data->maxval == 0)
						$chart_data->maxval = $row->value;
					else if($chart_data->maxval < $row->value)
						$chart_data->maxval = $row->value;
				}
				if(count($chart_data->data) == 0) {
					$point = new stdClass();
					$point->date = time();
					$point->value = -2;
					$chart_data->mindate = $point->date;
					//array_push($dates, $point->date);
					$chart_data->data[''] = array();
					array_push($chart_data->data[''], $point);
				}
				foreach(array_keys($chart_data->data) as $k) {
					if(count($chart_data->data[$k]) == 1) {
						$point = new stdClass();
						$point->date = $chart_data->data[$k][0]->date + 24 * 60 * 60;
						$point->value = $chart_data->data[$k][count($chart_data->data[$k])-1]->value;
						$chart_data->maxdate = $point->date;
						if($chart_data->maxval != 0 && $chart_data->maxval < $point->value)
							$chart_data->maxval = $point->value;
						//array_push($dates, $point->date);
						array_push($chart_data->data[$k], $point);
					}
				}
				//$chart_data->mindate = min($dates);
				//$chart_data->maxdate = max($dates);

				$this->write_cache($chart_data_cache_name, $chart_data);
			}

			if($force_local_refresh) {

				if(!function_exists('array_filter_dates')) {
					function array_filter_dates($point) {
						global $chart_data;
						if($point->date < $chart_data->mindate)
							$point->date = $chart_data->mindate;
						else if($point->date > $chart_data->maxdate)
							$point->date = $chart_data->maxdate;
						return true;
					}
				}

				foreach(array_keys($global_chart_data->data) as $k) {
					$global_chart_data->data[$k] = array_filter($global_chart_data->data[$k], 'array_filter_dates');

					if($chart_data->mindate < $global_chart_data->data[$k][0]->date)
						$global_chart_data->data[$k][0]->date = $chart_data->mindate;
					if($chart_data->maxdate > $global_chart_data->data[$k][count($global_chart_data->data[$k]) - 1]->date)
						$global_chart_data->data[$k][count($global_chart_data->data[$k]) - 1]->date = $chart_data->maxdate;
				}

				$this->write_cache($user_global_chart_data_cache_name, $global_chart_data);
			}

			$divname = 'scorechart_' . substr(str_shuffle("0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ"), 0, 5) . time();
			$dataname = '';
			$round_decimals = 1;
			$is_percent = false;
			switch($atts['data']) {
				case 'errors' : $dataname = 'Errors'; break;
				case 'accuracy' : $dataname = 'Percentage Accuracy'; if(strtolower($atts['game']) == 'tracking') { $dataname = 'Left hand accuracy'; } $is_percent = true; break;
				case 'accuracy2' : $dataname = 'Right hand Accuracy'; $is_percent = true; break;
				case 'reactiontime' : $dataname = 'Reaction Speed (S)'; $round_decimals = 3; break;
			}
			
			ob_start();
?>
<!--
<div class="low_high_avg">
	<div>Your High: <span class="your"><?php echo round($local->high * ($is_percent ? 100 : 1), $round_decimals) . ($is_percent ? '%' : ''); ?></span></div>
	<div>Your Low: <span class="your"><?php echo round($local->low * ($is_percent ? 100 : 1), $round_decimals) . ($is_percent ? '%' : ''); ?></span></div>
	<div>Your Avg: <span class="your"><?php echo round($local->avg * ($is_percent ? 100 : 1), $round_decimals) . ($is_percent ? '%' : ''); ?></span></div>
	<div>Global High: <span class="global"><?php echo round($global->high * ($is_percent ? 100 : 1), $round_decimals) . ($is_percent ? '%' : ''); ?></span></div>
	<div>Global Low: <span class="global"><?php echo round($global->low * ($is_percent ? 100 : 1), $round_decimals) . ($is_percent ? '%' : ''); ?></span></div>
	<div>Global Avg: <span class="global"><?php echo round($global->avg * ($is_percent ? 100 : 1), $round_decimals) . ($is_percent ? '%' : ''); ?></span></div>
</div>
-->
<div id="<?php echo $divname ?>" class="scorechart">
</div>
<script type="text/javascript">
	var
<?php
			$count = 1;
			foreach($global_chart_data->data as $game) {
				if($count > 1) echo ',';
				echo 'd' . $count++ . ' = [ ';
				$first = true;
				foreach($game as $data) {
					if($first) $first = false; else echo ', ';
					if($data->value < 0)
						$data->value = -$data->value;
					echo '[' . $data->date . ', ' . $data->value . ']';
				}
				echo ']';
			}
			foreach($chart_data->data as $game) {
				if($count > 1) echo ',';
				echo 'd' . $count++ . ' = [ ';
				$first = true;
				foreach($game as $data) {
					if($first) $first = false; else echo ', ';
					if($data->value < 0)
						$data->value = -$data->value;
					echo '[' . $data->date . ', ' . $data->value . ']';
				}
				echo ']';
			}
			if($count > 1) echo ',';
?>
			chart_data = [
<?php
			$count = 1;

			$curcolor = 0;
			$colors = array('#c5c4c6', '#6b8f24', '#b8db70');
			foreach(array_keys($global_chart_data->data) as $game) {
				if($curcolor > count($colors))
					$color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
				else
					$color = $colors[$curcolor++];

				if($count > 1) echo ',';
				echo '{ data: d' . $count++ . ', label: "Global data' . ($game != '' ? ' - ' . $game : '') . '", color: "' . $color . '", lines : { fill : true, fillColor : "#c5c4c6", fillOpacity : 0.9 } } ';
			}

			$curcolor = 0;
			$colors = array('#000000', '#246b8f', '#70b8db');
			foreach(array_keys($chart_data->data) as $game) {
				if($curcolor > count($colors))
					$color = sprintf('#%06X', mt_rand(0, 0xFFFFFF));
				else
					$color = $colors[$curcolor++];

				if($count > 1) echo ',';
				echo '{ data: d' . $count++ . ', label: "Your data' . ($game != '' ? ' - ' . $game : '') . '", color: "' . $color . '", lines : { show : true }, points : { show : true } } ';
			}
?>
			];
<?php
			if($is_percent) {
?>
			draw_scorechart(document.getElementById('<?php echo $divname; ?>'), '<?php echo $dataname; ?>', chart_data, -1.1, 2);
<?php
			} else {
?>
			draw_scorechart(document.getElementById('<?php echo $divname; ?>'), '<?php echo $dataname; ?>', chart_data, <?php echo min($chart_data->minval, $global_chart_data->minval) -1; ?>, <?php echo max($chart_data->maxval, $global_chart_data->maxval) + 1.9; ?>);
<?php
			}
?>
			<?php /*<?php echo min(array($local->low, $global->avg)) - 1.1; ?>, <?php echo max(array($local->high, $global->avg)) + 1; ?>);*/ ?>
	</script>
<?php

			$display = ob_get_contents();
			ob_end_clean();
			$this->write_cache($display_cache_name, $display);
?>

<style type="text/css">
body {
	overflow-x: hidden;
}

#content {
	width: 75% !important;
	float: left !important;
}

@media screen and (orientation:portrait) {
	#content {
		width: 53% !important;
	}
}
</style>

<?php
		}
		return $display;
	}

} // end class

$plugin_name = new ScoreCharts();