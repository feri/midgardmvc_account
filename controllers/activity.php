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

        $q->add_order(new midgard_query_property('metadata.published'), SORT_DESC);
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
                try
                {
                    $act->target_obj = midgard_object_class::get_object_by_guid($act->target);
                }
                catch(Exception $e)
                {
                    $this->mvc->log(__CLASS__, 'Probably missing target of activity object: ' . $act->guid . '.', 'warning');
                }
            }

            $this->data['list'][] = $act;
        }
        unset($acts, $act, $user, $person);
    }

    /**
     * Creates an activity object
     */
    public function create_activity($person_guid, $verb, $target, $summary, $application, $date, $mvc = null)
    {
        $retval = false;

        if (! $mvc)
        {
            $mvc = midgardmvc_core::get_instance();
        }

        try
        {
            $person = new midgard_person($person_guid);
        }
        catch (midgard_error_exception $e)
        {
            $mvc->log(__CLASS__, 'Person with GUID: ' . $person_guid . ' does not exist. Can not create activity object.', 'error');
            return $retval;
        }

        $transaction = new midgard_transaction();
        $transaction->begin();

        // create new activity object
        $activity = new midgard_activity();
        $activity->actor = $person->id;
        $activity->verb = $verb;
        $activity->target = $target;
        $activity->summary = $summary;
        $activity->application = $application;

        if ($date)
        {
            $activity->metadata->published = $date;
        }

        $retval = $activity->create();
        if ($retval)
        {
            if ($date)
            {
                $mvc->log(__CLASS__, 'Activity object (guid: ' . $activity->guid . '): with verb: ' . $verb . ' successfully created for ' . $target, 'info');
                $transaction->commit();
            }
            else
            {
                $activity->metadata->published = $activity->metadata->created;
                $retval = $activity->update();
                if ($retval)
                {
                    $mvc->log(__CLASS__, 'Activity object (guid: ' . $activity->guid . '): with verb: ' . $verb . ' successfully created for ' . $target, 'info');
                    $transaction->commit();
                }
                else
                {
                    $mvc->log(__CLASS__, 'Failed to update the publishing date of activity object: ' . $activity->guid, 'warning');
                    $transaction->rollback();
                }
            }
        }
        else
        {
            $mvc->log(__CLASS__, 'Failed to create ' . $verb . ' activity object for ' . $target, 'error');
            $transaction->rollback();
        }

        unset($person);

        return $retval;
    }
}