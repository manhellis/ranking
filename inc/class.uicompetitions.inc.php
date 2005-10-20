<?php
/**************************************************************************\
* eGroupWare - digital ROCK Rankings - competitions UI                     *
* http://www.egroupware.org, http://www.digitalROCK.de                     *
* Written and (c) by Ralf Becker <RalfBecker@outdoor-training.de>          *
* --------------------------------------------                             *
*  This program is free software; you can redistribute it and/or modify it *
*  under the terms of the GNU General Public License as published by the   *
*  Free Software Foundation; either version 2 of the License, or (at your  *
*  option) any later version.                                              *
\**************************************************************************/

/* $Id$ */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boranking.inc.php');

class uicompetitions extends boranking 
{
	/**
	 * @var array $public_functions functions callable via menuaction
	 */
	var $public_functions = array(
		'index' => true,
		'edit'  => true,
		'view'  => true,
	);
	var $attachment_type = array();

	function uicompetitions()
	{
		$this->boranking();

		$this->tmpl =& CreateObject('etemplate.etemplate');
		
		$this->attachment_type = array(
			'info'      => lang('Information PDF'),
			'startlist' => lang('Startlist PDF'),
			'result'    => lang('Result PDF'),
		);
	}

	/**
	 * View a competition
	 */
	function view()
	{
		$this->edit(null,'',true);
	}

	/**
	 * Edit a competition
	 *
	 * @param array $content
	 * @param string $msg
	 */
	function edit($content=null,$msg='',$view=false)
	{
		if (($_GET['rkey'] || $_GET['WetId']) && !$this->comp->read($_GET))
		{
			$msg .= lang('Entry not found !!!');
		}
		// set and enforce nation ACL
		if (!is_array($content))	// new call
		{
			if (!$_GET['WetId'] && !$_GET['rkey'])
			{
				$this->comp->data['nation'] = $this->edit_rights[0];
			}
			// we have no edit-rights for that nation
			if (!$this->acl_check($this->comp->data['nation'],EGW_ACL_EDIT))
			{
				$view = true;
			}
		}
		else
		{
			//echo "<br>uicompetitions::edit: content ="; _debug_array($content);
			$this->comp->data = $content['comp_data'];
			$old_rkey = $content['comp_data']['rkey'];
			unset($content['comp_data']);
			
			$view = $content['view'] && !($content['edit'] && $this->acl_check($this->comp->data['nation'],EGW_ACL_EDIT));

			if (!$view && $this->only_nation_edit) $content['nation'] = $this->only_nation_edit;

			if ($content['serie'] && $content['serie'] != $this->comp->data['serie'] && 
				$this->cup->read(array('SerId' => $content['serie'])))
			{
				foreach($this->cup->data['presets']+array('gruppen' => $this->cup->data['gruppen']) as $key => $val)
				{
					$content[$key] = $val;
				}
			}
			$this->comp->data_merge($content);
			//echo "<br>uicompetitions::edit: comp->data ="; _debug_array($this->comp->data);

			if (!$view  && ($content['save'] || $content['apply']) && $this->acl_check($content['nation'],EGW_ACL_EDIT))
			{
				if (!$this->comp->data['rkey'])
				{
					// generate an rkey using the cup's rkey or the year as prefix
					$pattern = date('y');
					if ($this->comp->data['serie'] && $this->cup->read(array('SerId' => $this->comp->data['serie'])))
					{
						$pattern = $this->cup->data['rkey'];
						if (strlen($pattern) > 5) $pattern = str_replace('_','',$pattern);
					}
					$n = 0;					
					do
					{
						$this->comp->data['rkey'] = $pattern . '_' . strtoupper(
							(!$this->comp->data['dru_bez'] ? ++$n :					// number starting with 1
							(++$n < 2 ? substr($this->comp->data['dru_bez'],0,2) : 	// 2 char shortcut from dru_bez
							$this->comp->data['dru_bez'][0].$n)));					// 1. char from dru_bez plus number
					}
					while ($this->comp->not_unique());
				}
				elseif ($this->comp->not_unique())
				{
					$msg .= lang("Error: Key '%1' exists already, it has to be unique !!!",$this->comp->data['rkey']);
				}
				elseif ($this->comp->save())
				{
					$msg .= lang('Error: while saving !!!');
				}
				else
				{
					$msg .= lang('%1 saved',lang('Competition'));
					
					//echo "<p>renaming attachments from '?$old_rkey' to '?".$this->comp->data['rkey']."'</p>\n";					
					if ($old_rkey && $this->comp->data['rkey'] != $old_rkey && 
						!$this->comp->rename_attachments($old_rkey))
					{
						$msg .= ', '.lang("Error: renaming the attachments !!!");
					}
					foreach($this->comp->attachment_prefixes as $type => $prefix)
					{
						$file = $content['upload_'.$type];
						if (is_array($file) && $file['tmp_name'] && $file['name'])
						{
							//echo $type; _debug_array($file);
							$error_msg = $file['type'] != 'application/pdf' && 
								strtolower(substr($file['name'],-4)) != '.pdf' ?
								lang('File is not a PDF'): false;

							if (!$error_msg && $this->comp->attach_files(array($type => $file['tmp_name']),$error_msg))
							{
								$msg .= ', '.lang("File '%1' successful attached as %2",$file['name'],$this->attachment_type[$type]);
							}
							else
							{
								$msg .= ', '.lang("Error: attaching '%1' as %2 (%3) !!!",$file['name'],$this->attachment_type[$type],$error_msg);
							}
						}	
					}
					if ($content['save']) $content['cancel'] = true;	// leave dialog now
				}
			}
			if ($content['cancel'])
			{
				$this->tmpl->location(array('menuaction'=>'ranking.uicompetitions.index'));
			}
			if ($content['delete'] && ($this->is_admin || in_array($this->comp->data['nation'],$this->edit_rights)))
			{
				$this->index(array(
					'nm' => array(
						'rows' => array(
							'delete' => array(
								$this->comp->data['WetId'] => 'delete'
							)
						)
					)
				));
				return;
			}
			if ($content['remove'] && in_array($content['nation'],$this->edit_rights))
			{
				list($type) = each($content['remove']);
				
				$msg .= $this->comp->remove_attachment($type) ?
					lang('Removed the %1',$this->attachment_type[$type]) :
					lang('Error: removing the %1 !!!',$this->attachment_type[$type]);
			}
		}
		$tabs = 'general|ranking|files|startlist|judges';
		$content = $this->comp->data + array(
			'msg' => $msg,
			$tabs => $content[$tabs],
		);
		foreach((array) $this->comp->attachments() as $type => $linkdata)
		{
			$content['pdf'][$type] = array(
				'icon' => $type,
				'file' => $this->comp->attachment_path($type),
				'link' => $linkdata,
			);
		}
		$sel_options = array(
			'pkte'      => $this->pkt_names,
			'feld_pkte' => array(0 => lang('none')) + $this->pkt_names,
			'serie'     => array(0 => lang('none')) + $this->cup->names(array(
				'nation'=>$this->comp->data['nation'])),
			'nation'    => $this->ranking_nations,
			'gruppen'   => $this->cats->names(array('nation' => $this->comp->data['nation'])),
			'prequal_comps' => $this->comp->names(array(
				!$this->comp->data['datum'] ? 'datum > \''.(date('Y')-2).'\'' :
					'datum < '.$this->db->quote($this->comp->data['datum']).' AND datum > \''.((int)$this->comp->data['datum']-2).'-01-01\'',
				'nation' => $this->comp->data['nation'],
			)),
			'host_nation' => $this->athlete->distinct_list('nation'),
		);
		$readonlys = array(
			'delete' => !$this->comp->data[$this->comp->db_key_cols[$this->comp->autoinc_id]],
			'nation' => !!$this->only_nation_edit,
			'edit'   => !$view || !$this->acl_check($this->comp->data['nation'],EGW_ACL_EDIT),
		);
		foreach($this->attachment_type as $type => $label)
		{
			$readonlys['remove['.$type.']'] = $view || !isset($content['pdf'][$type]);
		}
		if ($view)
		{
			foreach($this->comp->data as $name => $val)
			{
				$readonlys[$name] = true;
			}
			$readonlys['save'] = $readonlys['apply'] = true;
			$readonlys['upload_info'] = $readonlys['upload_startlist'] = $readonlys['upload_result'] = true;
		}
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang($view ? 'view %1' : 'edit %1',lang('competition'));
		$this->tmpl->read('ranking.comp.edit');
		$this->tmpl->exec('ranking.uicompetitions.edit',$content,
			$sel_options,$readonlys,array(
				'comp_data' => $this->comp->data,
				'view' => $view,
			));
	}

	/**
	 * query competitions for nextmatch in the competitions list
	 *
	 * @param array $query
	 * @param array &$rows returned rows/competitions
	 * @param array &$readonlys eg. to disable buttons based on acl
	 */
	function get_rows($query,&$rows,&$readonlys)
	{
		$GLOBALS['egw']->session->appsession('ranking','comp_state',$query);
		if (!$this->is_admin && !in_array($query['col_filter']['nation'],$this->read_rights))
		{
			$query['col_filter']['nation'] = $this->read_rights;
			if (($null_key = array_search('NULL',$this->read_rights)) !== false)
			{
				$query['col_filter']['nation'][$null_key] = null;
			}
		}
		$nation = $query['col_filter']['nation'];

		foreach((array) $query['col_filter'] as $col => $val)
		{
			if ($val == 'NULL') $query['col_filter'][$col] = null;
		}
		// set the cups based on the selected nation
		$cups = $this->cup->names(!$nation ? array() : array('nation' => $query['col_filter']['nation']),true);
		// unset the cup, if it's not (longer) in the selected nations cups
		if (!isset($cups[$query['col_filter']['serie']])) $query['col_filter']['serie'] = '';
		
		$total = $this->comp->get_rows($query,$rows,$readonlys);
		
		$readonlys = array();
		foreach($rows as $n => $row)
		{
			foreach((array) $this->comp->attachments($row) as $type => $linkdata)
			{
				$rows[$n]['pdf'][$type] = array(
					'icon' => $type,
					'file' => $this->comp->attachment_path($type),
					'link' => $linkdata,
					'label'=> $this->attachment_type[$type],
				);
			}
			$readonlys["edit[$row[WetId]]"] = $readonlys["delete[$row[WetId]]"] = !$this->acl_check($row['nation'],EGW_ACL_EDIT);
		}
		// set the cups based on the selected nation
		$rows['sel_options']['serie'] = $cups;
		
		if ($this->debug)
		{
			echo "<p>uicompetitions::get_rows(".print_r($query,true).") rows ="; _debug_array($rows);
			_debug_array($readonlys);
		}
		return $total;		
	}

	/**
	 * List existing competitions
	 *
	 * @param array $content
	 * @param string $msg
	 */
	function index($content=null,$msg='')
	{
		$content = $content['nm']['rows'];
		
		if ($content['view'] || $content['edit'] || $content['delete'])
		{
			foreach(array('view','edit','delete') as $action)
			{
				if ($content[$action])
				{
					list($id) = each($content[$action]);
					break;
				}
			}
			if ($this->debug) echo "<p>ranking::competitions() action='$action', id='$id'</p>\n";
			switch($action)
			{
				case 'view':
					$this->tmpl->location(array(
						'menuaction' => 'ranking.uicompetitions.view',
						'WetId'      => $id,
					));
					break;
					
				case 'edit':
					$this->tmpl->location(array(
						'menuaction' => 'ranking.uicompetitions.edit',
						'WetId'      => $id,
					));
					break;
					
				case 'delete':
					if (!$this->is_admin && $this->comp->read(array('WetId' => $id)) &&
						!in_array($this->comp->data['nation'],$this->edit_rights))
					{
						$msg = lang('Permission denied !!!');
					}
					elseif ($this->comp->has_results($id))
					{
						$msg = lang('You need to delete the results first !!!');
					}
					else
					{
						$msg = $this->comp->delete(array('WetId' => $id)) ? lang('%1 deleted',lang('Competition')) :
							lang('Error: deleting %1 !!!',lang('Competition'));
					}						
					break;
			}						
		}
		$content = array();

		if (!is_array($content['nm'])) $content['nm'] = $GLOBALS['egw']->session->appsession('ranking','comp_state');
		
		if (!is_array($content['nm']))
		{
			$content['nm'] = array(
				'get_rows'       =>	'ranking.uicompetitions.get_rows',
				'no_filter'      => True,// I  disable the 1. filter
				'no_filter2'     => True,// I  disable the 2. filter (params are the same as for filter)
				'no_cat'         => True,// I  disable the cat-selectbox
				'bottom_too'     => True,// I  show the nextmatch-line (arrows, filters, search, ...) again after the rows
				'order'          =>	'datum',// IO name of the column to sort after (optional for the sortheaders)
				'sort'           =>	'DESC',// IO direction of the sort: 'ASC' or 'DESC'
			);
			if (count($this->read_rights) == 1)
			{
				$content['nm']['col_filter']['nation'] = $this->read_rights[0];
			}
		}
		$content['msg'] = $msg;

		$this->tmpl->read('ranking.comp.list');
		$GLOBALS['egw_info']['flags']['app_header'] = lang('ranking').' - '.lang('competitions');
		$this->tmpl->exec('ranking.uicompetitions.index',$content,array(
			'nation' => $this->ranking_nations,
//			'serie'  => $this->cup->names(array(),true),
		));
	}
}
