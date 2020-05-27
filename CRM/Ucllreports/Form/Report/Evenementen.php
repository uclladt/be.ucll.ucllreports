<?php
use CRM_Ucllreports_ExtensionUtil as E;

class CRM_Ucllreports_Form_Report_Evenementen extends CRM_Report_Form {
  function __construct() {
    $this->_columns = array(
      'civicrm_event' => array(
        'dao' => 'CRM_Event_DAO_Event',
        'fields' => array(
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'start_date' => array(
            'title' => 'Datum',
            'required' => TRUE,
            'dbAlias' => 'date_format(start_date, \'%Y-%m-%d\')',
          ),
          'title' => array(
            'title' => 'Cursus',
            'required' => TRUE,
          ),
          'price' => array(
            'title' => 'Prijsset',
            'required' => TRUE,
            'dbAlias' => "group_concat(concat(psfv.label, ' (', floor(psfv.amount)), ' EUR)')"
          ),
        ),
        'order_bys' => array(
          'start_date' => array(
            'title' => 'Start Date',
            'default' => TRUE,
            'default_order' => 'ASC',
          ),
        ),
        'filters' => array(
          'start_date' => array(
            'title' => 'Datum cursus',
            'type' => CRM_Utils_Type::T_DATE,
            'default' => array(
              'from' => date('m/d/Y', time() - (86400 * 2)), // current date - 2 days
              'to' => date('m/d/Y', time() + (86400 * 365)), // current date + 365 days
            ),
          ),
        ),
      ),
    );

    parent::__construct();
  }

  function preProcess() {
    $this->assign('reportTitle', 'Cursussen');
    parent::preProcess();
  }

  function from() {
    $this->_from = "
			FROM
				civicrm_event {$this->_aliases['civicrm_event']}
      left outer join
        civicrm_price_set_entity pse on pse.entity_id = {$this->_aliases['civicrm_event']}.id and pse.entity_table = 'civicrm_event'
      left outer join
        civicrm_price_set ps on ps.id = pse.price_set_id
      left outer join
        civicrm_price_field psf on psf.price_set_id = ps.id
      left outer join
        civicrm_price_field_value psfv on psfv.price_field_id = psf.id
		";
  }

  function groupBy() {
    $this->_groupBy = "
			GROUP BY
				{$this->_aliases['civicrm_event']}.id
				, {$this->_aliases['civicrm_event']}.start_date
				, {$this->_aliases['civicrm_event']}.title
		";
  }

  function alterDisplay(&$rows) {
    foreach ($rows as $rowNum => $row) {
      // convert event title to link
      if (array_key_exists('civicrm_event_id', $row) && array_key_exists('civicrm_event_title', $row)) {
        $url = CRM_Utils_System::url('civicrm/event/manage/settings', "reset=1&action=update&id=" . $row['civicrm_event_id']);
        $rows[$rowNum]['civicrm_event_title'] = '<a href="' . $url .'">' . $row['civicrm_event_title'] . '</a>';
      }

      // convert number of participants to link
      if (array_key_exists('civicrm_event_id', $row) && array_key_exists('civicrm_participant_participant_count', $row)) {
        $url = CRM_Utils_System::url('civicrm/event/search', "reset=1&force=1&event=" . $row['civicrm_event_id'] . '&status=true');
        $rows[$rowNum]['civicrm_participant_participant_count'] = '<a href="' . $url .'">' . $row['civicrm_participant_participant_count'] . '</a>';
      }
    }
  }

}
