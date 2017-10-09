<?php

class TriggerOnObjectUpdate extends TriggerOnObject
{
    public static function Init()
    {
        $aParams = array
        (
            "category" => "core/cmdb,bizmodel",
            "key_type" => "autoincrement",
            "name_attcode" => "description",
            "state_attcode" => "",
            "reconc_keys" => array('description'),
            "db_table" => "priv_trigger_onobjupdate",
            "db_key_field" => "id",
            "db_finalclass_field" => "",
            "display_template" => "",
        );
        MetaModel::Init_Params($aParams);
        MetaModel::Init_InheritAttributes();
        MetaModel::Init_AddAttribute(new AttributeString("tracked_attcodes", array("allowed_values" => null, "sql" => "tracked_attcodes", "default_value" => null, "is_null_allowed" => true, "depends_on" => array())));

        // Display lists
        MetaModel::Init_SetZListItems('details', array('description', 'target_class', 'filter', 'tracked_attcodes', 'action_list')); // Attributes to be displayed for the complete details
        MetaModel::Init_SetZListItems('list', array('finalclass', 'target_class', 'tracked_attcodes')); // Attributes to be displayed for a list
        // Search criteria
        MetaModel::Init_SetZListItems('standard_search', array('description', 'target_class')); // Criteria of the std search form
        //MetaModel::Init_SetZListItems('advanced_search', array('name')); // Criteria of the advanced search form
    }
}

class TriggerOnObjectUpdatePlugIn implements iApplicationObjectExtension
{
    //private $check_id = 0;
    private $aChangedAttcodes = [];

    public function OnIsModified($oObject)
    {
        return false;
    }
    public function OnCheckToWrite($oObject)
    {
        //$this->check_id += 1;
        //IssueLog::Info('check ' . $this->check_id);
        $aNewAttcodes = array_fill_keys(array_keys($oObject->ListChanges()), true);
        //IssueLog::Info('aNewAttcodes: ' . var_export($aNewAttcodes, true));
        $this->aChangedAttcodes = array_merge($aNewAttcodes, $this->aChangedAttcodes);
        //IssueLog::Info('aChangedAttcodes: ' . var_export($this->aChangedAttcodes, true));
        return array();
    }
    public function OnCheckToDelete($oObject)
    {
        return array();
    }

    /**
     *
     * ListChanges не работает внутри OnDBUpdate (хз почему, не стал копать).
     * @param DBObject $oObject
     * @param null $oChange
     */
    public function OnDBUpdate($oObject, $oChange = null)
    {
        if (!is_object($oChange)) return;
        //IssueLog::Info('update '.$this->check_id);
        $aFilteredAttcodes = array_keys(array_filter($this->aChangedAttcodes)); // select only 'attcode' => true
        //IssueLog::Info('aFilteredAttcodes: ' . var_export($aFilteredAttcodes, true));
        if (empty($aFilteredAttcodes)) return;
        array_walk($this->aChangedAttcodes, function (&$item) { $item = false; });
        //IssueLog::Info('aChangedAttcodes: ' . var_export($this->aChangedAttcodes, true));
        $sClassList = implode("', '", MetaModel::EnumParentClasses(get_class($oObject), ENUM_PARENT_CLASSES_ALL));
        $sRegExp = '^ *$|' . implode('|', $aFilteredAttcodes); // '^ *$' - regexp для пустого поля tracked_attcodes (именно с пробелом)
        $oSet = new DBObjectSet(DBObjectSearch::FromOQL("SELECT TriggerOnObjectUpdate WHERE target_class IN ('$sClassList') AND tracked_attcodes REGEXP '$sRegExp'"));
        if ($oSet->Count() > 0) {
            $aChangeLog = array(); // лог изменения как в истории
            $aContextArgs = array(); // аргументы контекста для использования в уведомлении в виде $change->html(log)$
            $oFilter = DBObjectSearch::FromOQL("SELECT CMDBChangeOpSetAttribute WHERE attcode IN ('" . implode("','", $aFilteredAttcodes) . "')");
            // $oFilter->AddCondition('attcode', $aFilteredAttcodes, 'IN'); // так бросает exception, а через OQL выше работает
            $oFilter->AddCondition('objkey', $oObject->GetKey(), '=');
            $oFilter->AddCondition('objclass', get_class($oObject), '=');
            $oFilter->AddCondition('change', $oChange->GetKey(), '=');
            $oChangeOpSet = new DBObjectSet($oFilter);
            while ($oChangeOp = $oChangeOpSet->Fetch()) {
                $aChangeLog[] = $oChangeOp->GetDescription();
                $aContextArgs['change->userinfo'] = $oChangeOp->Get('userinfo');
                $aContextArgs['change->date'] = $oChangeOp->Get('date');
            }
            $aContextArgs['change->log'] = strip_tags(implode(" ", $aChangeLog));
            $aContextArgs['change->html(log)'] = "<ul><li>" . implode('</li><li>', $aChangeLog) . "</li></ul>";
            foreach ($aContextArgs as $key => $val) {
                $aContextArgs[str_replace('->', '-&gt;', $key)] = $val;
            }
            while ($oTrigger = $oSet->Fetch()) {
                $oTrigger->DoActivate(array_merge($oObject->ToArgs('this'), $aContextArgs));
            }
        }
    }
    public function OnDBInsert($oObject, $oChange = null)
    {
    }
    public function OnDBDelete($oObject, $oChange = null)
    {
    }
}
