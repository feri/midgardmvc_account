<?php
class midgardmvc_account_injector
{
    var $mvc = null;
    var $request = null;
    var $connected = false;

    public function __construct()
    {
        $this->mvc = midgardmvc_core::get_instance();
    }

    /**
     * Process injector
     */
    public function inject_process(midgardmvc_core_request $request)
    {
        $request->add_component_to_chain($this->mvc->component->get('midgardmvc_account'), true);

        static $connected = false;

        if (! $connected)
        {
            // Connect to signals
            $connected = $this->get_connected();
        }
    }

    /**
     * Some template hack
     */
    public function inject_template(midgardmvc_core_request $request)
    {
        // todo
    }

    /**
     * Hooks callbacks to various midgard core signals
     */
    public function get_connected()
    {
        midgard_object_class::connect_default('midgardmvc_core_login_session', 'action-created', array('midgardmvc_account_injector', 'create_account_from_session'));
        midgard_object_class::connect_default('midgard_person', 'action-updated', array('midgardmvc_account_injector', 'update_account'));
        midgard_object_class::connect_default('midgard_person', 'action-deleted', array('midgardmvc_account_injector', 'delete_account'));

        $this->mvc->log(__CLASS__, "Signals connected", 'info');

        /**
         * Todo: move this to testing
         *
         * $this->mvc->authorization->enter_sudo('midgardmvc_core');
         * $person = $this->create_person();
         * $this->delete_person($person);
         * $this->mvc->authorization->leave_sudo();
         *
         */

        return true;
    }

    /**
     * Create an account object
     */
    public function create_account_from_session(midgardmvc_core_login_session $session)
    {
        $mvc = midgardmvc_core::get_instance();
        $mvc->log(__CLASS__, "Person logged in: " . $session->username . ' (GUID: ' . $session->userid . ')', 'info');

        $person = $mvc->authentication->get_user()->get_person();

        if ($person instanceof midgard_person)
        {
            $mvc->log(__CLASS__, "Session authentication done through " . $session->authtype, 'info');

            $account = self::get_account($person->guid);

            if ($account)
            {
                $account->firstname = $person->firstname;
                $account->lastname = $person->lastname;

                switch ($session->authtype)
                {
                    case 'LDAP':
                        $connection = self::ldap_connect();
                        if ($connection)
                        {
                            $userinfo = self::get_user_info($connection, $session->username);
                            ldap_close($connection);
                        }

                        if (   is_array($userinfo)
                            && array_key_exists('email', $userinfo))
                        {
                            $account->email = $userinfo['email'];
                        }

                        if (   $mvc->configuration->avatar_provider == 'gravatar'
                            && $account->email)
                        {
                            $hash = md5(strtolower(trim($account->email)));
                            $account->avatarurl = $mvc->configuration->avatar_host . '/' . $hash . '.jpg';
                            $account->avatarurl .= '?s=' . $mvc->configuration->avatar_width;
                        }
                        break;
                    default:
                }
            }

            if ($account->guid)
            {
                if ($account->update())
                {
                    $mvc->log(__CLASS__, "Account update succeeded: " . $account->guid, 'info');
                }
                else
                {
                    $error = $mvc->get_error_string();
                    $mvc->log(__CLASS__, "Account update failed: " . $error, 'info');
                }
            }
            else
            {
                $account->personguid = $person->guid;
                if ($account->create())
                {
                    $mvc->log(__CLASS__, "Account created: " . $account->guid, 'info');
                }
                else
                {
                    $error = $mvc->get_error_string();
                    $mvc->log(__CLASS__, "Account creation failed: " . $error, 'info');
                }
            }
        }
    }


    /**
     * Checks if an account exists with the given person guid
     * If not it returns a new midgardmvc_account object
     * @param guid
     * @return object
     */
    public function get_account($personguid = null)
    {
        $retval = null;

        $storage = new midgard_query_storage('midgardmvc_account');
        $q = new midgard_query_select($storage);

        $qc = new midgard_query_constraint(
            new midgard_query_property('personguid'),
            '=',
            new midgard_query_value($personguid)
        );

        $q->set_constraint($qc);
        $q->toggle_readonly(false);
        $q->execute();

        $accounts = $q->list_objects();

        if (count($accounts))
        {
            $retval = new midgardmvc_account($accounts[0]->guid);
        }
        else
        {
            $retval = new midgardmvc_account();
        }

        return $retval;
    }


    /**
     * Connects to LDAP
     *
     * @return connection
     */
    private function ldap_connect()
    {
        $ldap_settings = midgardmvc_core::get_instance()->configuration->services_authentication_ldap;

        $server = $ldap_settings['server'];

        $ds = ldap_connect($server);

        if ($ds)
        {
            ldap_set_option($ds, LDAP_OPT_PROTOCOL_VERSION, 3);
        }

        return $ds;
    }


    /**
     * Get user info from LDAP
     *
     * @param ldap connection
     * @param string login name to look for in LDAP
     * @return array with user information
     */
    private function get_user_info($connection = null, $username = null)
    {
        $userinfo = midgardmvc_core::get_instance()->authentication->ldap_search($connection, $username);
        return $userinfo;
    }

    /**
     * Create an account object
     */
    public function create_account(midgard_person $person)
    {
        midgardmvc_core::get_instance()->log(__CLASS__, "Person with guid: " . $person->guid . ' created', 'info');

        $qb = new midgard_query_builder('midgard_user');
        $qb->add_constraint('auth_type', '=', 'LDAP');
        $qb->add_constraint('person', '=', $person->guid);
        $users = $qb->execute();

        if (count($users))
        {
           $user = $users[0];
        }
        unset($qb);

        if ($user)
        {
            $account = new midgardmvc_account();
            $account = $person;
        }
    }

    /**
     * Update an account object
     */
    public function update_account(midgard_person $person)
    {
        midgardmvc_core::get_instance()->log(__CLASS__, "Person with guid: " . $person->guid . ' updated', 'info');
    }

    /**
     * Update an account object
     */
    public function delete_account(midgard_person $person)
    {
        midgardmvc_core::get_instance()->log(__CLASS__, "Person with guid: " . $person->guid . ' deleted', 'info');
    }

    /**
     * Just a test method
     * @todo: move it to tests
     */
    private function create_person()
    {
        # create the person object
        $person = new midgard_person();
        $person->firstname = 'firstname';
        $person->lastname = 'lastname';

        if ( ! $person->create() )
        {
            $error = midgard_connection::get_instance()->get_error_string();
            $this->mvc->log(__CLASS__, "Failed to create midgard person: " . $error, 'error');
            return false;
        }
        else
        {
            $this->mvc->log(__CLASS__, "Created midgard person: " . $person->guid, 'info');

            $user = new midgard_user();
            $user->login = 'username_' . time();
            $user->password = '';
            $user->usertype = 1;

            $user->authtype = 'LDAP';
            $user->active = true;
            $user->set_person($person);

            if ( ! $user->create() )
            {
                $error = midgard_connection::get_instance()->get_error_string();
                $this->mvc->log(__CLASS__, "Failed to create midgard user: " . $error, 'error');
            }

            $this->mvc->log(__CLASS__, "Created midgard user: " . $user->login, 'info');
        }

        return $person;
    }

    /**
     * Just delete a person
     * @todo: move it to tests
     */
    private function delete_person($person)
    {
        if ($person instanceof midgard_person)
        {
            if (! $person->delete() )
            {
                $error = midgard_connection::get_instance()->get_error_string();
                $this->mvc->log(__CLASS__, "Failed to delete midgard person: " . $error, 'error');
            }
            else
            {
                $this->mvc->log(__CLASS__, "Deleted midgard person: " . $person->guid, 'info');
            }

            $qb = new midgard_query_builder('midgard_user');
            $qb->add_constraint('auth_type', '=', 'LDAP');
            $qb->add_constraint('person', '=', $person->guid);
            $users = $qb->execute();

            if (count($users))
            {
               $user = $users[0];
            }
            unset($qb);

            if (! $user->delete() )
            {
                $error = midgard_connection::get_instance()->get_error_string();
                $this->mvc->log(__CLASS__, "Failed to delete midgard user: " . $error, 'error');
            }
            else
            {
                $this->mvc->log(__CLASS__, "Deleted midgard user: " . $user->login, 'info');
            }
        }
    }
}