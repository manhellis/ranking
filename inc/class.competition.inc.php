<?php
	/**************************************************************************\
	* eGroupWare - digital ROCK Rankings - Competition Object                  *
	* http://www.egroupware.org, http://www.digitalROCK.de                     *
	* Written and (c) by Ralf Becker <RalfBecker@outdoor-training.de>          *
	* --------------------------------------------                             *
	*  This program is free software; you can redistribute it and/or modify it *
	*  under the terms of the GNU General Public License as published by the   *
	*  Free Software Foundation; either version 2 of the License, or (at your  *
	*  option) any later version.                                              *
	\**************************************************************************/

	/* $Id$ */

require_once(PHPGW_INCLUDE_ROOT . '/etemplate/inc/class.so_sql.inc.php');

/*!
@class competition
@abstract competition object
*/
class competition extends so_sql
{
	/*var $public_functions = array(
		'init'	=> True,
		'read'	=> True,
		'save'	=> True,
		'delete'	=> True,
		'search'	=> True,
	) */	// set in so_sql
/* set by so_sql('ranking','rang.Wettkaempfe'):
	var $table_name = 'rang.Wettkaempfe';
	var $autoinc_id = 'WetId';
	var $db_key_cols = array('WetId' => 'WetId');
	var $db_data_cols = array(
		'rkey' => 'rkey', 'name' => 'name', 'dru_bez' => 'dru_bez', 'datum' => 'datum',
		'pkte' => 'pkte', 'pkt_bis' => 'pkt_bis', 'feld_pkte' => 'feld_pkte', 'feld_bis' => 'feld_bis',
		'faktor' => 'faktor', 'serie' => 'serie', 'open' => 'open','nation' => 'nation',
		'gruppen' => 'gruppen', 'homepage' => 'homepage'
	);
*/
	var $non_db_cols = array(	// fields in data, not (direct) saved to the db
		'durartion' => 'duration'
	);

	/*!
	@function competition
	@abstract constructor of the competition class
	*/
	function competition($key=0)
	{
		//$this->debug = 1;
		$this->so_sql('ranking','rang.Wettkaempfe');	// call constructor of extending class

		$this->public_functions += array(
			'names' => True
		);

		if ($key)
			$this->read($key);
	}

	/*!
	@function db2data
	@abstract changes the data from the db-format to our work-format
	@param $data if given works on that array and returns result, else works on internal data-array
	*/
	function db2data($data=0)
	{
		if ($intern = !is_array($data))
			$data = $this->data;

		list($data['gruppen'],$data['duration']) = explode('@',$data['gruppen']);
		$data['pkt_bis'] = $data['pkt_bis']!='' ? intval(100 * $data['pkt_bis']) : 100;
		$data['feld_bis'] = $data['feld_bis']!='' ? intval(100 * $data['feld_bis']) : 100;

		if ($intern)
			$this->data = $data;

		return $data;
	}

	/*!
	@function data2db
	@abstract changes the data from our work-format to the db-format
	@param $data if given works on that array and returns result, else works on internal data-array
	*/
	function data2db($data=0)
	{
		if ($intern = !is_array($data))
			$data = $this->data;

		if ($data['duration'])
			$data['gruppen'] .= '@' . $data['duration'];
		$data['pkt_bis']  = $data['pkt_bis']  == 100 ? '' : 100.0*$data['pkt_bis'];
		$data['feld_bis'] = $data['feld_bis'] == 100 ? '' : 100.0*$data['feld_bis'];
		$data['rkey'] = strtoupper($data['rkey']);
		$data['nation'] = strtoupper($data['nation']);

		if ($intern)
			$this->data = $data;

		return $data;
	}

	/*!
	@function search
	@abstract reimplmented from so_sql to be able to call data2db before save and db2data after
	*/
	function search($criteria,$only_keys=True,$order_by='',$extra_cols='',$wildcard='',$empty=False)
	{
		unset($criteria['pkte']);	// is allwas set
		if (!$criteria['feld_pkte'])
			unset($criteria['feld_pkte']);
		unset($criteria['open']);
		if (!$criteria['serie'])
			unset($criteria['serie']);
		$criteria['rkey'] = strtoupper($criteria['rkey']);

		//$this->debug = 1;
		return so_sql::search($criteria,$only_keys,$order_by,$extra_cols,$wildcard,$empty);
	}

	/*!
	@function names
	@param $keys likes to limit name-list, like for so_sql.search
	@returns array with all Competitions of form WetId => name
	*/
	function names($keys=array())
	{
		$all = $this->search($keys,False,'datum');

		if (!$all)
			return array();

		while (list($key,$data) = each($all))
			$arr[$data['WetId']] = $data['rkey'].': '.$data['name'];

		return $arr;
	}
};