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
            $act->extras = null;
            $act->target_obj = null;

            (++$cnt % 2 == 0) ? $act->rowclass = 'even' : $act->rowclass = 'odd';

            if (array_key_exists($act->application, $this->mvc->configuration->activity))
            {
                $storage = new midgard_query_storage($this->mvc->configuration->activity[$act->application]['object']);
                $q = new midgard_query_select($storage);

                $qc = new midgard_query_constraint(
                    new midgard_query_property($this->mvc->configuration->activity[$act->application]['guidfield']),
                    '=',
                    new midgard_query_value($act->target)
                );

                $q->set_constraint($qc);
                $q->execute();

                $targets = $q->list_objects();
                if (count($targets))
                {
                    $act->target_obj = $targets[0];

                    $act->target_link = call_user_func($this->mvc->configuration->activity[$act->application]['objectlink'], $targets[0]);

                    foreach ($this->mvc->configuration->activity[$act->application]['fields'] as $field)
                    {
                        $act->extras[] = array('title' => $field, 'content' => $targets[0]->$field);
                    }
                }
            }

            if (! $act->target_obj)
            {
                $act->target_obj = midgard_object_class::get_object_by_guid($act->target);
            }

            $this->data['list'][] = $act;
        }
        unset($acts, $act, $user, $person);
   }
}