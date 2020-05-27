<?php

class CRM_Reports_Form_Report_Evenementen extends CRM_Report_Form {
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
						'title' => 'Evenement',
						'required' => TRUE,
					),
				),
				'order_bys' => array(
					'start_date' => array(
						'title' => 'Start Date',
						'default' => TRUE,
						'default_order' => 'DESC',
					),
				),
				'filters' => array(
					'start_date' => array(
						'title' => 'Datum evenement',
						'type' => CRM_Utils_Type::T_DATE,
						'default' => array(
							'from' => date('m/d/Y', time() - (86400 * 30)), // current date - 30 days
							'to' => date('m/d/Y', time() + (86400 * 45)), // current date + 45 days
						),
					),
				),
			),
			'civicrm_address' => array(
				'fields' => array(
					'city' => array(
						'title' => 'Plaats',
						'required' => TRUE,
					),
				),
			),
			'event_type' => array(
				'fields' => array(
					'label' => array(
						'title' => 'Type',
						'required' => TRUE,
					),
          'doelgroep' => array(
            'title' => 'Doelgroep',
            'required' => TRUE,
            'dbAlias' => "if(enkel_voor_leden__17=0,'Leden en niet-leden', 'Leden')",
          ),
				),
			),
			'civicrm_participant' => array(
				'dao' => 'CRM_Event_DAO_Participant',
				'fields' => array(
					'participant_count' => array(
						'title' => 'Aantal deelnemers',
						'required' => TRUE,
						'dbAlias' => 'count(c.id)',
					),
					'gewenst_aantal' => array(
					  'title' => 'Gewenste aantal',
            'required' => TRUE,
            'dbAlias' => 'gewenste_aantal_deelnemers_18',
          ),
          'aantal_beslissingsnemers' => array(
            'title' => 'Aantal beslissingsnemers',
            'required' => TRUE,
            'dbAlias' => '0',
          ),
					'nps' => array(
						'title' => 'NPS',
						'required' => TRUE,
						'dbAlias' => '\'\'',
					),
				),
			),
			'civicrm_value_bijkomende_info_6' => array(
				'filters' => array(
					'regio_19' => array(
						'title' => 'ETION regio',
						'operatorType' => CRM_Report_Form::OP_MULTISELECT_SEPARATOR,
						'options' => $this->_getVKWRegions(),
            'type' => CRM_Utils_Type::T_STRING,
					),
				),
			),
		);

		parent::__construct();
	}

	function preProcess() {
		$this->assign('reportTitle', 'Evenementen');
		parent::preProcess();
	}

	function from() {
		$this->_from = "
			FROM
				civicrm_event {$this->_aliases['civicrm_event']}
			INNER JOIN
				civicrm_value_bijkomende_info_6 {$this->_aliases['civicrm_value_bijkomende_info_6']}
			ON
				{$this->_aliases['civicrm_value_bijkomende_info_6']}.entity_id = {$this->_aliases['civicrm_event']}.id
			INNER JOIN
				civicrm_option_value {$this->_aliases['event_type']}
			ON
				{$this->_aliases['event_type']}.option_group_id = 14 and {$this->_aliases['event_type']}.value = {$this->_aliases['civicrm_event']}.event_type_id
			LEFT OUTER JOIN
				civicrm_participant {$this->_aliases['civicrm_participant']}
			ON 
				{$this->_aliases['civicrm_participant']}.status_id in (1, 2, 8) AND {$this->_aliases['civicrm_participant']}.event_id = {$this->_aliases['civicrm_event']}.id			
			LEFT OUTER JOIN
				civicrm_contact c
			ON
				{$this->_aliases['civicrm_participant']}.contact_id = c.id AND c.is_deleted = 0
			LEFT OUTER JOIN			
				civicrm_loc_block lb
			ON
				lb.id = {$this->_aliases['civicrm_event']}.loc_block_id
			LEFT OUTER JOIN
				civicrm_address {$this->_aliases['civicrm_address']}
			ON
				{$this->_aliases['civicrm_address']}.id = lb.address_id
			LEFT OUTER JOIN
			  civicrm_value_evenement_boekhouding_5 evbh
			ON
			  {$this->_aliases['civicrm_event']}.id = evbh.entity_id 
		";
	}

	function groupBy() {
		$this->_groupBy = "
			GROUP BY
				{$this->_aliases['civicrm_event']}.id
				, {$this->_aliases['civicrm_event']}.start_date	
				, {$this->_aliases['civicrm_event']}.title				
				, {$this->_aliases['event_type']}.label
				, {$this->_aliases['civicrm_address']}.city
		";
	}

	function alterDisplay(&$rows) {
		$score = new CRM_Reports_Utils_EventScore();

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

			// calculate the net promotor score and decision makers
			if (array_key_exists('civicrm_event_id', $row)) {
				$score->loadEventStats($rows[$rowNum]['civicrm_event_id']);

				$url = CRM_Utils_System::url('civicrm/report/be.vkw.reports/evenementevaluatie', "reset=1&force=1&eventID=" . $row['civicrm_event_id']);
				$rows[$rowNum]['civicrm_participant_nps'] = '<a href="' . $url .'">' . $score->getNPS() . '</a>';

        // get number of decision makers
        $rows[$rowNum]['civicrm_participant_aantal_beslissingsnemers'] = $score->getAantalBeslissingsnemers();
			}
		}
  }

	function _getVKWRegions() {
		$retval = array();

		$params = array(
			'option_group_id' => 88,
		);
		$results = civicrm_api3('OptionValue', 'get', $params);

		foreach ($results['values'] as $option) {
			$retval[$option['value']] = $option['label'];
		}

		return $retval;
	}
}
