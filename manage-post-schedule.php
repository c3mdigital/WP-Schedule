<?php
/*
Plugin Name: c3m wp-schedule
Plugin URI: 
Description: Gives contributors a jQuery date picker to choose available date and times available to publish posts
Version: 0.1.2
Author: Chris Olbekson
Author URI: http://c3mdigital.com/
License: GPL v2
*/


	register_activation_hook( __FILE__, 'c3m_activate_cron' );
	add_action( 'post_submitbox_misc_actions', 'c3m_create_schedule_meta', 10 );
	add_action( 'admin_print_footer_scripts', 'c3m_echo_js' );
	add_action( 'admin_enqueue_scripts', 'c3m_enqueue_scripts' );

	function c3m_activate_cron() {
		wp_schedule_event( current_time( 'timestamp' ), 'hourly', 'c3m_check_posts' );
	
	}

	function c3m_check_posts() {
		$args = array(
			'post_status' => array( 'draft', 'pending' ),
			'posts_per_page' => -1,
			);

			$timestamp = current_time( 'timestamp' );
			$posts = get_posts( $args );
			$month = (int) date('m', $timestamp );
			$day = (int) date('d', $timestamp );
			$hour = (int) date('G', $timestamp );

				foreach ( $posts as $post ) {
					$date = get_post_meta( $post->ID, '_schedule_date', true );

					if ( !$date ) continue;
					$sched_date = explode( "-", $date['date'] );

					if ( (int) $sched_date[0] > $month  ) continue;
					if ( (int) $sched_date[1] > $day ) continue;
					if ( (int) $sched_date[1] >= $day && (int)$date['time'] > $hour )  continue;

					wp_publish_post( $post->ID );
					
					}

	}
	
	function c3m_create_schedule_meta() {
		global $post_ID;

    	$date = get_post_meta( $post_ID, '_schedule_date', TRUE );
		$options = c3m_get_options ();
		$times = $options[ 'c3m_hour_string' ];
		$times_available = explode ( ",", $times );
		$time_output = "Choose Time to publish<br/>";
		$time_output .= "<select class='time-div' name='c3m_sched_time' id='" . $post_ID . "' >\n";
		$time_output .= "\t<option value='-1'>" . esc_html ( 'Select Publish Time' ) . "</option>\n";

		foreach ( $times_available as $time ) {
			$time_output .= "\t<option value='$time'>" . esc_html ( $time ) . "</option>\n";

		}
		$time_output .= "</select>";
    	
    	echo '<div id="schedule" class="misc-pub-section" style="border-top-style:solid; border-top-width:1px; border-top-color:#EEEEEE; border-bottom-width:1px;">';
    	
    	if ( !$date ) {
    	$output = 'Choose Date to publish';
		$output .= "<input class='sched-div datepicker' type='text' name='c3m_sched_date' id='".$post_ID."' />\n";
		$output .= '<br /><br /><div id="sched_time_div">'.$time_output.'</div>';

		echo $output;
		echo '<p id="hidden-p"><a id="save-time" style="margin-left: 10px" class="button">Save</a></p>';

		} else {
		    if ( $date['time'] > 12 ) $pm = 'pm'; else $pm = 'am';

		    echo '<p style="padding-left: 10px;">Scheduled to publish on: <strong>' . $date['date'] . '</strong><br />';
		    echo 'At approx: <strong>' .  $date['time'].$pm. '</strong><br /></p>';
			 }
    	
    	echo '</div>';

	}

	function c3m_enqueue_scripts() {
		global $pagenow, $typenow;
		if ( ( $pagenow == 'post.php' || $pagenow == 'post-new.php' ) && $typenow == 'post' ) {
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_style ( 'jquery-ui-lightness', plugins_url( 'ui-lightness/jquery-ui-1.8.20.custom.css', __FILE__ )  );
		}

	}

	function c3m_echo_js() { 
		global $pagenow, $typenow;
  		if ( ( $pagenow=='post.php' || $pagenow=='post-new.php')   && $typenow=='post') {
			  $options = c3m_get_options ();
			  $dates = $options[ 'c3m_date_string' ];
			  $find = '/';
			  $replace = '-';
			  $dates = str_replace( $find, $replace, $dates );
			  $days = explode ( ",", $dates );
			  $year = date ( 'Y' );

			  ?>
	
		<script type="text/javascript">
			jQuery(document).ready(function() {
				jQuery("#publishing-action").hide();
				jQuery(".misc-pub-section-last").hide();
				jQuery("a#save-time").click(function() {
					var postID = jQuery("#post_ID").val();
					var pubDate = jQuery(".sched-div").val();
					var theTime = jQuery(".time-div option:selected").val();
					console.log( postID, pubDate, theTime );
					jQuery.ajax({
						type:'POST',
						url: ajaxurl,
						data: {"action": "save_pub_time", post_id: postID, sched: pubDate, time: theTime },
						success: function(response) {
							jQuery("#schedule").replaceWith(response);

						}
					});

					return false;

				});

				var enabledDays = [ <?php foreach( $days as $day ) {  ?>
				 "<?php  echo $day.'-'.$year; ?>",
			<?php  } ?>];

			function enableAllTheseDays(date) {
				var m = date.getMonth(), d = date.getDate(), y = date.getFullYear();
				for (i = 0; i < enabledDays.length; i++) {
					if (jQuery.inArray((m + 1) + '-' + d + '-' + y, enabledDays) != -1) {
						return [true, ''];
					}
				}
				return [false, ''];
			}
			jQuery('.datepicker').datepicker({
				dateFormat:'mm-dd-yy',
				beforeShowDay:enableAllTheseDays
			});
			});
	</script>

	<?php 	}
	}

	add_action ( 'wp_ajax_save_pub_time', 'c3m_ajax_save' );
	function c3m_ajax_save() {
		$post_id = $_POST[ 'post_id' ];
		$date = $_POST[ 'sched' ];
		$time = $_POST[ 'time' ];
		if ( $time > 12 ) $pm = 'pm'; else $pm = 'am';
		update_post_meta ( $post_id, '_schedule_date', array ( 'date' => $date, 'time' => $time ) );
		$output = '<p style="padding-left: 10px;">Scheduled to publish on: <strong>'.$date.'</strong><br />';
		$output .= 'At approx: <strong>'.$time. $pm.'</strong></p><br />';
		echo $output;

		die(1);
	}

	/**
	 * @return array
	 * Array
	 * (
	 * [c3m_hour_string] => 11,03,05,07
	 * [c3m_allowed_string] => 4
	 * [c3m_date_string] => 05/10,05/11,05/12
	 * )
     *
	 */
	
	 function c3m_get_options() {
		$c3m_options = get_option('c3m_options');
    	return $c3m_options;
	}

	add_action( 'admin_menu', 'c3m_create_menu' );
	function c3m_create_menu() {
		add_options_page( 'Manage Post Schedule', 'Manage Post Schedules', 'manage_options', 'post_schedules', 'c3m_schedule_options' );
	}

	function c3m_schedule_options() {
		echo '<div class="wrap">';
		echo '<h2>Manage Post Schedules</h2>';
		echo 'Manages the custom post scheduling options';
		echo '<form action="options.php" method="post">';
		settings_fields( 'c3m_options' );
		do_settings_sections( 'post_schedules' );
		echo '<input name="Submit" type="submit" class="button-primary" value="Save Changes" />';
		echo '</form></div>';

	}
	add_action( 'admin_init', 'c3m_plugin_init' );
	function c3m_plugin_init() {
		register_setting( 'c3m_options', 'c3m_options', 'c3m_validate' );
		add_settings_section( 'plugin_main', 'Post Schedule Dates and Times', 'settings_array', 'post_schedules' );
		add_settings_field( 'c3m_hour_string', 'Enter Post Publish Times (use 2 digit hours seperated by commas. ie 11,16,17  will publish at 11am, 4pm and 5pm):', 'c3m_hour_setting', 'post_schedules', 'plugin_main' );
		add_settings_field( 'c3m_allowed_string', 'Enter how many posts can be published at each time: ', 'c3m_allowed_setting', 'post_schedules', 'plugin_main' );
		add_settings_field( 'c3m_date_string', 'Enter Publish Dates (use month/day seperated by commas ie: 5/5,5/7 for May 5th and May 7th): ', 'c3m_date_setting', 'post_schedules', 'plugin_main' );
		add_settings_field( 'c3m_editor', 'click to load an editor', 'c3m_editor_setting', 'post_schedules', 'plugin_main' );
	}

	function settings_array() {
		echo '<p>Add post schedule date and time settings here</p>';
	}

	function c3m_hour_setting() {
		$options = get_option( 'c3m_options' );
		echo "<input id='c3m_hour_string' name='c3m_options[c3m_hour_string]' size='40' type='text' value='{$options['c3m_hour_string']}' />";
	}

	function c3m_allowed_setting() {
		$options = get_option( 'c3m_options' );
		echo "<input id='c3m_allowed_string' name='c3m_options[c3m_allowed_string]' size='40' type='text' value='{$options['c3m_allowed_string']}' />";
	}

	function c3m_date_setting() {
		$options = get_option( 'c3m_options' );
		echo "<input id='c3m_date_string' name='c3m_options[c3m_date_string]' size='40' type='text' value='{$options['c3m_date_string']}' />";
	}

	function c3m_validate( $input ) {
		$options = get_option( 'c3m_options' );
		$options['c3m_hour_string'] = trim( $input['c3m_hour_string'] );
		$options[ 'c3m_allowed_string' ] = trim ( $input[ 'c3m_allowed_string' ] );
		$options[ 'c3m_date_string' ] = trim ( $input[ 'c3m_date_string' ] );
		return $options;
		// Todo:  Create a real validate function
	}
        

?>