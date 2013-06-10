<?php
$_pluginInfo=array(
	'name'             => 'AOL',
	'version'          => '1.6.0',
	'description'      => "Get the contacts from an AOL account",
	'base_version'     => '1.9.6',
	'type'             => 'email',
	'check_url'        => 'http://webmail.aol.com',
	'requirement'      => 'email',
	'allowed_domains'  => array('/(aol.com)/i'),
	'imported_details' => array(
    'nickname',
    'email_1',
    'email_2',
    'phone_mobile',
    'phone_home',
    'phone_work',
    'pager',
    'fax_work',
    'last_name'
  ),
);

/**
 * AOL Plugin
 * 
 * Imports user's contacts from AOL's AddressBook
 * 
 * @author BarBQ
 * @version 1.6.0
 */
class aol extends openinviter_base
{
	private   $login_ok      = false;
	public    $showContacts  = true;
	public    $internalError = false;
	protected $timeout       = 30;
	
	public    $debug_array   = array(
    'initial_get'=>'pwderr',
    'login_post'=>'Good Afternoon',
    'url_redirect'=>'var gSuccessURL',
    'inbox'=>'aol.wsl.afExternalRunAtLoad = []',
    'print_contacts'=>'Email1'
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
		$this->service='aol';
		$this->service_user=$user;
		$this->service_password=$pass;

		if (!$this->init()) {
      return false;
    }
		
		$res=$this->get("https://my.screenname.aol.com/_cqr/login/login.psp?sitedomain=sns.webmail.aol.com&lang=en&seamless=novl&offerId=newmail-en-us-v2&authLev=0&siteState=ver%3A4%7Crt%3ASTANDARD%7Cat%3ASNS%7Cld%3Awebmail.aol.com%7Cuv%3AAOL%7Clc%3Aen-us%7Cmt%3AANGELIA%7Csnt%3AScreenName%7Csid%3A8aae62d1-6c56-4948-9eb0-e4c2f82a012e&locale=us",true);

    if ($this->checkResponse('initial_get',$res)) {
			$this->updateDebugBuffer('initial_get',"https://my.screenname.aol.com/_cqr/login/login.psp?sitedomain=sns.webmail.aol.com&lang=en&seamless=novl&offerId=newmail-en-us-v2&authLev=0&siteState=ver%3A4%7Crt%3ASTANDARD%7Cat%3ASNS%7Cld%3Awebmail.aol.com%7Cuv%3AAOL%7Clc%3Aen-us%7Cmt%3AANGELIA%7Csnt%3AScreenName%7Csid%3A8aae62d1-6c56-4948-9eb0-e4c2f82a012e&locale=us",'GET');
    } else {
			$this->updateDebugBuffer('initial_get',"https://my.screenname.aol.com/_cqr/login/login.psp?sitedomain=sns.webmail.aol.com&lang=en&seamless=novl&offerId=newmail-en-us-v2&authLev=0&siteState=ver%3A4%7Crt%3ASTANDARD%7Cat%3ASNS%7Cld%3Awebmail.aol.com%7Cuv%3AAOL%7Clc%3Aen-us%7Cmt%3AANGELIA%7Csnt%3AScreenName%7Csid%3A8aae62d1-6c56-4948-9eb0-e4c2f82a012e&locale=us",'GET',false);
			$this->debugRequest();
			$this->stopPlugin();

			return false;
		}
			
		$post_elements = $this->getHiddenElements($res);$post_elements['loginId']=$user;$post_elements['password']=$pass;

		$res = $this->post("https://my.screenname.aol.com/_cqr/login/login.psp?sitedomain=sns.webmail.aol.com&lang=en&seamless=novl&offerId=newmail-en-us-v2&authLev=0&siteState=ver%3A4%7Crt%3ASTANDARD%7Cat%3ASNS%7Cld%3Awebmail.aol.com%7Cuv%3AAOL%7Clc%3Aen-us%7Cmt%3AANGELIA%7Csnt%3AScreenName%7Csid%3A8aae62d1-6c56-4948-9eb0-e4c2f82a012e&locale=us",$post_elements,true);

    if ($this->checkResponse('login_post',$res)) {
      $this->updateDebugBuffer('login_post',"https://my.screenname.aol.com/_cqr/login/login.psp",'POST',true,$post_elements);
    }	else{
			$this->updateDebugBuffer('login_post',"https://my.screenname.aol.com/_cqr/login/login.psp",'POST',false,$post_elements);	
			$this->debugRequest();
			$this->stopPlugin();

			return false;
		}

		$url_redirect = "http://mail.aol.com".$this->getElementString($res,'var gSuccessURL = "','"',$res);
		$url_redirect = str_replace("Suite.aspx","Lite/Today.aspx",$url_redirect);
		$res = $this->get($url_redirect,true);

		if ($this->checkResponse('inbox',$res)) {
      $this->updateDebugBuffer('inbox',"{$url_redirect}",'GET');
    }	else {
			$this->updateDebugBuffer('inbox',"{$url_redirect}",'GET',false);
			$this->debugRequest();
			$this->stopPlugin();

			return false;
		}
			
		$url_contact=$this->getElementDOM($res,"//a[@id='contactsLnk']",'href');

		$this->login_ok = $url_contact[0];

		file_put_contents($this->getLogoutPath(),$url_contact[0]);

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

    $res  = $this->get("http://mail.aol.com/37776-111/aol-6/en-us/Lite/ABExport.aspx?command=all");
    $temp = $this->parseCSV($res);

		$contacts = array();

		foreach ($temp as $values) {
			if (!empty($values[4])) {
				$contacts[$values[4]] = array(
          'first_name'            => (!empty($values[0])?$values[0]:false),
          'middle_name'           => (!empty($values[2])?$values[2]:false),
          'last_name'             => (!empty($values[1])?$values[1]:false),
          'nickname'              => false,
          'email_1'               => (!empty($values[4])?$values[4]:false),
          'email_2'               => false,
          'email_3'               => false,
          'organization'          => false,
          'phone_mobile'          => (!empty($values[11])?$values[11]:false),
          'phone_home'            => (!empty($values[9])?$values[9]:false),
          'pager'                 => false,
          'address_home'          => false,
          'address_city'          => (!empty($values[5])?$values[5]:false),
          'address_state'         => (!empty($values[7])?$values[7]:false),
          'address_country'       => (!empty($values[8])?$values[8]:false),
          'postcode_home'         => (!empty($values[6])?$values[6]:false),
          'company_work'          => (!empty($values[14])?$values[14]:false),
          'address_work'          => false,
          'address_work_city'     => (!empty($values[16])?$values[16]:false),
          'address_work_country'  => (!empty($values[19])?$values[19]:false),
          'address_work_state'    => (!empty($values[17])?$values[17]:false),
          'address_work_postcode' => (!empty($values[18])?$values[18]:false),
          'fax_work'              => (!empty($values[21])?$values[21]:false),
          'phone_work'            => (!empty($values[20])?$values[20]:false),
          'website'               => (!empty($values[12])?$values[12]:false),
          'isq_messenger'         => false,
          'skype_essenger'        => false,
          'yahoo_essenger'        => false,
          'msn_messenger'         => false,
          'aol_messenger'         => false,
          'other_messenger'       => false,
         );
      }
		}

		foreach ($contacts as $email=>$name) {
      if (!$this->isEmail($email)) {
        unset($contacts[$email]);
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

		if (file_exists($this->getLogoutPath())) {
			$url        = file_get_contents($this->getLogoutPath());
			$res        = $this->get($url,true);
			$url_logout = $this->getElementDOM($res,"//a[@class='signOutLink']",'href');

			if (!empty($url_logout)) {
        $this->get($url_logout[0]);
      }
		}

		$this->debugRequest();
		$this->resetDebugger();
		$this->stopPlugin();

		return true;
	}
}