<?php 
/* 
Plugin Name: WP Shorties
Version: 3.0
Plugin URI: http://softwarp.com
Description: Professional url shortener for affiliate link cloaking and click tracking. WPShorties makes the tiny URLs you always wanted to push affiliate offers. Get shortened URls in no time. Short and wellmasked URLs sell better and convert better.
Author: SoftWarp.com Team
Author URI: http://softwarp.com
*/

	$wpshort = new wp_short_class();
	
	class wp_short_class {
		var $site_url;
		var $plugin_url;
		
		function __construct(){
			register_activation_hook(__FILE__, array(&$this, '_plugin_activation'));
			register_deactivation_hook(__FILE__, array(&$this, '_plugin_deactivation'));
			add_action( 'plugins_loaded', array(&$this, '_upgrade_db'));
			
			add_action('admin_menu', array(&$this, '_admin_menus'));
			add_action('init', array(&$this, '_init'), 10000);
			add_filter('the_content', array(&$this, '_wp_short_url_check'), 10);
			if($this->_is_wpmu()){
				add_action('admin_notices', array(&$this, '_admin_notices_wpmu'), 1);
			}elseif($this->_is_multisite()){
				add_action('admin_notices', array(&$this, '_admin_notices_multisites'), 1);
			}
			add_filter( "plugin_action_links_" . plugin_basename( __FILE__ ), array( $this, '_add_settings_link') );			

			$this->site_url = get_option('siteurl');
			$this->plugin_page = basename(__FILE__);
			$this->plugin_url = WP_PLUGIN_URL . '/' . str_replace(basename( __FILE__), '', plugin_basename(__FILE__));
			$this->plugin_path = dirname(__FILE__) . '/';                
			$this->plugin_name = 'WP Shorties';
			$this->plugin_ver = '3.0';			
		}
		
		function _get_installed_version(){
			return get_option('wp_shorties_ver');
		}
		
		function _set_installed_version($version){
			update_option('wp_shorties_ver', $version);
		}
		
		function _plugin_activation(){
			global $wpdb;
			
			$table_name = $wpdb->prefix . 'shorties';
			$table_name_log = $wpdb->prefix . 'shorties_log';
			$charset_collate = $wpdb->get_charset_collate();

			$sql = "CREATE TABLE $table_name (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				url_short varchar(100) DEFAULT '' NOT NULL,
				url_long text NOT NULL,
				postdate datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				probability text NOT NULL,
				open int(50) NOT NULL,
				params varchar(255) DEFAULT '' NOT NULL,
				PRIMARY KEY  (id),
				KEY url_short (url_short)
			) $charset_collate;";

			$sql_log = "CREATE TABLE $table_name_log (
				id bigint(20) NOT NULL AUTO_INCREMENT,
				short_id bigint(20) NOT NULL,
				ip_address varchar(15) DEFAULT '' NOT NULL,
				referral_url varchar(255) DEFAULT '' NOT NULL,
				postdate datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
				long_url varchar(255) DEFAULT '' NOT NULL,
				PRIMARY KEY  (id),
				KEY short_id (short_id)
			) $charset_collate;";
			
			require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
			dbDelta( $sql );
			dbDelta( $sql_log );			
			
			$this->_set_installed_version($this->plugin_ver);
		}
		
		function _plugin_deactivation(){
			global $wpdb;			
			if( get_option('wp_shorties_delete') == 'yes' ){
				$wpdb->query("DROP TABLE `{$wpdb->prefix}shorties`");
				$wpdb->query("DROP TABLE `{$wpdb->prefix}shorties_log`");
				delete_option('wp_short_dir');
				delete_option('wp_short_purge_logs');
				delete_option('wp_shorties_ver');
				delete_option('wp_shorties_delete');
			}
		}
		
		function _upgrade_db(){
			$installed_ver = $this->_get_installed_version();			
			if ( version_compare( $installed_ver, '3.0' ) < 0 ) {
				$this->_plugin_activation();			
			}
		}
		
		function _add_settings_link($links){
			$settings_link = '<a href="options-general.php?page=' . $this->plugin_page . '">' . __( 'Settings' ) . '</a>';
			array_push( $links, $settings_link );
			return $links;
		}
		
		function _admin_notices_wpmu(){echo '<div class="error"><p><strong>WP Shorties:</strong> Sorry, WP Shorties does not support WPMU.</p></div>';}
		function _admin_notices_multisites(){echo '<div class="error"><p><strong>WP Shorties:</strong> Sorry, WP Shorties does not support wp multisite.</p></div>';}

		function _is_wpmu() {
			global $wp_version;
			if($wp_version >= 3.0) return FALSE;
			return (
				isset($GLOBALS['wpmu_version'])
				||
				(function_exists('wp_get_mu_plugins') && count(wp_get_mu_plugins()) > 0)
			);
		}

		function _is_multisite() {
			global $wp_version;
			if($wp_version < 3.0) return FALSE;
			return (
				(function_exists('is_multisite') && is_multisite())
				||
				(defined('MULTISITE') && MULTISITE == TRUE)
				||
				(defined('WP_ALLOW_MULTISITE') && WP_ALLOW_MULTISITE == TRUE)
			);
		}
		
		function _purge_logs(){
			global $wpdb;
			$wp_short_purge_logs = (int)abs(get_option('wp_short_purge_logs'));
			if($wp_short_purge_logs > 0){
				$wpdb->query("DELETE FROM `{$wpdb->prefix}shorties_log` where postdate < DATE_SUB(now(), INTERVAL " . $wp_short_purge_logs . " DAY)");
				$wpdb->query("OPTIMIZE TABLE `{$wpdb->prefix}shorties_log`");
			}
		}
		
		function _admin_head(){
			echo '<script type="text/javascript">//<![CDATA[	jQuery(document).ready(function(){jQuery("a.toplevel_page_top-menu-softwarp").removeAttr("href");jQuery("#toplevel_page_top-menu-softwarp li.wp-first-item").remove();});//]]></script>';
			echo '<style>#icon-top-menu-softwarp{background:url("images/icons32.png?ver=20100531") no-repeat scroll -492px -5px transparent;}.readme_menu{}.readme_content{font-family:"Courier New", Courier, monospace;}</style>';
		}
		
		
		function _admin_menus(){
			add_submenu_page('options-general.php', 'WP Shorties by SoftWarp.com', 'WP Shorties', 'manage_options', basename(__FILE__), array(&$this, '_manage'));
		}

		function _create($length = 3){
			if($length < 3) $length = 3;
			for($i=97; $i<122; $i++) $arrc[] = chr($i);
			for($i=0; $i<9; $i++) $arrn[] = $i;
			$o[] = $arrc[array_rand($arrc)];
			for($i = 1; $i < $length; $i++){$n = rand(0, 10); $o[] = ($n % 2) ? $arrn[array_rand($arrn)] : $arrc[array_rand($arrc)];}
			return implode('', $o);
		}
	
		function _init($posts){
			global $wpdb;
			
			$req_uri = $_SERVER['REQUEST_URI'];
			$wp_short_dir = trim(get_option('wp_short_dir'), '/');
			if($wp_short_dir == '?'){
				$wp_short_dir_url = '/?';
			}elseif($wp_short_dir){
				$wp_short_dir_url = '/' . $wp_short_dir . '/';
			}else{
				return;
			}
			if(strpos($req_uri, $wp_short_dir_url) !== false){
				//get short url
				if($wp_short_dir == '?'){
					$arr = explode('/?', $req_uri);
				}else{
					$arr = explode('/', $req_uri);
				}		

				$n = sizeof($arr);
				if($arr[$n - 1] && strpos($arr[$n - 1], '=') === false){
					//check db 
					$xx = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}shorties WHERE url_short LIKE %s limit 1", $arr[$n - 1]));
					
					if($xx->id){
						//get randomize long URL
						if(unserialize($xx->url_long)) 
							$long_url = unserialize($xx->url_long);
						else
							$long_url = array($xx->url_long);
							
						if(unserialize($xx->probability)) 
							$probability_orig = unserialize($xx->probability);
						else
							$probability_orig = array($xx->probability);
						
						$probability_arr = array();
						foreach($long_url as $url_index => $url_info){
							
							$prob_value = $probability_orig[$url_index];
							if($prob_value < 1) $prob_value = 1;
							for($j = 0; $j < $prob_value; $j++) $probability_arr[] = $url_index;
						}
							
						$rand_probability = array_rand($probability_arr);
						$rand_index = $probability_arr[$rand_probability];
							
						//random URL redirect
						$url = $long_url[$rand_index];
						
						//log
						$data = array('short_id' => $xx->id,
									'ip_address' => $_SERVER['REMOTE_ADDR'],
									'referral_url' => $_SERVER['HTTP_REFERER'],
									'long_url' => $url,
									'postdate' => date('Y-m-d H:i:s')
									);
						$wpdb->insert("{$wpdb->prefix}shorties_log", $data);
						
						//redirect
						wp_redirect( $url, 301 );
						exit; 
					}
				}
			}
		}
		
		function _wp_short_url_check($content){
			global $wpdb, $post;
			$long_url = array();
			$open_window = array();
			$probability = array();
				
			$dir = get_option('wp_short_dir');
			preg_match_all('/' . preg_quote('href="', '/') . '(.*?)' . preg_quote('/'.$dir.'/', '/') . '(.*?)' . preg_quote('">', '/') . '(.*?)' . preg_quote('</a>', '/') . '/si', $post->post_content, $result);	

			if(sizeof($result[2])){
				foreach($result[2] as $k => $v){
					$row = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$wpdb->prefix}shorties WHERE url_short = %s AND open > 0", $v) );
					if($row){
						$open_window = $row->open;
						$params = ($row->params) ? ' ' . $row->params . ' ' : '';
						
						if(unserialize($row->url_long)){ 
							$long_url = unserialize($row->url_long);
						}else{
							$long_url = array($row->url_long);
						}
						if(unserialize($row->probability)){ 
							$probability_orig = unserialize($row->probability);
						}else{
							$probability_orig = array($row->probability);
						}
						
						$probability_arr = array();
						foreach($long_url as $url_index => $url_info){	
							$prob_value = $probability_orig[$url_index];
							if($prob_value < 1) $prob_value = 1;
							for($j = 0; $j < $prob_value; $j++) $probability_arr[] = $url_index;
						}
						
						$open_js = '';
						if($open_window > 1){
							$open_js .= ' onclick="';
							for($xx=1; $xx<$open_window; $xx++){
									$rand_probability = array_rand($probability_arr);
									$rand_index = $probability_arr[$rand_probability];
									//random URL redirect
									$url = $long_url[$rand_index];
									$this->_remove_index_value($probability_arr,$rand_index);
									$open_js .= ' window.open(\''.$url.'\');';
							}
							$open_js .= ' return true;" ';
						}
						
						$str_to_find = $result[0][$k];
						$content = str_replace($str_to_find, $params . $open_js . $str_to_find, $content);
						
					}
				}
			}
			return $content;
		}

		function _remove_index_value(&$array, $index_value){
			foreach($array as $i => $p){
				if($p == $index_value)
					unset($array[$i]);
			}
		}
		
		function _screen_icon(){
			//fix for wp lower than 2.7
			//no longer used in 3.8+
			//if(function_exists('screen_icon')) screen_icon();
		}

		function _manage(){
			global $wpdb;
			//set the default view
			$goto = '';
			if(!isset($_GET['doc']) && !isset($_GET['data']) && !isset($_GET['logs']) && !isset($_GET['options'])){
				$perma = get_option('permalink_structure');
				if($perma == ''){
					$goto = 'option';
				}else{
					$goto = 'main';
				}
			}
			//purge the logs
			$this->_purge_logs();
	
			if(isset($_GET['doc'])){?>
				<div class="wrap">
					<?php $this->_screen_icon(); ?>
					<h2><?php echo $this->plugin_name; ?> <a href="options-general.php?page=<?php echo $this->plugin_page;?>&data">
						<?php _e('Main') ?>
						</a> | <a href="options-general.php?page=<?php echo $this->plugin_page;?>&logs">
						<?php _e('Logs') ?>
						</a> | <a href="options-general.php?page=<?php echo $this->plugin_page;?>&options">
						<?php _e('Options') ?>
						</a> |
						<?php _e('Usage Notes') ?>
					</h2>
					<table class="form-table">
						<tr valign="top">
							<td><img style="vertical-align:middle;" src="<?php echo $this->plugin_url; ?>images/wp-shorties.png" title="<?php echo $this->plugin_name . ' ' . $this->plugin_ver;?>" /></td>
							<td align="right"><a href="http://www.softwarp.com"><img style="vertical-align:middle;max-width:150px;" src="<?php echo $this->plugin_url; ?>images/softwarp-logo.gif" title="www.softwarp.com" /></a></td>
						</tr>
					</table>
					<?php include_once($this->plugin_path . 'help.txt');?>
				</div>
<?php
			}elseif(isset($_GET['data']) || $goto == 'main'){
?>
	<div class="wrap">
		<?php $this->_screen_icon(); 
		
			if ($_POST["action"] == "savedata") {
				$url_long = (array)$_POST['url_long'];
				$url_short = $_POST['url_short'];
				$window = (int)$_POST['open_window'];
				$params = stripslashes($_POST['params']);

				//check if not empty
				$filtered_url = array();
				foreach($url_long as $filter_url){
					if($filter_url)$filtered_url[] = $filter_url;
				}
				$url_long = $filtered_url;
				
				//filter the open window quantity
				if(empty($window) || $window > count($url_long)) $window = count($url_long);
								
				$id = (int)$_POST['id'];
				
				$probability = (array)$_POST['probability_url'] ;
				$probability = array_slice($probability, 0, sizeof($url_long));
				$success = $duplicate = 0;

				$url_long = serialize($url_long) ;
				$probability = serialize($probability);
				
				$data = array(
							'url_long' => $url_long, 
							'url_short' => $url_short, 
							'probability' => $probability, 
							'open' => $window, 
							'params' => $params,
							);
							
				if($id > 0 ){
					$wpdb->update("{$wpdb->prefix}shorties", $data, array('id' => $id) );					
					$success++;
				}else{
					if($url_long && $url_short != ''){
						//doublecheck duplication
						$check = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}shorties WHERE url_short LIKE '" . $url_short . "'");
						if(empty($check)){
							$data['postdate'] = current_time( 'mysql' );
							$wpdb->insert("{$wpdb->prefix}shorties", $data );
							$success++;
						}else{
							$duplicate++;
						}
					}
				}
				
				if($success > 0 && $duplicate == 0){
					echo '<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>Data saved.</strong></p></div>';	
				}elseif($success == 0 && $duplicate > 0){
					echo '<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>Duplicate data.</strong></p></div>';	
				}elseif($success > 0 && $duplicate > 0){
					echo '<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>Data saved.</strong></p></div>';	
				}else{
					echo '<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>Blank data.</strong></p></div>';	
				}
			}
	
			if ($_GET["action"] == "del") {
				$id = (int)$_GET['id'];
				if($id){
					$wpdb->query("DELETE FROM {$wpdb->prefix}shorties WHERE id = '{$id}' LIMIT 1");
					echo	'<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>Data deleted.</strong></p></div>';	
				}
			}
	?>
		<h2><?php echo $this->plugin_name; ?>
			<?php _e('Main') ?>
			| <a href="options-general.php?page=<?php echo $this->plugin_page;?>&logs">
			<?php _e('Logs') ?>
			</a> | <a href="options-general.php?page=<?php echo $this->plugin_page;?>&options">
			<?php _e('Options') ?>
			</a> | <a href="options-general.php?page=<?php echo $this->plugin_page; ?>&doc">
			<?php _e('Usage Notes'); ?>
			</a></h2>
		<table class="form-table">
			<tr valign="top">
				<td><img style="vertical-align:middle;" src="<?php echo $this->plugin_url; ?>images/wp-shorties.png" title="<?php echo $this->plugin_name . ' ' . $this->plugin_ver;?>" /></td>
				<td align="right"><a href="http://www.softwarp.com"><img style="vertical-align:middle;max-width:150px;" src="<?php echo $this->plugin_url; ?>images/softwarp-logo.gif" title="www.softwarp.com" /></a></td>
			</tr>
			<tr valign="top">
				<td style="text-align:right" colspan="2" valign="bottom"><p class="search-box"> </p>
					<form method="get" action="options-general.php">
						<input type="hidden" name="page" value="wp-shorties.php" />
						<input type="hidden" name="data" value="" />
						<label for="number_of_rows_per_page">
							<?php _e( 'Rows' ); ?>
							: </label>
						<input type="text" class="search-input" id="number_of_rows_per_page" name="number_of_rows_per_page" size="3" value="<?php echo ((int)$_GET['number_of_rows_per_page']>0) ? (int)$_GET['number_of_rows_per_page'] : 15; ?>" />
						|
						<label for="search">
							<?php _e( 'Search' ); ?>
							:</label>
						<input type="text" class="search-input" id="search" name="search" value="<?php echo urldecode($_GET['search']);?>" />
						<input type="submit" value="<?php _e( 'GO' ); ?>" class="button" />
					</form></td>
			</tr>
		</table>
		<table class="widefat" cellspacing="0">
			<thead>
				<tr>
					<th scope="col" class="manage-column">Long URL</th>
					<th scope="col" class="manage-column">Short URL</th>
					<th scope="col" class="manage-column" title="Additional link attributes">Attributes</th>
					<th scope="col" class="manage-column" title="Probability (%)">Prob(%)</th>
					<th scope="col" class="manage-column" title="# of links to open"># Open</th>
					<th scope="col" class="manage-column">Postdate</th>
					<th scope="col" class="manage-column">Logs</th>
					<th scope="col" class="manage-column">Actions</th>
				</tr>
			</thead>
			<tbody>
<?php
		$sql = "SELECT * FROM `{$wpdb->prefix}shorties` where 1 ";
		$search = $wpdb->escape(urldecode($_GET['search']));
		if($search){
			$sql .= " and (url_long like '%{$search}%' or url_short like '%{$search}%') ";
		}
		$sql .= " order by postdate desc ";
		
		$num_rows = $wpdb->query($sql);
		$number_of_rows_per_page = ((int)$_GET['number_of_rows_per_page']>0) ? (int)$_GET['number_of_rows_per_page'] : 15;
		$num_pages = ceil($num_rows / $number_of_rows_per_page);
		
		$paged = (int)$_GET['paged'];
		if($paged <= 0) $paged = 1;
		if ($paged > $num_pages) $paged = $num_pages;
	
		$page_links = paginate_links( array(
			'base' => 'options-general.php?page=' . $this->plugin_page . '&data=&number_of_rows_per_page=' . $number_of_rows_per_page . '&search=' . urlencode(stripslashes($search)) . '%_%',
			'format' => '&paged=%#%',
			'prev_text' => __('&laquo;'),
			'next_text' => __('&raquo;'),
			'total' => $num_pages,
			'current' => $paged
		));
		
		$offset = ($number_of_rows_per_page * ($paged - 1));
		$sql .= " limit " . max($offset, 0) . ", " . $number_of_rows_per_page;
	
		$wp_short_dir = trim(get_option('wp_short_dir'), '/');
		if($wp_short_dir == '?'){
			$wp_short_dir = '/?%s';
		}elseif($wp_short_dir){
			$wp_short_dir = '/' . $wp_short_dir . '/%s';
		}
	
		$query = $wpdb->get_results($sql);
		
		$i = 0;
		$prob_arr = array();
		$prob_val = array();	
		foreach ($query as $line) {
			$open_window = $line->open;
			$url_long = $line->url_long;
			
			if(unserialize($url_long)){
				$url_long = unserialize($url_long);
			}else{
				$url_long = array($url_long);
			}
			
			$url_long_arr = array();
			foreach($url_long as $key_url_long){
					$url_long_arr[] = (strlen($key_url_long) > 70) ? substr(urldecode($key_url_long), 0, 30) . '...' . substr(urldecode($key_url_long), -30) : urldecode($key_url_long);
			}
			
			$url_long = implode("\n",$url_long_arr);
			$url_long = nl2br($url_long);
			
			$probability = $line->probability;
			$probability = unserialize($probability);
			if(!is_array($probability)) $probability = array($probability);
			
			//get qty
			$qty = $wpdb->get_row("select count(*) as qty from `{$wpdb->prefix}shorties_log` where short_id = '" . $line->id . "' ");
	?>
				<tr>
					<td><?php echo stripslashes($url_long);?></td>
					<td><?php if($wp_short_dir){?>
						<a target="_blank" href="<?php echo $this->site_url . sprintf($wp_short_dir, stripslashes($line->url_short)); ?>"><?php echo sprintf($wp_short_dir, stripslashes($line->url_short));?></a>
						<?php }else{ _e('Please configure the option!');}?>
					</td>
					<td><?php echo stripslashes($line->params); ?></td>
					<td><?php echo implode("<br>",$probability); ?></td>
					<td><?php echo $open_window; ?></td>
					<td><?php echo date('M d, Y', strtotime($line->postdate));?></td>
					<td><?php echo number_format((int)$qty->qty);?></td>
					<td><a onclick="return wp_shorties_cf()" href="options-general.php?page=<?php echo $this->plugin_page;?>&data=&number_of_rows_per_page=<?php echo $number_of_rows_per_page;?>&search=<?php echo urlencode(stripslashes($search));?>&id=<?php echo $line->id;?>&action=del">Del</a> | <a href="options-general.php?page=<?php echo $this->plugin_page;?>&data=&number_of_rows_per_page=<?php echo $number_of_rows_per_page;?>&search=<?php echo urlencode(stripslashes($search));?>&id=<?php echo $line->id;?>&action=edit">Edit</a></td>
				</tr>
				<?php	
		}
	?>
			</tbody>
		</table>
		<?php if ( $page_links ) { ?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<?php $page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s' ) . '</span>%s',
				number_format_i18n( ( $paged - 1 ) * $number_of_rows_per_page + 1 ),
				number_format_i18n( min( $paged * $number_of_rows_per_page, $num_rows ) ),
				number_format_i18n( $num_rows ),
				$page_links); 
				echo $page_links_text; ?>
			</div>
		</div>
		<?php } ?>
		<br />
		
		<?php $this->_screen_icon(); ?>
		<?php if($_GET['action'] == 'edit' && $_GET['id'] > 0){?>
		<h2>
			<?php _e('Update Shorties') ?>
		</h2>
		<?php }else{ ?>
		<h2>
			<?php _e('Add Shorties') ?>
		</h2>
		<?php } ?>		
		
		<form method="post" action="options-general.php?page=<?php echo $this->plugin_page;?>&data=&number_of_rows_per_page=<?php echo $number_of_rows_per_page;?>">
			<input type="hidden" name="action" value="savedata" />
			<script language="JavaScript" type="text/javascript">
	function wp_shorties_cf(){
		return confirm('Are you sure want to delete it?');
	}
	function wp_shorties_oc(idx){
		var sh ;
		sh = '<?php echo $this->_create(5);?>';
		var ush = document.getElementById('url_short');
		var usl = document.getElementById('url_long_' + idx);
		if(usl.value && !ush.value) ush.value = sh;
	}
	
	var i=0;
	var j=1;
	function add(){
		 i++;
		var td = document.createElement('tr');
		td.innerHTML = '<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td><input type="text" id="url_long_'+i+'" name="url_long[]" value="" style="width:100%;" onchange="wp_shorties_oc('+i+');" onblur="wp_shorties_oc('+i+');" /></td><td><input style="width:55%;" type="text" name="probability_url['+i+']" /> %</td>';
		 document.getElementById('add_field').appendChild(td);
	}
	function edit_add(){
		 j++;
		var td = document.createElement('tr');
		td.innerHTML = '<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td><input type="text" id="url_long_'+j+'" name="url_long[]" value="" style="width:100%;" onchange="wp_shorties_oc('+j+');" onblur="wp_shorties_oc('+j+');" /></td><td><input style="width:55%;" type="text" name="probability_url['+j+']" /> %</td>';
		 document.getElementById('add_field').appendChild(td);
	}
	</script>
	
			<table class="widefat" cellspacing="0" id="widefat">
				<thead>
					<tr>
						<th scope="col" class="manage-column">Short URL</th>
						<th scope="col" class="manage-column">Attributes</th>
						<th scope="col" class="manage-column" title="# of links to open"># Open</th>
						<th scope="col" class="manage-column">Long URL</th>
						<th scope="col" class="manage-column">Probability</th>
					</tr>
				</thead>
				<tbody id="add_field">
				
<?php 
		if($_GET['action'] == 'edit' && $_GET['id'] > 0){
			$row = $wpdb->get_row("SELECT * FROM `{$wpdb->prefix}shorties` where id='" . (int)$_GET['id'] . "' ");
			echo '<input type="hidden" name="id" value="' . (int)$_GET['id'] . '">';
			
			if(unserialize($row->url_long)){
				$url_long = unserialize($row->url_long);
			}else{
				$url_long = array($row->url_long);
			}
			
			$probability = unserialize($row->probability) ;
			
			foreach($url_long as $xid => $url){
				echo '<tr>';
				if($xid == 0){
					echo '<td rowspan="' . sizeof($url_long) . '"><input type="text" id="url_short" name="url_short" value="' . $row->url_short . '"/></td>
					<td rowspan="' . sizeof($url_long) . '"><input type="text" name="params" value="' . htmlentities(stripslashes($row->params)) . '"/></td>
					<td rowspan="' . sizeof($url_long) . '"><input type="text" name="open_window" value="' . (int)$row->open . '"/></td>';
				}
				
				echo '<td><input type="text" name="url_long[]" value="' . $url . '"/></td><td><input type="text" name="probability_url[]" value="' . $probability[$xid] . '" /> %</td>';
				
				echo '</tr>';
			}
?>
			<tr>
				<td colspan="3">&nbsp;</td>
				<td colspan="2"><span onclick="edit_add();" name="add" value="Add Field" style="cursor:pointer;">More long URL </span></td>
			</tr>
<?php }else{ ?>
				<tr>
					<td><input type="text" id="url_short" name="url_short" value="" placeholder="leave it blank for auto"/></td>
					<td><input type="text" name="params" value=""/></td>
					<td><input type="text" name="open_window" value="1"/></td>
					<td><input type="text" id="url_long_0" name="url_long[]" value="" style="width:100%;" onchange="wp_shorties_oc(0);" onblur="wp_shorties_oc(0);" /></td>
					<td><input style="width:55%;text-align:center;" type="text" name="probability_url[0]" />%</td>
				</tr>
				<tr>
					<td colspan="3">&nbsp;</td>
					<td colspan="2"><span onclick="add();" name="add" value="Add Field" style="cursor:pointer;">More long URL </span></td>
				</tr>
<?php } ?>
				</tbody>
			</table>
			<p class="submit" style="text-align: right !important;"><input type="submit" name="Submit" class="button-primary" value="<?php _e('Save') ?>" /></p>
		</form>
	</div>	
<?php		
	}elseif(isset($_GET['logs'])){
?>
	<script language="JavaScript" type="text/javascript">
	function wp_shorties_date(){
		document.search_log.search.value = '<?php echo date('Y-m-d');?>';
		return false;
	}
	</script>
	<div class="wrap">
		<?php $this->_screen_icon(); ?>
		<h2><?php echo $this->plugin_name; ?> <a href="options-general.php?page=<?php echo $this->plugin_page;?>&data">
			<?php _e('Main') ?>
			</a> |
			<?php _e('Logs') ?>
			| <a href="options-general.php?page=<?php echo $this->plugin_page;?>&options">
			<?php _e('Options') ?>
			</a> | <a href="options-general.php?page=<?php echo $this->plugin_page; ?>&doc">
			<?php _e('Usage Notes'); ?>
			</a></h2>
		<table class="form-table">
			<tr valign="top">
				<td><img style="vertical-align:middle;" src="<?php echo $this->plugin_url; ?>images/wp-shorties.png" title="<?php echo $this->plugin_name . ' ' . $this->plugin_ver;?>" /></td>
				<td align="right"><a href="http://www.softwarp.com"><img style="vertical-align:middle;max-width:150px;" src="<?php echo $this->plugin_url; ?>images/softwarp-logo.gif" title="www.softwarp.com" /></a></td>
			</tr>
			<tr valign="top">
				<td style="text-align:right" colspan="2" valign="bottom"><p class="search-box"> </p>
					<form name="search_log" method="get" action="options-general.php">
						<input type="hidden" name="page" value="<?php echo $this->plugin_page;?>" />
						<input type="hidden" name="logs" value />
						<label for="number_of_rows_per_page">
							<?php _e( 'Rows' ); ?>
							: </label>
						<input type="text" class="search-input" id="number_of_rows_per_page" name="number_of_rows_per_page" size="3" value="<?php echo ((int)$_GET['number_of_rows_per_page']>0) ? (int)$_GET['number_of_rows_per_page'] : 15; ?>" />
						|
						<label for="search">
							<?php _e( 'Search' ); ?>
							:</label>
						<input type="text" class="search-input" id="search" name="search" value="<?php echo urldecode($_GET['search']);?>" />
						<a href="#" onclick="wp_shorties_date()"><img src="images/date-button.gif" /></a>&nbsp;&nbsp;&nbsp;
						<input type="submit" value="<?php _e( 'GO' ); ?>" class="button" />
					</form></td>
			</tr>
		</table>
		<table class="widefat" cellspacing="0">
			<thead>
				<tr>
					<th scope="col" class="manage-column" style="">Date</th>
					<th scope="col" class="manage-column" style="">Short URL</th>
					<th scope="col" class="manage-column" style="width:30%">Long URL</th>
					<th scope="col" class="manage-column" style="">IP Address</th>
					<th scope="col" class="manage-column" style="width:30%"">Referrer</th>
				</tr>
			</thead>
			<tbody>
				<?php
		$sql = "SELECT sl.*, s.url_short FROM `{$wpdb->prefix}shorties_log` sl, `{$wpdb->prefix}shorties` s where sl.short_id = s.id ";
		$search = $wpdb->escape(urldecode($_GET['search']));
		if($search){
			if(substr_count($search, '-') == 2){
				$sql .= " and date(sl.postdate) = '" . date('Y-m-d', strtotime($search)) . "' ";
			}else{
				$sql .= " and (s.url_short like '%{$search}%' or s.url_long like '%{$search}%' or s.ip_address like '%{$search}%') ";
			}
		}
		$sql .= " order by sl.postdate desc ";
		
		$num_rows = $wpdb->query($sql);
		$number_of_rows_per_page = ((int)$_GET['number_of_rows_per_page']>0) ? (int)$_GET['number_of_rows_per_page'] : 15;
		$num_pages = ceil($num_rows / $number_of_rows_per_page);
		
		$paged = (int)$_GET['paged'];
		if($paged <= 0) $paged = 1;
		if ($paged > $num_pages) $paged = $num_pages;
	
		$page_links = paginate_links( array(
			'base' => 'options-general.php?page=' . $this->plugin_page . '&number_of_rows_per_page=' . $number_of_rows_per_page . '&search=' . urlencode(stripslashes($search)) . '%_%',
			'format' => '&paged=%#%',
			'prev_text' => __('&laquo;'),
			'next_text' => __('&raquo;'),
			'total' => $num_pages,
			'current' => $paged
		));
		
		$offset = ($number_of_rows_per_page * ($paged - 1));
		$sql .= " limit " . max($offset, 0) . ", " . $number_of_rows_per_page;
	
		$query = $wpdb->get_results($sql);
		foreach ($query as $line) {
			$referral_url = $line->referral_url;
	?>
				<tr>
					<td><?php echo date('M d, Y H:i:s',strtotime($line->postdate));?></td>
					<td><?php echo stripslashes($line->url_short);?></td>
					<td><?php echo stripslashes($line->long_url);?></td>
					<td><?php echo stripslashes($line->ip_address);?></td>
					<td><?php echo $referral_url;?></td>
				</tr>
				<?php	
		}
	?>
			</tbody>
		</table>
		<?php if ( $page_links ) { ?>
		<div class="tablenav">
			<div class="tablenav-pages">
				<?php $page_links_text = sprintf( '<span class="displaying-num">' . __( 'Displaying %s&#8211;%s of %s' ) . '</span>%s',
		number_format_i18n( ( $paged - 1 ) * $number_of_rows_per_page + 1 ),
		number_format_i18n( min( $paged * $number_of_rows_per_page, $num_rows ) ),
		number_format_i18n( $num_rows ),
		$page_links
	); echo $page_links_text; ?>
			</div>
		</div>
		<?php } ?>
	</div>
	<?php	
			}elseif($goto == 'option' || $goto == ''){?>
	<div class="wrap">
		<?php $this->_screen_icon(); ?>
		<h2><?php echo $this->plugin_name; ?> <a href="options-general.php?page=<?php echo $this->plugin_page;?>&data">
			<?php _e('Main') ?>
			</a> | <a href="options-general.php?page=<?php echo $this->plugin_page;?>&logs">
			<?php _e('Logs') ?>
			</a> |
			<?php _e('Options') ?>
			| <a href="options-general.php?page=<?php echo $this->plugin_page;?>&doc">
			<?php _e('Usage Notes') ?>
			</a></h2>
		<?php
			if ($_POST["action"] == "saveconfiguration"){
				update_option('wp_short_dir', $_POST['wp_short_dir']);
				update_option('wp_short_purge_logs', $_POST['wp_short_purge_logs']);
				update_option('wp_shorties_delete', ($_POST['wp_shorties_delete'] == 'yes') ? 'yes' : 'no');
	
				echo	'<div class="updated fade" id="message" style="background-color: rgb(255, 251, 204);"><p><strong>Settings saved.</strong></p></div>';	
			}
	?>
		<form method="post" action="options-general.php?page=<?php echo $this->plugin_page;?>&options">
			<input type="hidden" name="action" value="saveconfiguration" />
			<table class="form-table">
				<tr valign="top">
					<td><img style="vertical-align:middle;" src="<?php echo $this->plugin_url; ?>images/wp-shorties.png" title="<?php echo $this->plugin_name . ' ' . $this->plugin_ver;?>" /></td>
					<td align="right"><a href="http://www.softwarp.com"><img style="vertical-align:middle;max-width:150px;" src="<?php echo $this->plugin_url; ?>images/softwarp-logo.gif" title="www.softwarp.com" /></a></td>
				</tr>
			
			</table>
			<table class="form-table">
				<tr valign="top">
					<th scope="row"><?php _e('<b>Directory</b>') ?></th>
					<td><p>Define directory for shortened urls to be processed in. No forward and trailing slash.</p>
						<br />
						<?php
		$perma = get_option('permalink_structure');
		if($perma == ''){?>
						<div style="color:#FF3333; border:solid 1px #AE0426; padding:5px;">You are using default permalink setting! In order to make redirection work,<br />
							please use only ? (question mark) in box below, otherwise you will get 404 (page not found) error.</div>
						<br />
						<br />
						<?php }?>
						<?php if(!get_option('wp_short_dir')){update_option('wp_short_dir', 'go');} ?>
						<?php echo $this->site_url . '/';?>
						<input type="text" name="wp_short_dir" id="wp_short_dir" value="<?php echo get_option('wp_short_dir')?>" />
						/short_url.htm </td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('<b>Logs</b>') ?></th>
					<td><fieldset>
							<legend class="hidden">
							<?php _e('Logs') ?>
							</legend>
							<p>
								<label for="wp_short_purge_logs">
									<?php _e('How often you want stats purged? Default is blank or zero (no purge). If you set to 30, it will purge the logs over 30 days old.') ?>
									<br />
									<br />
									<input name="wp_short_purge_logs" type="text" id="wp_short_purge_logs" size="3" value="<?php echo (int)abs(get_option('wp_short_purge_logs'));?>" />
									days</label>
							</p>
						</fieldset></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php _e('<b>Deactivation</b>') ?></th>
					<td><fieldset>
							<legend class="hidden">
							<?php _e('Deactivation') ?>
							</legend>
							<p>
									<?php _e('Clear all database records related to WP-Shorties during plugin deactivation?') ?>
									<br />
									<br />
									<label><input name="wp_shorties_delete" type="radio" value="yes" <?php echo (get_option('wp_shorties_delete') == 'yes') ? 'checked' : '';?> /> Yes, please clear them</label><br>
									<label><input name="wp_shorties_delete" type="radio" value="no" <?php echo (get_option('wp_shorties_delete') == 'no' || !get_option('wp_shorties_delete')) ? 'checked' : '';?> /> No, keep the database </label>
							</p>
						</fieldset></td>
				</tr>				
			</table>
			<p class="submit" style="margin-left:230px;">
				<input type="submit" name="Submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>
	</div>
</div>
<?php		
			}
		}
	}//end of class
?>
