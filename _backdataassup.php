<?php
/*
Plugin Name: back data ass up
Plugin URI: 
Description: Requires PHP 5
Author: Eric Eaglstun
Author URI: http://ericeaglstun.com
Version: .6
*/

class backDataAssUp{
	private $wpdb;						// reference to global $wpdb
	
	private $backup_directory = '';		// default to /plugins/back-data-ass-up/backups/
	private $current_url = '';			// url the user is currently on
	private $file_name = '';			// file name of raw .sql file
	private $options = array();			// object
	private $plugin_version = .6;
	
	// rendering views
	private $template_dir = '';			// wp-content/plugins/back-data-ass-up
	private $template_file = '';		// the raw php file used in rendering the view
	private $vars = array();			// (object) variables used in the view
	private $view = '';					// the html output of the rendered view
	
	/*
	*
	*/
	public function __construct(){
		global $wpdb;
		$this->wpdb = &$wpdb;
		
		$this->template_dir = dirname( __FILE__ );
		$this->vars = (object) array();
		
		$this->options = array(
			'email' => (object) array( 'checked' => '', 'value' => '' ),
			'file' => (object) array( 'checked' => '', 'value' => '' ),
			'cronURL' => (object) array( 'checked' => '', 'value' => '' )
		);
		$this->loadSaveOptions();
		
		// running cron from external service / ping
		$current_url = isset( $_SERVER['HTTPS'] ) ? 'https://' : 'http://';
		$current_url .= $_SERVER['HTTP_HOST'];
		$current_url .= $_SERVER['REQUEST_URI'];
		$this->current_url = $current_url;
		
		if( strcasecmp($this->options->cronURL->value, $current_url) == 0 ){
			$this->backup();
			die();
		}
		
		add_action( 'admin_menu', array($this, 'adminBootstrap') );
		add_filter( 'plugin_action_links_'.plugin_basename(__FILE__), 'backDataAssUp::adminSettingsLink' );
	}
	
	/*
	*	callback for `admin_menu` action 
	*/
	public function adminBootstrap(){
		// show link to management page in admin 'tools' sidebar
		wp_enqueue_script( '_backdataassup.js', WP_PLUGIN_URL.'/back-data-ass-up/_backdataassup.js' );
		add_management_page( 'Database Backup', 'Database Backup Settings', 'activate_plugins', 'backdataassup', array($this,'adminManagementPage') );
		
		// selected tables
		if( isset($_POST['backdataassup_tables']) ){
			update_option( 'backdataassup_tables', $_POST['backdataassup_tables'] );
		}
		
		// save email and filesystem options
		if( isset($_POST['backdataassup_saveoptions']) ){
			$this->saveSaveOptions( $_POST['backdataassup_saveoptions'] );
		}
		
		// run the backup immediately
		if( isset($_POST['backdataassup_now']) ){
			$this->backup();
		}
		
		// save compression options
		if( isset($_POST['backdataassup_compression']) ){
			update_option( 'backdataassup_compression', $_POST['backdataassup_compression'] );
		}
		
		// delete a stored file
		if( isset($_POST['backdataassup_delete']) ){
			// only do one file, though it is posted as array (uses 'Delete' as value) TODO maybe use button type="submit" ?
			$keys = array_keys( $_POST['backdataassup_delete'] );
			$file = reset( $keys );
			$this->deleteFile( $file );
		}
		
		// delete multiple stored files
		if( isset($_POST['backdataassup_bulk']) && is_array($_POST['backdataassup_bulk']) && isset($_POST['backdataassup_bulkaction']) ){
			switch( $_POST['backdataassup_bulkaction'] ){
				case 'delete':
					foreach( $_POST['backdataassup_bulk'] as $file ){
						$this->deleteFile( $file );
					}
					break;
			}
		}
		
		// download sql to users computer
		if( isset($_GET['file']) ){
			$this->download( $_GET['file'] );
		}
		
		// get the options for saving
		$this->vars->options = $this->options;
	}
	
	/*
	*	display main admin settings page
	*/
	public function adminManagementPage(){
		// get the list of tables in database and those selected for backup
		$sql = "SHOW TABLES";
		$all_tables = $this->wpdb->get_col( $sql );
		$active_tables = (array) get_option( 'backdataassup_tables' );
		
		// why bother with no js? im that kind of guy
		if( isset($_GET['select']) && $_GET['select'] == 'all' )
			$select_all = TRUE;
		elseif( isset($_GET['select']) && $_GET['select'] == 'none' )
			$select_all = FALSE;
		else
			$select_all = NULL;
		
		foreach( $all_tables as &$table ){
			$sql = $this->wpdb->prepare( "SHOW TABLE STATUS LIKE %s", $table );
			
			$table = array(
				'table' => $table,
				'checked' => (in_array($table, $active_tables) && $select_all === NULL) 
							 ||
							 ( $select_all === TRUE && $select_all !== FALSE ) 
							 	? 'checked="checked"' 
							 	: ''
			);
			
			$table = (object) array_merge( $table, $this->wpdb->get_row($sql, ARRAY_A) );
			$table->size = $this->fileSize( $table->Data_length );
		}
		$this->vars->tables = $all_tables;
		
		// get the time of last backup
		$this->vars->lastrun = date( 'D M jS Y g:i:s a', get_option('backdataassup_lastrun') );
		
		// list of db archives on server
		$this->vars->db_files = $this->directoryContents();
		
		// get availale compression options and selected
		$this->vars->compression = $this->getCompressionOptions();
		$this->vars->plugin_version = $this->plugin_version;
		
		echo $this->render( 'admin.php' );
	}
	
	/*
	*	add direct link to 'Settings' in plugins.php
	*	@param array $links
	*	@return array
	*/ 
	static public function adminSettingsLink( $links ){
		$settings_link = '<a href="tools.php?page=backdataassup">Settings</a>';  
		array_unshift( $links, $settings_link );
		return $links;
	}
	
	/*
	*	run the backup, save the file, email if set
	*/
	public function backup(){
		set_time_limit( 600 );
		
		$blogname = get_option( 'blogname' );
		
		$this->file_name = sanitize_title( $blogname ).'_'.date('Y-m-d-His').'.sql';
		$this->file_path = $this->backup_directory.$this->file_name;
		
		// make sure backup directory exists
		if( !is_dir($this->backup_directory) ){
			mkdir( $this->backup_directory );
		}
		
		// make sure backup directory is safe
		if( !file_exists($this->backup_directory.'.htaccess') ){
			copy( $this->template_dir.'/_htaccess', $this->backup_directory.'.htaccess' );
		}
		
		// if file already exists something is wrong
		if( file_exists($this->file_path) ){
			return;
		}
		
		// create a blank file
		touch( $this->file_path );
		$fp = fopen( $this->file_path, 'a' );
		
		// write header information
		$this->vars->blogname = $blogname;
		$this->vars->plugin_version = $this->plugin_version;
		$this->vars->time = date( 'D M jS h:i:s a e', time() );
		$text = $this->render( '_header.txt' );
		fwrite( $fp, $text );
		
		// loop through tables and write
		$active_tables = get_option( 'backdataassup_tables' );
		foreach( $active_tables as $table ){
			$res = $this->wpdb->get_row( "SHOW CREATE TABLE `$table`" );
			if( !$res ) continue;
			
			// write the drop and create
			$this->vars->table_create = $res->{'Create Table'};
			$this->vars->table_name = $table;
			$text = $this->render( '_table_create.txt' );
			fwrite( $fp, $text );
			
			// write the contents of the table. do this 50 at a time to be safe w memory
			$page = 0;
			$limit = 50;
			do{
				$sql = "SELECT * FROM $table LIMIT $page, $limit";
				$res = $this->wpdb->get_results( $sql, ARRAY_A );
				
				if( count($res) ){
					foreach( $res as $row ){
						$this->vars->table_name = $table;
						$row = array_map( array($this,'escape'), $row );
						$row = implode( ', ', $row );
						$this->vars->table_values = $row;
						$text = $this->render( '_table_contents.txt' );
						fwrite( $fp, $text );
					}
				}
				
				$page += $limit;
				
			} while( count($res) );
			
			// write a closing comment
			$text = $this->render( '_table_end.txt' );
			fwrite( $fp, $text );
		}
		
		// write the footer
		$text = $this->render( '_footer.txt' );
		fwrite( $fp, $text );
		fclose( $fp );
		
		// mark as done
		update_option( 'backdataassup_lastrun', time() );
		
		// zip it danny
		$compression = (string) get_option( 'backdataassup_compression' );
		switch( $compression ){
			case 'gzip':
				$testFunction = 'gzopen';
				$method = 'compressFileGzip';
				break;
				
			case 'none':
			default:
				$testFunction = '';
				$method = '';
				break;
		}
		
		// see if we have a callable compression option, zip and delete original if ok
		if( function_exists($testFunction) && is_callable(array($this, $method)) ){
			$zip_file = $this->$method( $this->file_path, 9 );
			
			if( file_exists($zip_file) ){
				unlink( $this->file_path );
				$this->file_path = $zip_file;
			}
		}
		
		// email it if thats what we do
		if( $this->options->email->checked && $this->options->email->value ){
			$email_to = $this->options->email->value;
			$email_subject = 'Database Backup : '.get_option( 'blogname' );
			$email_message = 'Backup attached.';
			$email_headers = 'From: Database Backup <noreply@'.$_SERVER['HTTP_HOST'].'>';
			$email_attachments = array( $this->file_path );
			// TODO: this kills wordpress if the file is too big.  look into
			wp_mail( $email_to, $email_subject, $email_message, $email_headers, $email_attachments );
		}
	}
	
	/*
	*	delete a database backup file if it exists
	*	@param string $file
	*	@return bool
	*/
	private function deleteFile( $file ){
		$success = file_exists($this->backup_directory.$file) && unlink( $this->backup_directory.$file );
		return $success;
	}
	
	/*
	*	Callback for array_map for escaping a string, and wrapping with single quotes if needed, for writing to the .sql file
	*
	*	@param string $r
	*	@return string
	*/ 
	public function escape( $r ){
		$r = addcslashes( $r, "'" );
		if( is_null($r) ){
			$r = 'NULL';
		} else if( !is_numeric($r) ){
			$r = "'$r'";
		}
		
		return $r;
	}
	
	/*
	*	List the contents of the backup directory, hiding files starting with '.'
	*
	*	@return array
	*/ 
	private function directoryContents(){
		if( !is_dir($this->backup_directory) || !is_readable($this->backup_directory) ) return array();
		
		$files = array();
		$_d = opendir( $this->backup_directory );
		while( $fileName = readdir($_d) ){
			if( strpos($fileName,'.') !== 0 ){
				$file = (object) array( 'file' => $fileName, 
										'created' => date('D M jS Y g:i:s a', filectime($this->backup_directory.$fileName)),
										'stamp' => filectime($this->backup_directory.$fileName),
										'bytes' => filesize($this->backup_directory.$fileName) );
				$file->size = $this->fileSize( $file->bytes );
				array_push( $files, $file );
			}
		}
		
		// newest files first
		usort( $files, array($this, 'sortFiles') );
		
		return $files;
	}
	
	/*
	*
	*	@return NULL
	*/
	private function loadSaveOptions(){
		$options = (array) get_option( 'backdataassup_saveOptions' );
		$this->options = (object) array_merge( $this->options, $options );
		
		// set the directory for saved files
		if( trim($this->options->file->value) && is_dir($this->options->file->value) ){
			$this->backup_directory = $this->options->file->value;
		} else {
			$this->backup_directory = WP_PLUGIN_DIR.'/back-data-ass-up-backups/';
			$this->options->file->value = $this->backup_directory;
		}
		
		// set the cron url for external service
		if( !trim($this->options->cronURL->value) ){
			$cron_url = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
			$cron_url .= $_SERVER['HTTP_HOST'];
			$cron_url .= '/back-data-ass-up-cron?action=backdataassup&key='.sha1(microtime());
			$this->options->cronURL->value = $cron_url;
		}
		
		return;
	}
	
	/*
	*
	*	@return NULL
	*/
	private function saveSaveOptions( $options ){
		foreach( $options as $key=>$option ){
			if( isset($this->options->$key) ){
				$this->options->$key->checked = isset($option['checked']) ? 'checked="checked"' : '';
				$this->options->$key->value = $option['value'];
			}
		}
		
		update_option( 'backdataassup_saveOptions', $this->options );
	}
	
	/*
	*	usort callback to sort an array of files of their timestamp (filectime)
	*	@param object $a
	*	@param object $b
	*/
	private function sortFiles( $a, $b ){
		return $a->stamp < $b->stamp;
	}
	
	/*
	*	Force download the .sql file to the users computer, and die
	*	@param string $fileName
	*/
	private function download( $fileName ){
		if( !is_file($this->backup_directory.$fileName) ) return;
		
		header( 'Content-type: application/force-download' );
		header( 'Content-Disposition: attachment; filename="'.$fileName.'"' );
		echo readfile($this->backup_directory.$fileName);
		die();
	}
	
	/*
	*	
	*	@return array
	*/
	private function getCompressionOptions(){
		$options = array( 'none' => '' );
		
		if( function_exists('gzopen') ){
			$options['gzip'] = '';
		}
		
		$compression = (string) get_option( 'backdataassup_compression' );
		if( array_key_exists($compression, $options) ){
			$options[$compression] = 'checked="checked"';
		} else {
			$options['none'] = 'checked="checked"';
		}
		
		return $options;
	}
	
	/*
	*	Get a human friendly file size representation, in b, kb, mb etc
	*	adapted from http://www.php.net/manual/en/function.filesize.php#100097
	*	@param int $size
	*	@return string
	*/
	private function fileSize( $size = 1 ){
		$units = array(' B', ' KB', ' MB', ' GB', ' TB' );
	    for( $i = 0; $size >= 1024 && $i < 4; $i++ ) $size /= 1024;
	    return round( $size, 2 ).$units[$i];
	}
	
	// compression functions
	
	/*
	*	compress a file using gzip
	*	adapted from http://www.php.net/manual/en/function.gzwrite.php#34955
	*	@param string $source
	*	@param bool $level
	*	@return string
	*/
	private function compressFileGzip( $source, $level = FALSE ){
		$dest = $source.'.gz';
		$mode = 'wb'.$level;
		$error = FALSE;
		
		if( $fp_out = gzopen($dest,$mode) ){
			if( $fp_in = fopen($source,'rb') ){
				while( !feof($fp_in) ){
					gzwrite( $fp_out, fread($fp_in,1024*512) );
				}
				fclose( $fp_in );
			} else {
				$error = TRUE;
			}
			gzclose( $fp_out );
		} else {
			$error = TRUE;
		}
		
		if( $error ){
			return FALSE;
		}
		
		return $dest;
	} 
	
	/*
	*	render a php template using varaibles set in $this->vars or $vars
	*	@param sting $view
	*	@param object|array $vars optional
	*	@return string html
	*/
	private function render( $view = 'index.php', $vars = null ){
		$this->template_file = $view;
		
		if( !$vars ) $vars = $this->vars;
		$vars = (array) $vars;
		$this->view = '';
		
		// do the magic
		extract( $vars, EXTR_SKIP );
		ob_start();
			include $this->template_dir.'/'.$this->template_file;
			$this->view .= ob_get_contents();
		ob_end_clean();

		return $this->view;
	}
}

new backDataAssUp;