<?php
class midgardmvc_account_controllers_activity
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
     * Get the user's activity list
     */
    public function get_list(array $args)
    {
        $cnt = 0;
        $this->data['list'] = false;

        $user = $this->mvc->authentication->get_user();
        $person = new midgard_person($user->person);

        $storage = new midgard_query_storage('midgard_activity');
        $q = new midgard_query_select($storage);

        $q->set_constraint(new midgard_query_constraint(
            new midgard_query_property('actor'),
            '=',
            new midgard_query_value($person->id)
        ));

        $q->toggle_readonly(false);
        $q->execute();

        $acts = $q->list_objects();

        foreach ($acts as $act)
        {
            (++$cnt % 2 == 0) ? $act->rowclass = 'even' : $act->rowclass = 'odd';
            $act->target_obj = midgard_object_class::get_object_by_guid($act->target);
            $this->data['list'][] = $act;
        }
        unset($acts, $act, $user, $person);
   }
}