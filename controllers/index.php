<?php
class midgardmvc_account_controllers_index
{
    var $mvc = null;
    var $isuser = false;
    var $isadmin = false;
    var $request = null;

    public function __construct(midgardmvc_core_request $request)
    {
        $this->request = $request;

        $this->mvc = midgardmvc_core::get_instance();
        $this->mvc->authorization->require_user();
        $this->mvc->i18n->set_translation_domain('midgardmvc_account');

        $default_language = $this->mvc->configuration->default_language;

        if (! isset($default_language))
        {
            $default_language = 'en_US';
        }

        $this->mvc->i18n->set_language($default_language, false);
    }

    /**
     * Get the user's account index page
     */
    public function get_index(array $args)
    {
        $user = $this->mvc->authentication->get_user();
    }
}