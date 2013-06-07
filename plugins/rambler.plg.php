<?php
$_pluginInfo = array(
	'name'             => 'Rambler',
	'version'          => '1.2.0',
	'description'      => "Get the contacts from a Rambler account",
	'base_version'     => '1.8.3',
	'type'             => 'email',
	'check_url'        => 'http://www.rambler.ru',
	'requirement'      => 'user',
	'allowed_domains'  => array('/(rambler.ru)/i'),
	'imported_details' => array(
    'first_name',
    'email_1'
  ),
);

/**
 * Rambler Plugin
 * 
 * Import user's contacts from Rambler AddressBook
 * 
 * @author BarBQ
 * @version 1.2.0
 */
class rambler extends openinviter_base
{
	private   $login_ok      = false;
	public    $showContacts  = true;
	public    $internalError = false;
	protected $timeout       = 30;
	
	public    $debug_array   = array(
    'initial_get'  => 'login',
    'login_post'   => 'ramac_add_handler',
    'url_contacts' => 'email'
	);
	
	/**
	 * Login function
	 * 
	 * Makes all the necessary requests to authenticate
	 * the current user to the server.
	 * 
	 * @param string $user The current user.
	 * @param string $pass The password for the current user.
	 * @return bool TRUE if the current user was authenticated successfully, FALSE otherwise.
	 */
	public function login($user,$pass)
	{
		$this->resetDebugger();
		$this->service          = 'rambler';
		$this->service_user     = $user;
		$this->service_password = $pass;

		if (!$this->init()) {
      return false;
    }
				
		$res=$this->get("http://www.rambler.ru/",true);
		
		if ($this->checkResponse("initial_get",$res)) {
      $this->updateDebugBuffer('initial_get',"http://www.rambler.ru/",'GET');
    }	else	{
			$this->updateDebugBuffer('initial_get',"http://www.rambler.ru/",'GET',false);
			$this->debugRequest();
			$this->stopPlugin();

			return false;
		}

		$post_elements                     = $this->getHiddenElements($res);
		$post_elements['profile.login']    = $user;
		$post_elements['profile.password'] = $pass;
		$post_elements['profile.domain']   = 'rambler.ru';
		$post_elements['button.submit']    = '';
		$post_elements['show']             = '';

		unset($post_elements[0]);

    $customHeaders  = array('User-Agent' => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:20.0) Gecko/20100101 Firefox/20.0');
    $res            = $this->post("http://id.rambler.ru/login", $post_elements, true, false, false, $customHeaders);

		$this->login_ok = 'http://mail.rambler.ru/jsonrpc2';

		return true;
	}

	/**
	 * Get the current user's contacts
	 * 
	 * Makes all the necesarry requests to import
	 * the current user's contacts
	 * 
	 * @return mixed The array if contacts if importing was successful, FALSE otherwise.
	 */	
	public function getMyContacts()
	{
		if (!$this->login_ok) {
			$this->debugRequest();
			$this->stopPlugin();

			return false;
		}

    $jsonData = '{"method":"Rambler::Mail::get_contacts","jsonrpc":"2.0","id":"136617668530575907","params":{}}';

    $customHeaders = array(
      'Content-Type'     => 'application/json; charset=UTF-8',
      'X-Requested-With' => 'XMLHttpRequest',
      'User-Agent'       => 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:20.0) Gecko/20100101 Firefox/20.0',
      'Accept: '         => 'application/json, text/javascript, */*; q=0.01',
      'Referer'          => 'http://mail.rambler.ru/',
     );

    $res = $this->post($this->login_ok, $jsonData, false, false, false, $customHeaders, true, false);

    if ($res) {
      $response = json_decode($res, true);

      if ($response['result']['status'] != 'OK') {
        return false;
      }

      $contactsData = $response['result']['addressbook']['contacts'];
    }

    $contacts = array();

    foreach ($contactsData as $contact) {
      $email = trim($contact['email']);

      if ($email) {
        $firstName        = $contact['first_name'];
        $lastName         = $contact['last_name'];
        $contacts[$email] = array(
          'first_name' => $firstName,
          'last_name'  => $lastName
        );
      }
    }

		return $this->returnContacts($contacts);
	}

	/**
	 * Terminate session
	 * 
	 * Terminates the current user's session,
	 * debugs the request and reset's the internal 
	 * debudder.
	 * 
	 * @return bool TRUE if the session was terminated successfully, FALSE otherwise.
	 */	
	public function logout()
	{
		if (!$this->checkSession()) {
      return false;
    }

    $this->get('http://id.rambler.ru/logout?back=http://rambler.ru/&rname=mail',true);

    $this->debugRequest();
		$this->resetDebugger();
		$this->stopPlugin();

		return true;
	}
}