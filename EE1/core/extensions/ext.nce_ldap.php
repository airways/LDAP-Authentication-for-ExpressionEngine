<?php  if ( ! defined('EXT') ) exit('Invalid file request');
/**
 * LDAP Authentication
 *
 * ### EE 1.6 version ###
 *
 * Based on: NCE LDAP
 *           http://code.google.com/p/ee-ldap-extension/
 * License: "if you've used this module and found that it needed something then please hand it back so that it can be shared with the world"
 * Site: http://code.google.com/p/ee-ldap-extension/wiki/Introduction
 *
 * An ExpressionEngine Extension that allows the authentication of users via LDAP
 * LDAP details are copied to the EE database before standard MySQL authentication is performed
 * If user is not found on LDAP, MySQL authentication will still be performed (useful for EE users not in LDAP)
 *
 * Dependancy: iconv PHP module
 *
 * @package DesignByFront
 * @author  Alistair Brown 
 * @author  Alex Glover
 * @link    http://github.com/designbyfront/LDAP-Authentication-for-ExpressionEngine
 * @since   Version 0.1
 *
 * Enhancements to original:
 *  - Upgraded to EE2
 *  - Non-LDAP user login (remove restriction)
 *  - Authentication even with LDAP server downtime (remove restriction)
 *  - Use EE global classes (rather then PHP global variables)
 *  - DB protection against injection (however unlikely)
 *  - Better code structure using functions
 *  - More settings control:
 *     -> Custom admin email message
 *  - Notifications:
 *     Adds to session data $_SESSION['messages']['LDAP Authentication'] which can be used later for notification purposes
 *  - Use of character encoding for sent data (and ability to change in settings)
 *     PHP uses 'UTF-8' encoding; Windows server uses 'Windows-1252' encoding.
 *     Using the iconv PHP module, settings data saved in 'UTF-8' is dynamically encoded to 'Windows-1252' when being sent.
 *
 */

class Nce_ldap {

	var $settings       = array();
	var $name           = 'LDAP authentication';
	var $version        = '1.1.0';
	var $description    = 'Handles LDAP login / account creation';
	var $settings_exist = 'y';
	var $docs_url       = 'http://github.com/designbyfront/ZenDesk-Dropbox-for-ExpressionEngine/issues';
	var $debug          = FALSE;

// If you are looking here to edit the settings, you can always just change them in Extensions settings page :)
	var $admin_email               = 'admin@your_site.com'; // Change your_site.com to your sites domain
	var $from_email                = 'ldap@your_site.com'; // Change your_site.com to your sites domain
	var $mail_host                 = 'your_mail_host'; // Change your_mail_host to name / ip address of your mail host
	var $mail_message              = "This is an automated message from the ExpressionEngine LDAP authentication system.\n-------------------------\n\n{name} has just logged in for the first time. This has created an ExpressionEngine account for them using their LDAP details.\nTo complete their account details, please log in to http://{host} and update their member group, profile and 'people' weblog entry.\nTheir username is: {username}";
	var $use_ldap_account_creation = 'yes';
	var $ldap_host                 = 'ldap://your_ldap_host'; // Change your_ldap_host to name / ip address of your LDAP host
	var $ldap_port                 = '389'; // Change if your LDAP port is different
	var $ldap_search_base          = 'ldap_search_base'; // Change to your LDAP search base
	var $ldap_search_user          = 'ldap_search_user'; // Change to your LDAP search user
	var $ldap_search_password      = 'ldap_search_password'; // Change to your LDAP search password
	var $ldap_username_attribute   = 'ldap_username'; // Change to your LDAP username
	var $ldap_character_encode     = 'Windows-1252';
	var $no_ldap_login_message     = 'LDAP authentication seems to be down at the moment. Please contact your administrator.';
	var $first_time_login_message  = 'This is your first time logging in! Your account has been automatically created for you, but your administrator may still need to alter your settings. Please contact them if you require more access.';

	// PHP4 Constructor
	function Nce_ldap($settings = '')
	{
		$this->settings = $settings;
	}


// ----------------------


	/**
	* EE method called when the extension is activated
	*/
	function activate_extension ()
	{
		global $DB;

		$settings = array();
		$settings['admin_email']               = $this->admin_email;
		$settings['from_email']                = $this->from_email;
		$settings['mail_host']                 = $this->mail_host;
		$settings['mail_message']              = $this->mail_message;
		$settings['use_ldap_account_creation'] = $this->use_ldap_account_creation;
		$settings['ldap_host']                 = $this->ldap_host;
		$settings['ldap_port']                 = $this->ldap_port;
		$settings['ldap_search_base']          = $this->ldap_search_base;
		$settings['ldap_search_user']          = $this->ldap_search_user;
		$settings['ldap_search_password']      = $this->ldap_search_password;
		$settings['ldap_username_attribute']   = $this->ldap_username_attribute;
		$settings['ldap_character_encode']     = $this->ldap_character_encode;
		$settings['no_ldap_login_message']     = $this->no_ldap_login_message;
		$settings['first_time_login_message']  = $this->first_time_login_message;

		$hooks = array(
			'login_authenticate_start'  => 'login_authenticate_start',
			'member_member_login_start' => 'member_member_login_start'
		);

		foreach ($hooks as $hook => $method)
		{
			$DB->query($DB->insert_string('exp_extensions',
				array(
					'extension_id' => '',
					'class'        => __CLASS__,
					'method'       => $method,
					'hook'         => $hook,
					'settings'     => serialize($settings),
					'priority'     => 10,
					'version'      => $this->version,
					'enabled'      => "y"
				)
			));
		}
	}


// ----------------------


	/**
	* EE method called when the extension is updated
	*/
	function update_extension($current = '')
	{
		global $DB;

		if ($current == '' OR $current == $this->version)
			return FALSE;

		$DB->query('UPDATE exp_extensions SET version = \''.$DB->escape_str($this->version).'\' WHERE class = \''.$DB->escape_str(__CLASS__).'\'');
	}


// ----------------------


	/**
	* EE method called when the extension is disabled
	*/
	function disable_extension()
	{
		global $DB;

		$DB->query('DELETE FROM exp_extensions WHERE class = \''.$DB->escape_str(__CLASS__).'\'');
	}


// ----------------------


	/**
	* Configuration for the extension settings page
	*/
	function settings()
	{
		$settings = array();
		$settings['ldap_host']                 = $this->ldap_host;
		$settings['ldap_port']                 = $this->ldap_port;
		$settings['ldap_search_base']          = $this->ldap_search_base;
		$settings['ldap_search_user']          = $this->ldap_search_user;
		$settings['ldap_search_password']      = $this->ldap_search_password;
		$settings['ldap_username_attribute']   = $this->ldap_username_attribute;
		$settings['ldap_character_encode']     = $this->ldap_character_encode;
		$settings['use_ldap_account_creation'] = array('r', array('yes' => 'yes_ldap_account_creation',
                                                               'no'  => 'no_ldap_account_creation'),
                                                    'yes');
		$settings['admin_email']               = $this->admin_email;
		$settings['from_email']                = $this->from_email;
		$settings['mail_host']                 = $this->mail_host;
		$settings['mail_message']              = array('t', $this->mail_message);
		$settings['no_ldap_login_message']     = array('t', $this->no_ldap_login_message);
		$settings['first_time_login_message']  = array('t', $this->first_time_login_message);

		return $settings;
	}


// ----------------------


	/**
	 * Called by the member_member_login_start hook
	 */
	function member_member_login_start()
	{
		return $this->login_authenticate_start();
	}


// ----------------------


	/**
	 * Called by the login_authenticate_start hook
	 */
	function login_authenticate_start()
	{
		global $IN;

		$provided_username = $IN->GBL('username', 'POST');
		$provided_password = $IN->GBL('password', 'POST');

		$connection = $this->create_connection();
		$result = $this->authenticate_user($connection, $provided_username, $provided_password);

		if ($this->debug)
		{
			echo'<pre>';
			var_dump($result);
			echo'</pre>';
		}

		if ($result['authenticated'])
		{
			$this->sync_user_details($result);
		}
		else
		{
			$this->debug_print('Could not authenticate username \''.$provided_username.'\' with LDAP');
		}
		$this->close_connection($connection);

		if ($this->debug)
			exit();

	}


// ----------------------


	function sync_user_details($user_info)
	{
			global $FNS, $DB;
			// Sync EE password to match LDAP (if account exists)
			$encrypted_password = $FNS->hash(stripslashes($user_info['password']));
			$sql = 'UPDATE exp_members SET password = \''.$DB->escape_str($encrypted_password).'\' WHERE username = \''.$DB->escape_str($user_info['username']).'\'';
			$this->debug_print('Updating user with SQL: '.$sql);
			$DB->query($sql);

			// now we might want to do some EE account creation
			if ($this->settings['use_ldap_account_creation'] === 'yes')
			{
				$this->create_ee_user($user_info, $encrypted_password);
			}

	}


// ----------------------


	function create_ee_user($user_info, $encrypted_password)
	{
		global $LOC, $FNS, $DB, $STAT, $SESS, $LANG;

		$sql = 'SELECT \'username\' FROM exp_members WHERE username = \''.$DB->escape_str($user_info['username']).'\'';
		$query = $DB->query($sql);

		// user doesn't exist in exp_members table, so we will create an EE account
		if (sizeof($query->row) === 0)
		{
			$this->debug_print('Using LDAP for account creation...');
			$unique_id = $FNS->random('encrypt');
			$join_date = $LOC->now;

			$sql = 'INSERT INTO exp_members SET '.
						 'username = \''.$DB->escape_str($user_info['username']).'\', '.
						 'password = \''.$DB->escape_str($encrypted_password).'\', '.
						 'unique_id = \''.$DB->escape_str($unique_id).'\', '.
						 'group_id = \'6\', '.
						 'screen_name = \''.$DB->escape_str($user_info['cn'][0]).'\', '.
						 'email = \''.$DB->escape_str($user_info['mail'][0]).'\', '.
						 'ip_address = \'0.0.0.0\', '.
						 'join_date = \''.$DB->escape_str($join_date).'\', '.
						 'language = \'english\', '.
						 'timezone = \'UTC\', '.
						 'time_format = \'eu\'';

			$this->debug_print('Inserting user with SQL: '.$sql);
			$query = $DB->query($sql);
			
			$member_id = $DB->insert_id;
			if ($member_id > 0) // update other relevant fields
			{
				$sql = 'UPDATE exp_members SET photo_filename = \'photo_'.$member_id.'.jpg\', photo_width = \'90\', photo_height = \'120\'';
				$query = $DB->query($sql);

				$DB->query('INSERT INTO exp_member_data SET member_id = '.$DB->escape_str($member_id));
				$DB->query('INSERT INTO exp_member_homepage SET member_id = '.$DB->escape_str($member_id));

				$STAT->update_member_stats();

				$this->settings['mail_message'] = str_replace('{name}',     $user_info['cn'][0],   $this->settings['mail_message']);
				$this->settings['mail_message'] = str_replace('{username}', $user_info['username'],       $this->settings['mail_message']);
				$this->settings['mail_message'] = str_replace('{host}',     $_SERVER['HTTP_HOST'], $this->settings['mail_message']);

				// Email the admin with the details of the new user
				ini_set('SMTP', $this->settings['mail_host']);
				$headers = 'From: '.$this->settings['from_email']."\r\n" .
									 'X-Mailer: PHP/' . phpversion();
				$success = mail(
													$this->settings['admin_email'], 
													'New member \''.$user_info['username'].'\' on http://'.$_SERVER['HTTP_HOST'],
													$this->settings['mail_message'],
													$headers
												);
				session_start();
				$_SESSION['messages']['LDAP Authentication'] = $this->settings['first_time_login_message'];
			}
			else
			{
				exit('Could not create user account for '.$user_info['username'].'<br/>'."\n");
			}
		}
	}


// ----------------------


	function authenticate_user($conn, $username, $password)
	{
		global $SESS, $FNS, $EXT, $IN;

		$this->debug_print('Searching for attribute '.$this->settings['ldap_username_attribute'].'='.$username.' ...');
		// Search username entry
		$result = ldap_search($conn, $this->settings['ldap_search_base'], $this->settings['ldap_username_attribute'].'='.$username);
		$this->debug_print('Search result is: '.$result);

		// Search not successful (server down?), so do nothing - standard MySQL authentication can take over
		if ($result === FALSE)
		{
			session_start();
			$_SESSION['messages']['LDAP Authentication'] = $this->settings['no_ldap_login_message'];
			return array('authenticated' => false);
		}

		$this->debug_print('Number of entires returned is '.ldap_count_entries($conn, $result));
		// username not found, so do nothing - standard MySQL authentication can take over
		if (ldap_count_entries($conn, $result) < 1)
		{
			return array('authenticated' => false);
		}

		$this->debug_print('Getting entries for \''.$username.'\' ...');
		$info = ldap_get_entries($conn, $result); // entry for username found in directory, retrieve entries
		$user_info = $info[0];
		$this->debug_print('Data for '.$info["count"].' items returned<br/>');

		$user_info['username'] = $username;
		$user_info['password'] = $password;
		// Authenticate LDAP user against password submitted on login
		$dn = $user_info['dn'];
		$success = @ldap_bind($conn, $dn, $this->ldap_encode($password)); // bind with user credentials

		if (!$success) 
		{
			$this->debug_print('Error binding with supplied password (dn: '.$dn.') ERROR: '.ldap_error($conn));
		}

		$user_info['authenticated'] = $success;
		return $user_info;
	}


// ----------------------


	function create_connection()
	{
		$this->debug_print('Connecting to LDAP...');
		$conn = ldap_connect($this->settings['ldap_host'], $this->settings['ldap_port']) or
			die('Could not connect to host: '.$this->settings['ldap_host'].':'.$this->settings['ldap_port'].'<br/>'."\n");
		$this->debug_print('connect result is '.$conn);

		// Perform bind with search user
		if (empty($this->settings['ldap_search_user']))
		{
			$this->debug_print('Binding anonymously...');
			$success = ldap_bind($conn); // this is an "anonymous" bind, typically read-only access
		}
		else
		{
			$this->debug_print('Binding with user: '.$this->settings['ldap_search_user'].' ...'); 
			$success = ldap_bind($conn, $this->ldap_encode($this->settings['ldap_search_user']), $this->ldap_encode($this->settings['ldap_search_password'])); // bind with credentials
		}
		$this->debug_print('Bind result is '.$success);
		return $conn;
	}


// ----------------------


	function close_connection($conn)
	{
		$this->debug_print('Closing connection...');
		ldap_close($conn) or
			die('Could not close the LDAP connection<br/>'."\n");
	}


// ----------------------


	function debug_print($message, $br="<br/>\n")
	{
		if ($this->debug)
		{
			if (is_array($message))
			{
				print('<pre>');
				print_r($message);
				print('</pre>'.$br);
			}
			else
			{
				print($message.' '.$br);
			}
		}
	}


	function ldap_encode($text)
	{
		return iconv("UTF-8", $this->settings['ldap_character_encode'], $text);
	}


}
// END CLASS Nce_ldap

/* End of file ext.nce_ldap.php */
/* Location: ./system/extensions/ext.nce_ldap.php */