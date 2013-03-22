<?php
/**
 * EGroupware digital ROCK Rankings - result business object/logic
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package ranking
 * @link http://www.egroupware.org
 * @link http://www.digitalROCK.de
 * @author Ralf Becker <RalfBecker@digitalrock.de>
 * @copyright 2007-12 by Ralf Becker <RalfBecker@digitalrock.de>
 * @version $Id$
 */

require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.boranking.inc.php');
require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.route.inc.php');
require_once(EGW_INCLUDE_ROOT.'/ranking/inc/class.route_result.inc.php');

class boresult extends boranking
{
	/**
	 * values and labels for route_order
	 *
	 * @var array
	 */
	var $order_nums;
	/**
	 * values and labels for route_status
	 *
	 * @var array
	 */
	var $stati = array(
		STATUS_UNPUBLISHED     => 'unpublished',
		STATUS_STARTLIST       => 'startlist',
		STATUS_RESULT_OFFICIAL => 'result official',
	);
	/**
	 * Different types of qualification
	 *
	 * @var array
	 */
	var $quali_types = array(
		ONE_QUALI      => 'one Qualification',
		TWO_QUALI_HALF => 'two Qualification, half quota',	// no countback
		TWO_QUALI_ALL  => 'two Qualification for all, flash one after the other',			// multiply the rank
		TWO_QUALI_ALL_SEED_STAGGER => 'two Qualification for all, flash simultaniously',	// lead on 2 routes for all on flash
		TWO_QUALI_ALL_NO_STAGGER   => 'two Qualification for all, identical startorder',	// lead on 2 routes for all on sight
		TWO_QUALI_ALL_SUM => 'two Qualification with height sum',							// lead on 2 routes with height sum counting
		TWO_QUALI_ALL_NO_COUNTBACK => 'two Qualification for all, no countback',			// lead 2012 EYC
		TWOxTWO_QUALI  => 'two * two Qualification',		// multiply the rank of 2 quali rounds on two routes each
	);
	var $quali_types_speed = array(
		ONE_QUALI       => 'one Qualification',
		TWO_QUALI_SPEED => 'two Qualification',
		TWO_QUALI_BESTOF=> 'best of two (record format)',
	);
	var $eliminated_labels = array(
		''=> '',
		1 => 'fall',
		0 => 'wildcard',
	);
	/**
	 * values and labels for route_plus
	 *
	 * @var array
	 */
	var $plus,$plus_labels;
	/**
	 * Logfile for the bridge to the rock programms running via async service
	 * Set to null to switch it of.
	 *
	 * @var string
	 */
	var $rock_bridge_log = '/tmp/rock_bridge.log';

	/**
	 * Instance of boresult, if instancated
	 *
	 * @var boresult
	 */
	public static $instance;

	function __construct()
	{
		parent::__construct();

		$this->order_nums = array(
			0 => lang('Qualification'),
			1 => lang('2. Qualification'),
		);
		for($i = 2; $i <= 10; ++$i)
		{
			$this->order_nums[$i] = lang('%1. Heat',$i);
		}
		$this->order_nums[-1] = lang('General result');

		$this->plus_labels = array(
			0 =>    '',
			1 =>    '+ '.lang('plus'),
			'-1' => '- '.lang('minus'),
			TOP_PLUS  => lang('Top'),
		);
		$this->plus = array(
			0 =>    '',
			1 =>    '+',
			'-1' => '-',
			TOP_PLUS => lang('Top'),
		);

		// makeing the boresult object availible for other objects
		self::$instance = $this;
	}

	/**
	 * php4 constructor
	 *
	 * @deprecated use __construct()
	 * @return boresult
	 */
	function boresult()
	{
		self::__construct();
	}

	/**
	 * Fix allowed plus labels depending on year of competition and nation
	 *
	 * Also takes into account if we run on ifsc-climbing.org or digitalrock.de
	 *
	 * @param int $year year of competition
	 * @param string $nation nation of comp.
	 * @param string $discipline='lead' can be 'boulderheight' to return labels for tries
	 * @return array
	 */
	function plus_labels($year, $nation, $discipline='lead')
	{
		if ($discipline == 'boulderheight')
		{
			$labels = array();
			for($n = 1; $n < 10; ++$n)
			{
				$labels[100-$n] = lang('%1. try', $n);
			}
			return $labels;
		}
		$labels = $this->plus_labels;

		$minus_allowed = $year < 2012;	// nothing to do

		// digitalrock.de
		if (isset($this->license_nations['GER']) || isset($this->license_nations['SUI']))
		{
			// SUI and international/Regio Cup still has minus in 2012
			if ($nation != 'GER' && $year == 2012) $minus_allowed = true;
		}
		if (!$minus_allowed)
		{
			unset($labels['-1']);
		}
		return $labels;
	}

	/**
	 * Convert athlete array to a string
	 *
	 * @param array $athlete array with values for 'vorname', 'nachname', 'nation' and optional 'start_order', 'start_number', 'result_rank', 'result'
	 * @param boolean|string $show_result=true false: startnumber, null: only name, true: rank&result, 'rank': just rank
	 * @return string nachname, vorname (nation) prefixed with rank for start-number and postfixed with result
	 */
	public static function athlete2string(array $athlete, $show_result=true)
	{
		$str = strtoupper($athlete['nachname']).' '.$athlete['vorname'].' '.$athlete['nation'];

		if ($show_result && $athlete['result_rank'])
		{
			$str = $athlete['result_rank'].'. '.$str.($show_result !== 'rank' ? ' '.str_replace('&nbsp;',' ',$athlete['result']) : '');
		}
		elseif ($show_result == false && $athlete['start_order'])
		{
			$str = $athlete['start_order'].' '.($athlete['start_number'] ? '('.$athlete['start_number'].') ' : '').$str;
		}
		return $str;
	}

	/**
	 * Generate a startlist for the given competition, category and heat (route_order)
	 *
	 * reimplented from boranking to support startlist from further heats and to store the startlist via route_result
	 *
	 * @param int/array $comp WetId or complete comp array
	 * @param int/array $cat GrpId or complete cat array
	 * @param int $route_order 0/1 for qualification, 2, 3, ... for further heats
	 * @param int $route_type=ONE_QUAL ONE_QUALI, TWO_QUALI_HALF or TWO_QUALI_ALL*
	 * @param int $discipline='lead' 'lead', 'speed', 'boulder'
	 * @param int $max_compl=999 maximum number of climbers from the complimentary list
	 * @param int $order=null 0=random, 1=reverse ranking, 2=reverse cup, 3=random(distribution ranking), 4=random(distrib. cup), 5=ranking, 6=cup
	 * @param int $order=null null = default order from self::quali_startlist_default(), int with bitfield of
	 * 	&1  use ranking for order, unranked are random behind last ranked
	 *  &2  use cup for order, unranked are random behind last ranked
	 *  &4  reverse ranking or cup (--> unranked first)
	 *  &8  use ranking/cup for distribution only, order is random
	 * @param int $add_cat=null additional category to add registered atheletes from
	 * @return int/boolean number of starters, if startlist has been successful generated AND saved, false otherwise
	 */
	function generate_startlist($comp,$cat,$route_order,$route_type=ONE_QUALI,$discipline='lead',$max_compl=999,$order=null,$add_cat=null)
	{
		$keys = array(
			'WetId' => is_array($comp) ? $comp['WetId'] : $comp,
			'GrpId' => is_array($cat) ? $cat['GrpId'] : $cat,
			'route_order' => $route_order,
		);
		if (!$comp || !$cat || !is_numeric($route_order) ||
			!$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp) ||	// permission denied
			!$this->route->read($keys) ||	// route does not exist
			$this->has_results($keys))		// route already has a result
		{
			//echo "failed to generate startlist"; _debug_array($keys);
			return false;
		}
		if ($route_order >= 2 || 	// further heat --> startlist from reverse result of previous heat
			$route_order == 1 && in_array($route_type,array(TWO_QUALI_ALL,TWO_QUALI_ALL_NO_STAGGER,TWO_QUALI_ALL_SEED_STAGGER,TWO_QUALI_ALL_NO_COUNTBACK)))	// 2. Quali uses same start-order
		{
			// delete existing starters
			$this->route_result->delete($keys);
			return $this->_startlist_from_previous_heat($keys,
				// after quali reversed result, otherwise as previous heat (always previous for boulderheight!)
				($route_order >= 2 && $discipline != 'boulderheight' ? 'reverse' : 'previous'),
				$discipline);
		}
		// hack for speedrelay, which currently does NOT use registration --> randomize teams
		if ($discipline == 'speedrelay')
		{
			return $this->_randomize_speedrelay($keys);
		}
		// from now on only quali startlist from registration
		if (!is_array($comp)) $comp = $this->comp->read($comp);
		if (!is_array($cat)) $cat = $this->cats->read($cat);
		if (!$comp || !$cat) return false;

		// depricated startlist stored in the result
		if ($this->result->has_startlist(array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['Grpid'],
		)))
		{
			// delete existing starters
			$this->route_result->delete($keys);

			$starters =& $this->result->read(array(
				'WetId' => $keys['WetId'],
				'GrpId' => $keys['Grpid'],
				'platz=0 AND pkt > 64'
			),'',true,'GrpId,pkt,nachname,vorname');

			return $this->_store_startlist($starters,$route_order);
		}
		// preserv an existing quali-startorder (not ranked competitiors)
		$old_startlist = array();
		if ($route_type == TWO_QUALI_HALF) $keys['route_order'] = array(0,1);
		foreach((array)$this->route_result->search($keys,'PerId,start_order,start_number,route_order','start_order ASC,route_order ASC') as $starter)
		{
			if ($starter['PerId']) $old_startlist[$starter['PerId']] = $starter;
		}
		// generate a startlist, without storing it in the result store
		$starters =& parent::generate_startlist($comp,$cat,
			in_array($route_type,array(ONE_QUALI,TWO_QUALI_ALL,TWO_QUALI_ALL_NO_STAGGER,TWO_QUALI_SPEED,TWO_QUALI_BESTOF,TWO_QUALI_ALL_NO_COUNTBACK)) ? 1 : 2,$max_compl,	// 1 = one route, 2 = two routes
			(string)$order === '' ? self::quali_startlist_default($discipline,$route_type,$comp['nation']) : $order,// ordering of quali startlist
			in_array($route_type,array(TWO_QUALI_ALL_SEED_STAGGER,TWO_QUALI_ALL_SUM)),		// true = stagger, false = no stagger
			$old_startlist, $this->comp->quali_preselected($cat['GrpId'], $comp['quali_preselected']), $add_cat);

		if ($discipline == 'speed' && $route_type == TWO_QUALI_BESTOF)	// set 2. lane for record format
		{
			unset($starter);
			$anz = count($starters[1]);
			foreach($starters[1] as &$starter)
			{
				$starter['start_order2n'] = $starter['start_order'] <= floor($anz/2) ?
					$starter['start_order'] + ceil($anz/2) :	// (bigger) first half
					$starter['start_order'] - floor($anz/2);	// (smaller) second half
			}
		}

		// delete existing starters
		$this->route_result->delete($keys);

		$num = $this->_store_startlist($starters[1],$route_type == TWO_QUALI_HALF ? 0 : $route_order);

		if (!in_array($route_type,array(ONE_QUALI,TWO_QUALI_ALL,TWO_QUALI_ALL_NO_COUNTBACK)) && $discipline != 'speed')	// automatically generate 2. quali
		{
			$keys['route_order'] = 1;
			if (!$this->route->read($keys))
			{
				$keys['route_order'] = 0;
				$route = $this->route->read($keys,true);
				$this->route->save(array(
					'route_name'   => '2. '.$route['route_name'],
					'route_order'  => 1,
					'route_status' => STATUS_STARTLIST,
				));
			}
			$this->_store_startlist(isset($starters[2]) ? $starters[2] : $starters[1],1,isset($starters[2]));
		}
		return $num;
	}

	/**
	 * Randomize a startlist for speedrelay qualification
	 *
	 * @param array $keys values for WetId, GrpId and route_order
	 * @return int|boolean number of starters, if the startlist has been successful generated AND saved, false otherwise
	 */
	function _randomize_speedrelay(array $keys)
	{
		$start_order = null;
		if (($starter = $this->route_result->search('',true,'RAND()','','','','AND',false,$keys)))
		{
			foreach($starter as $data)
			{
				$this->route_result->init($data);
				$this->route_result->update(array('start_order' => ++$start_order));
			}
		}
		return $start_order;
	}

	/**
	 * Get registered athletes for given competition and category
	 *
	 * @param array $keys array with values for WetId and GrpId
	 * @param boolean $only_nations=false only return array with nations (as key and value)
	 * @return array
	 */
	function get_registered($keys,$only_nations=false)
	{
		static $stored_keys,$starters;
		if ($keys !== $stored_keys)
		{
			$starters = $this->result->read($keys,'',true,'nation,reg_nr');
			$stored_keys = $keys;
			//_debug_array($starters);
		}
		if ($only_nations)
		{
			$nations = array();
			foreach($starters as $starter)
			{
				if (!isset($nations[$starter['nation']]))
				{
					$nations[$starter['nation']] = $starter['nation'];
				}
			}
			return $nations;
		}
		return $starters;
	}

	/**
	 * Get the default ordering of the qualification startlist
	 *
	 * order bitfields:
	 * 	&1  use ranking for order, unranked are random behind last ranked
	 *  &2  use cup for order, unranked are random behind last ranked
	 *  &4  reverse ranking or cup (--> unranked first)
	 *  &8  use ranking/cup for distribution only, order is random
	 *
	 * @param string $discipline 'lead', 'speed', 'boulder'
	 * @param int $route_type {ONE|TWO|TWOxTWO}_QUALI(_{HALF|ALL|ALL_SEED_STAGGER})?
	 * @param string $nation=null nation of competition
	 * @return int 0=random, 1=reverse ranking, 2=reverse cup, 3=random(distribution ranking), 4=random(distrib. cup), 5=ranking, 6=cup
	 */
	static function quali_startlist_default($discipline,$route_type,$nation=null)
	{
		switch($nation)
		{
			case 'SUI':
				$order = 10;	// random, distribution by Cup(!), since 2012
				break;

			default:
				$order = $discipline == 'speed' ?
					// speed: 0 = random for bestof/record format, 5 = reverse of ranking
					($route_type == TWO_QUALI_BESTOF ? 0 : 1|4) :
					// 9 = distribution by ranking, 0 = random
					(in_array($route_type,array(TWO_QUALI_HALF,TWO_QUALI_ALL_SEED_STAGGER,TWOxTWO_QUALI)) ? 1|8 : 0);
				break;
		}
		//echo "<p>".__METHOD__."($discipline,$route_type,$nation) order=$order</p>\n";
		return $order;
	}

	/**
	 * Store a startlist in route_result table
	 *
	 * @internal
	 * @param array $starters
	 * @param int $route_order if set only these starters get stored
	 * @return int num starters stored
	 */
	function _store_startlist($starters,$route_order,$use_order=true)
	{
		if (!$starters || !is_array($starters))
		{
			return false;
		}
		$num = 0;
		foreach($starters as $starter)
		{
			if (!($start_order = $this->pkt2start($starter['pkt'],!$use_order ? 1 : 1+$route_order)))
			{
				continue;	// wrong route
			}
			$this->route_result->init(array(
				'WetId' => $starter['WetId'],
				'GrpId' => $starter['GrpId'],
				'route_order' => $route_order,
				'PerId' => $starter['PerId'],
				'start_order' => $start_order,
				'ranking' => $starter['ranking'],	// place in cup or ranking responsible for start-order
			)+(isset($starter['start_number']) ? array(
				'start_number' => $starter['start_number'],
			) : array())+(isset($starter['start_order2n']) ? array(
				'start_order2n' => $starter['start_order2n']
			) : array()));

			if ($this->route_result->save() == 0) $num++;
		}
		return $num;
	}

	/**
	 * Startorder for the ko-system, first key is the total number of starters,
	 * second key is the place with the startorder as value
	 *
	 * @var array
	 */
	var $ko_start_order=array(
		16 => array(
			1 => 1,  16 => 2,
			8 => 3,  9  => 4,
			4 => 5,  13 => 6,
			5 => 7,  12 => 8,
			2 => 9,  15 => 10,
			7 => 11, 10 => 12,
			3 => 13, 14 => 14,
			6 => 15, 11 => 16,
		),
		8 => array(
			1 => 1, 8 => 2,
			4 => 3, 5 => 4,
			2 => 5, 7 => 6,
			3 => 7, 6 => 8,
		),
		4 => array(
			1 => 1, 4 => 2,
			2 => 3, 3 => 4,
		),
	);

	/**
	 * Generate a startlist from the result of a previous heat
	 *
	 * @internal use generate_startlist
	 * @param array $keys values for WetId, GrpId and route_order
	 * @param string $start_order='reverse' 'reverse' result, like 'previous' heat, as the 'result'
	 * @param string $discipline
	 * @return int/boolean number of starters, if the startlist has been successful generated AND saved, false otherwise
	 */
	function _startlist_from_previous_heat($keys,$start_order='reverse',$discipline='lead')
	{
		$ko_system = substr($discipline,0,5) == 'speed';
		//echo "<p>".__METHOD__."(".array2string($keys).",$start_order,$discipline) ko_system=$ko_system</p>\n";
		if ($ko_system && $keys['route_order'] > 2)
		{
			return $this->_startlist_from_ko_heat($keys,$prev_route);
		}
		$prev_keys = array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['GrpId'],
			'route_order' => $keys['route_order']-1,
		);
		if ($prev_keys['route_order'] == 1 && !$this->route->read($prev_keys))
		{
			$prev_keys['route_order'] = 0;
		}
		if (!($prev_route = $this->route->read($prev_keys,true)) ||
			$start_order != 'previous' && !$this->has_results($prev_keys) ||	// startorder does NOT depend on result
			$ko_system && !$prev_route['route_quota'])
		{
			//echo "failed to generate startlist from"; _debug_array($prev_keys); _debug_array($prev_route);
			return false;	// prev. route not found or no result
		}
		if ($prev_route['route_type'] == TWO_QUALI_HALF && $keys['route_order'] == 2)
		{
			$prev_keys['route_order'] = array(0,1);		// use both quali routes
		}
		if ($prev_route['route_type'] == TWOxTWO_QUALI && $keys['route_order'] == 4)
		{
			$prev_keys['route_order'] = array(2,3);		// use both quali groups
		}
		if ($prev_route['route_quota'] &&
			(!self::is_two_quali_all($prev_route['route_type']) || $keys['route_order'] > 2))
		{
			$prev_keys[] = 'result_rank <= '.(int)$prev_route['route_quota'];
		}
		// which column get propagated to next heat
		$cols = $this->route_result->startlist_cols();

		// we need ranking from result_detail for 2. qualification for preselected participants
		if (!$prev_route['route_order'])
		{
			$cols[] = 'result_detail AS ranking';

			$comp = $this->comp->read($keys['WetId']);
		}
		if ($prev_route['route_quota'] == 1 || 				// superfinal
			$start_order == 'previous' && !$ko_system || 	// 2. Quali uses same startorder
			$ko_system && $keys['route_order'] > 2)			// speed-final
		{
			$order_by = 'start_order';						// --> same starting order as previous heat!
		}
		else
		{
			if ($ko_system || $start_order == 'result')		// first speed final or start_order by result (eg. boulder 1/2-f)
			{
				$order_by = 'result_rank';					// --> use result of previous heat
			}
			// quali on two routes with multiplied ranking
			elseif(self::is_two_quali_all($prev_route['route_type']) && $keys['route_order'] == 2)
			{
				$cols = array();
				if (self::is_two_quali_all($prev_route['route_type'])) $prev_keys['route_order'] = 0;
				$prev_keys[] = 'result_rank IS NOT NULL';	// otherwise not started athletes qualify too
				$join = $this->route_result->_general_result_join(array(
					'WetId' => $keys['WetId'],
					'GrpId' => $keys['GrpId'],
				),$cols,$order_by,$route_names,$prev_route['route_type'],$discipline,array());
				$order_by = str_replace(array('r2.result_rank IS NULL,r2.result_rank,r1.result_rank IS NULL,',
					',nachname ASC,vorname ASC'),'',$order_by);	// we dont want to order alphabetical, we have to add RAND()
				$order_by .= ' DESC';	// we need reverse order

				// just the col-name is ambigues
				foreach($prev_keys as $col => $val)
				{
					$prev_keys[] = $this->route_result->table_name.'.'.
						$this->db->expression($this->route_result->table_name,array($col => $val));
					unset($prev_keys[$col]);
				}
				foreach($cols as $key => $col)
				{
					if (strpos($col,'quali_points')===false) unset($cols[$key]);	// remove all cols but the quali_points
				}
				$cols[] = $this->route_result->table_name.'.PerId AS PerId';
				$cols[] = $this->route_result->table_name.'.start_number AS start_number';
			}
			else
			{
				$order_by = 'result_rank DESC';		// --> reversed result
			}
			if (($comp = $this->comp->read($keys['WetId'])) &&
				($ranking_sql = $this->_ranking_sql($keys['GrpId'],$comp['datum'],$this->route_result->table_name.'.PerId')))
			{
				$order_by .= ','.$ranking_sql.($start_order != 'result' ? ' DESC' : '');	// --> use the (reversed) ranking
			}
			$order_by .= ',RAND()';					// --> randomized
		}
		//echo "<p>route_result::search('','$cols','$order_by','','',false,'AND',false,".array2string($prev_keys).",'$join');</p>\n";
		$starters =& $this->route_result->search('',$cols,$order_by,'','',false,'AND',false,$prev_keys,$join);
		//_debug_array($starters);

		// ko-system: ex aquos on last place are NOT qualified, instead we use wildcards
		if ($ko_system && $keys['route_order'] == 2 && count($starters) > $prev_route['route_quota'])
		{
			$max_rank = $starters[count($starters)-1]['result_rank']-1;
		}
		$start_order = 1;
		$half_starters = count($starters)/2;
		foreach($starters as $n => $data)
		{
			// get ranking value of prequalified
			if (!empty($data['ranking']) && ($data['ranking'] = unserialize($data['ranking'])))
			{
				$data['ranking'] = $data['ranking']['ranking'];
			}
			// applying a quota for TWO_QUALI_ALL, taking ties into account!
			if (isset($data['quali_points']) && count($starters)-$n > $prev_route['route_quota'] &&
				$data['quali_points'] > $starters[count($starters)-$prev_route['route_quota']]['quali_points'])
			{
				//echo "<p>ignoring: n=$n, points={$data['quali_points']}, starters[".(count($starters)-$prev_route['route_quota'])."]['quali_points']=".$starters[count($starters)-$prev_route['route_quota']]['quali_points']."</p>\n";
				continue;
			}
			if ($ko_system && $keys['route_order'] == 2)	// first final round in ko-sytem
			{
				if (!isset($this->ko_start_order[$prev_route['route_quota']])) return false;
				if ($max_rank)
				{
					if ($data['result_rank'] > $max_rank) break;
					if ($start_order <= $prev_route['route_quota']-$max_rank)
					{
						$data['result_time'] = WILDCARD_TIME;
					}
				}
				$data['start_order'] = $this->ko_start_order[$prev_route['route_quota']][$start_order++];
			}
			// 2. quali is stagger'ed of 1. quali (50-100,1-49)
			elseif(in_array($prev_route['route_type'],array(TWO_QUALI_ALL,TWO_QUALI_ALL_SEED_STAGGER)) && $keys['route_order'] == 1)
			{
				if ($start_order <= floor($half_starters))
				{
					$data['start_order'] = $start_order+ceil($half_starters);
				}
				else
				{
					$data['start_order'] = $start_order-floor($half_starters);
				}
				++$start_order;
			}
			else
			{
				$data['start_order'] = $start_order++;
			}
			$this->route_result->init($keys);
			unset($data['result_rank']);
			$this->route_result->save($data);
		}
		// add prequalified to quali(s) and first final round
		if ($comp && ($preselected = $this->comp->quali_preselected($keys['GrpId'], $comp['quali_preselected'])) && $keys['route_order'] <= 2)
		{
			array_pop($prev_keys);	// remove: result_rank IS NULL
			// we need ranking from result_detail for 2. qualification for preselected participants
			$cols[] = $this->route_result->table_name.'.result_detail AS ranking';
			$cols[] = $this->route_result->table_name.'.result_rank AS result_rank';
			$order_by = $this->route_result->table_name.'.start_order ASC';	// are already in cup order
			$starters =& $this->route_result->search('',$cols,$order_by,'','',false,'AND',false,$prev_keys,$join);
			//_debug_array($starters);
			foreach($starters as $n => $data)
			{
				// get ranking value of prequalified
				if (!empty($data['ranking']) && ($data['ranking'] = unserialize($data['ranking'])))
				{
					$data['ranking'] = $data['ranking']['ranking'];
				}
				if (!(isset($data['ranking']) && $data['ranking'] <= $preselected))	// not prequalified
				{
					//echo "<p>not prequalified</p>";
					continue;
				}
				$data['start_order'] = $start_order++;
				$this->route_result->init($keys);
				unset($data['result_rank']);
				$this->route_result->save($data);
			}
		}
		if ($ko_system && $keys['route_order'] == 2)	// first final round in ko-sytem --> fill up with wildcards
		{
			while($start_order <= $prev_route['route_quota'])
			{
				$this->_create_wildcard_co($keys,$this->ko_start_order[$prev_route['route_quota']][$start_order++]);
			}
		}
		return $start_order-1;
	}

	/**
	 * Generate a startlist from the result of a previous heat
	 *
	 * @param array $keys values for WetId, GrpId and route_order
	 * @param string $start_order='reverse' 'reverse' result, like 'previous' heat, as the 'result'
	 * @param boolean $ko_system=false use ko-system
	 * @param string $discipline
	 * @return int/boolean number of starters, if the startlist has been successful generated AND saved, false otherwise
	 */
	function _startlist_from_ko_heat($keys)
	{
		//echo "<p>".__METHOD__."(".print_r($keys,true).")</p>\n";
		$prev_keys = array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['GrpId'],
			'route_order' => $keys['route_order']-1,
		);
		if (!($prev_route = $this->route->read($prev_keys)))
		{
			return false;
		}
		if ($prev_route['route_quota'] == 2)	// small final
		{
			$prev_keys[] = 'result_rank > 2';
		}
		else	// 1/2|4|8 Final
		{
			$prev_keys[] = 'result_rank = 1';

			if (!$prev_route['route_quota'] && --$prev_keys['route_order'] &&	// final
				!($prev_route = $this->route->read($prev_keys)))
			{
				return false;
			}
		}
		// which column get propagated to next heat
		$cols = $this->route_result->startlist_cols();
		$cols[] = 'start_order';
		$starters =& $this->route_result->search('',$cols,
			$order_by='start_order','','',false,'AND',false,$prev_keys);
		//echo "<p>route_result::search('','$cols','$order_by','','',false,'AND',false,".array2string($prev_keys).",'$join');</p>\n"; _debug_array($starters);

		// reindex by _new_ start_order
		foreach($starters as &$starter)
		{
			unset($starter['result_rank']);
			$start_order = (int)(($starter['start_order']+1)/2);
			$starters_by_startorder[$start_order] =& $starter;
		}
		for($start_order=1; $start_order <= $prev_route['route_quota']; ++$start_order)
		{
			$data = $starters_by_startorder[$start_order];
			if (!isset($data) || $data[$this->route_result->id_col] <= 0)	// no starter --> wildcard for co
			{
				$this->_create_wildcard_co($keys,$start_order,array('result_rank' => 2));
			}
			else	// regular starter
			{
				// check if our co is a regular starter, as we otherwise have a wildcard
				$co = $starters_by_startorder[$start_order & 1 ? $start_order+1 : $start_order-1];
				if (!isset($co) || $co[$this->route_result->id_col] <= 0)
				{
					$data['result_time'] = WILDCARD_TIME;
					$data['result_rank'] = 1;
				}
				$data['start_order'] = $start_order;

				$this->route_result->init($keys);
				$this->route_result->save($data);
			}
		}
		return $start_order-1;
	}

	/**
	 * Create a wildcard co-starter
	 *
	 * @param array $keys
	 * @param int $start_order
	 * @param array $extra=array()
	 */
	function _create_wildcard_co(array $keys,$start_order,array $extra=array())
	{
		$this->route_result->init($keys);
		$this->route_result->save($data=array(
			$this->route_result->id_col => -$start_order,	// has to be set and unique (per route) for each wildcard
			'start_order' => $start_order,
			'result_time' => ELIMINATED_TIME,
			'team_name' => lang('Wildcard'),
		)+$extra);
	}

	/**
	 * Check if given type is one of the TWO_QUALI_ALL* types
	 *
	 * @param int $route_type
	 * @return boolean
	 */
	static function is_two_quali_all($route_type)
	{
		return in_array($route_type,array(TWO_QUALI_ALL,TWO_QUALI_ALL_NO_STAGGER,TWO_QUALI_ALL_SEED_STAGGER,TWO_QUALI_ALL_SUM,TWO_QUALI_ALL_NO_COUNTBACK));
	}

	/**
	 * Get the ranking as an sql statement, to eg. order by it
	 *
	 * @param int/array $cat category
	 * @param string $stand date of the ranking as Y-m-d string
	 * @return string sql or null for no ranking
	 */
	function _ranking_sql($cat,$stand,$PerId='PerId')
	{
	 	$ranking =& $this->ranking($cat,$stand,$nul,$nul,$nul,$nul,$nul,$nul,$mode == 2 ? $comp['serie'] : '');
		if (!$ranking) return null;

		$sql = 'CASE '.$PerId;
	 	foreach($ranking as $data)
	 	{
	 		$sql .= ' WHEN '.$data['PerId'].' THEN '.$data['platz'];
	 	}
	 	$sql .= ' ELSE 9999';	// unranked competitors should be behind the ranked ones
		$sql .= ' END';

	 	return $sql;
	}

	/**
	 * Updates the result of the route specified in $keys
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param array $results PerId => data pairs
	 * @param int $route_type ONE_QUALI, TWO_QUALI_*, TWOxTWO_QUALI
	 * @param string $discipline 'lead', 'speed', 'boulder' or 'speedrelay'
	 * @param array $old_values values at the time of display, to check if somethings changed
	 * 		default is null, which causes save_result to read the results now.
	 * 		If multiple people are updating, you should provide the result of the time of display,
	 * 		to not accidently overwrite results entered by someone else!
	 * @param int $quali_preselected=0 preselected participants for quali --> no countback to quali, if set!
	 * @return boolean|int number of changed results or false on error
	 */
	function save_result($keys,$results,$route_type,$discipline,$old_values=null,$quali_preselected=0)
	{
		$this->error = null;

		if (!$keys || !$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order']) ||
			!($comp = $this->comp->read($keys['WetId'])) ||
			!$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp) &&
			!$this->is_judge($comp, false, $keys))	// check additionally for route_judges
		{
			return $this->error = false;	// permission denied
		}
		// setting discipline and route_type to allow using it in route_result->save()/->data2db
		$this->route_result->discipline = $discipline;
		$this->route_result->route_type = $route_type;

		// adding a new team for relay
		$data = $results[0];
		if ($discipline == 'speedrelay' && isset($data) && !empty($data['team_nation']) && !empty($data['team_name']))
		{
			$data['team_id'] = $this->route_result->get_max($keys,'team_id')+1;
			$data['start_order'] = $this->route_result->get_max($keys,'start_order')+1;
			$data['result_modified'] = time();
			$data['result_modifier'] = $this->user;
			$this->route_result->init($keys);
			$this->route_result->save($data);
		}
		unset($results[0]);

		//echo "<p>".__METHOD__."(".array2string($keys).",,$route_type,'$discipline')</p>\n"; _debug_array($results);
		if (is_null($old_values) && $results)
		{
			$keys[$this->route_result->id_col] = array_keys($results);
			$old_values = $this->route_result->search($keys,'*');
		}
		$modified = 0;
		foreach($results as $id => $data)
		{
			$keys[$this->route_result->id_col] = $id;

			foreach($old_values as $old) if ($old[$this->route_result->id_col] == $id) break;
			if ($old[$this->route_result->id_col] != $id)
			{
				unset($old);
			}
			else
			{
				$old['result_time'] = $old['result_time_l'];
			}
			// boulder result
			for ($i=1; $i <= route_result::MAX_BOULDERS && isset($data['top'.$i]); ++$i)
			{
				if ($data['top'.$i] && (int)$data['top'.$i] < (int)$data['zone'.$i])
				{
					$this->error[$id]['zone'.$i] = lang('Can NOT be higher than top!');
				}
			}
			if (isset($data['tops']))	// boulder result with just the sums
			{
				// todo: validation
				if ($data['tops'] && (int)$data['tops'] > (int)$data['zones'])
				{
					$this->error[$id]['zones'] = lang('Can NOT be lower than tops!');
				}
				foreach(array('top','zone') as $name)
				{
					if ($data[$name.'s'] > $data[$name.'_tries'])
					{
						$this->error[$id][$name.'s'] = lang('Can NOT be higher than tries!');
					}
				}
			}

			foreach($data as $key => $val)
			{
				// something changed?
				if ((!$old && (string)$val !== '' || (string)$old[$key] != (string)$val) &&
					($key != 'result_plus' || $data['result_height'] || $val == TOP_PLUS || $old['result_plus'] == TOP_PLUS))
				{
					if (($key == 'start_number' || $key == 'start_number_1') && strchr($val,'+') !== false)
					{
						$this->set_start_number($keys,$val);
						++$modified;
						continue;
					}
					//error_log(__METHOD__."() --> saving #$id because $key='$val' changed, was '{$old[$key]}'");
					$data['result_modified'] = time();
					$data['result_modifier'] = $this->user;

					$this->route_result->read($keys);
					//error_log(__METHOD__."() old: route_result->data=".array2string($this->route_result->data));
					$this->route_result->save($data);
					//error_log(__METHOD__."() new: route_result->data=".array2string($this->route_result->data));
					++$modified;
					break;
				}
			}
		}
		// always trying the update, to be able to eg. incorporate changes in the prev. heat
		//if ($modified)	// update the ranking only if there are modifications
		{
			unset($keys[$this->route_result->id_col]);

			if ($keys['route_order'] == 2 && is_null($route_type))	// check the route_type, to know if we have a countback to the quali
			{
				$route = $this->route->read($keys);
				$route_type = $route['route_type'];
			}
			$is_final = false;
			if ($discipline == 'lead' && $keys['route_order'] >= 2)
			{
				if (is_null($route)) $route = $this->route->read($keys);
				$is_final = !$route['route_quota'];
			}
			$n = $this->route_result->update_ranking($keys,$route_type,$discipline,$quali_preselected,$is_final);
			//echo '<p>--> '.($n !== false ? $n : 'error, no')." places changed</p>\n";
		}
		// delete the export_route cache
		boresult::delete_export_route_cache($keys);

		return $modified ? $modified : $n;
	}

	/**
	 * Set start-number of a given and the following participants
	 *
	 * @param array $keys 'WetId','GrpId', 'route_order', $this->route_result->id_col (PerId/team_id)
	 * @param string $number [start]+increment
	 */
	function set_start_number($keys,$number)
	{
		$id = $keys[$this->route_result->id_col];
		unset($keys[$this->route_result->id_col]);
		list($start,$increment) = explode('+',$number);
		foreach($this->route_result->search($keys,false,'start_order') as $data)
		{
			if (!$id || $data[$this->route_result->id_col] == $id)
			{
				for ($i = 0; $i <= 3; ++$i)
				{
					$col = 'start_number'.($i ? '_'.$i : '');
					if (!array_key_exists($col,$data)) continue;
					if ($data[$this->route_result->id_col] == $id && $start)
					{
						$last = $data[$col] = $start;
						unset($id);
					}
					else
					{
						$last = $data[$col] = is_numeric($increment) ? $last + $increment : $last;
					}
				}
				$this->route_result->save($data);
			}
		}
	}

	/**
	 * Check if a route has a result or a startlist ($startlist_only == true)
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param boolean $startlist_only=false check of startlist only (not result)
	 * @return boolean true if there's a at least particial result, false if thers none, null if $key is not valid
	 */
	function has_results($keys,$startlist_only=false)
	{
		if (!$keys || !$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order'])) return null;

		if (count($keys) > 3) $keys = array_intersect_key($keys,array('WetId'=>0,'GrpId'=>0,'route_order'=>0,'PerId'=>0,'team_id'=>0));

		if (!$startlist_only) $keys[] = 'result_rank IS NOT NULL';

		return (boolean) $this->route_result->get_count($keys);
	}

	/**
	 * Check if a route has a startlist
	 *
	 * @param array $keys WetId, GrpId, route_order
	 * @param boolean $startlist_only=false check of startlist only (not result)
	 * @return boolean true if there's a at least particial result, false if thers none, null if $key is not valid
	 */
	function has_startlist($keys)
	{
		return $keys['route_order'] == -1 ? false : $this->has_results($keys,true);
	}

	/**
	 * Delete a participant from a route and renumber the starting-order of the following participants
	 *
	 * @param array $keys required 'WetId', 'PerId'/'team_id', possible 'GrpId', 'route_number'
	 * @return boolean true if participant was successful deleted, false otherwise
	 */
	function delete_participant($keys)
	{
		if (!$keys['WetId'] || !$keys[$this->route_result->id_col] ||
			!($comp = $this->comp->read($keys['WetId'])) ||
			!$this->acl_check($comp['nation'],EGW_ACL_RESULT,$comp) ||
			$this->has_results($keys))
		{
			return false; // permission denied
		}
		return $this->route_result->delete_participant($keys);
	}

	/**
	 * Download a route as csv file
	 *
	 * @param array $keys WetId, GrpId, route_order
	 */
	function download($keys)
	{
		if (($route = $this->route->read($keys)) &&
			($cat = $this->cats->read($keys['GrpId'])) &&
			($comp = $this->comp->read($keys['WetId'])))
		{
			$keys['route_type'] = $route['route_type'];
			$keys['discipline'] = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];
			$result = $this->has_results($keys);
			$athletes =& $this->route_result->search('',false,$result ? 'result_rank' : 'start_order','','',false,'AND',false,$keys);
			//_debug_array($athletes); return;

			$stand = $comp['datum'];
 			$this->ranking($cat,$stand,$nul,$test,$ranking,$nul,$nul,$nul);

			$browser =& CreateObject('phpgwapi.browser');
			$browser->content_header($cat['name'].' - '.$route['route_name'].'.csv','text/comma-separated-values');
			$name2csv = array(
				'WetId'    => 'comp',
				'GrpId'    => 'cat',
				'route_order' => 'heat',
				'PerId'    => 'athlete',
				'result_rank'    => 'place',
				'category',
				'route',
				'start_order' => 'startorder',
				'nachname' => 'lastname',
				'vorname'  => 'firstname',
				'nation'   => 'nation',
				'verband'  => 'federation',
				'birthyear' => 'birthyear',
				'ranking',
				'ranking-points',
				'start_number' => 'startnumber',
				'result' => 'result',
			);
			if ($comp['nation'] == 'SUI')
			{
				$name2csv += array(
					'ort'      => 'city',
					'plz'      => 'postcode',
					'geb_date' => 'birthdate',
				);
			}
			switch($keys['discipline'])
			{
				case 'boulder':
					for ($i = 1; $i <= $route['route_num_problems']; ++$i)
					{
						$name2csv['boulder'.$i] = 'boulder'.$i;
					}
					break;
				case 'speed':
					unset($name2csv['result']);
					$name2csv['time_sum'] = 'result';
					$name2csv['result'] = 'time-left';
					$name2csv['result_r'] = 'time-right';
					break;
				case 'lead':
					if ($keys['route_order'] == -1 && self::is_two_quali_all($keys['route_type']))
					{
						unset($name2csv['result']);
						$name2csv['quali_points'] = 'result';
					}
			}
			echo implode(';',$name2csv)."\n";
			$charset = translation::charset();
			foreach($athletes as $athlete)
			{
				if (!$athlete['PerId']) continue;	// general results contain such a (wrong) entry ...

				$values = array();
				foreach($name2csv as $name => $csv)
				{
					switch($csv)
					{
						case 'category':
							$val = $cat['name'];
							break;
						case 'ranking':
							$val = $ranking[$athlete['PerId']]['platz'];
							break;
						case 'ranking-points':
							$val = isset($ranking[$athlete['PerId']]) ? sprintf('%1.2lf',$ranking[$athlete['PerId']]['pkt']) : '';
							break;
						case 'route':
							$val = $route['route_name'];
							break;
						case 'result':
							$val = $athlete['discipline'] == 'boulder' ? $athlete[$name] :
								str_replace(array('&nbsp;',' '),'',$athlete[$name]);
							break;
						default:
							$val = $athlete[$name];
					}
					if (strchr($val,';') !== false)
					{
						$val = '"'.str_replace('"','',$val).'"';
					}
					$values[$csv] = $val;
				}
				// convert by default to iso-8859-1, as this seems to be the default of excel
				echo translation::convert(implode(';',$values),$charset,
					$_GET['charset'] ? $_GET['charset'] : 'iso-8859-1')."\n";
			}
			$GLOBALS['egw']->common->egw_exit();
		}
	}

	/**
	 * Upload a route as csv file
	 *
	 * @param array $keys WetId, GrpId, route_order and optional 'route_type and 'discipline'
	 * @param string|FILE $file uploaded file name or handle
	 * @param boolean $add_athletes=false add not existing athletes, default bail out with an error
	 * @param boolean|int $ignore_comp_heat=false ignore WetId and route_order, default do NOT, or integer WetId to check agains
	 * @param boolean $return_data=false true return array with data and do NOT store it
	 * @return int|string|array integer number of imported results or string with error message
	 */
	function upload($keys,$file,$add_athletes=false,$ignore_comp_heat=false,$return_data=false)
	{
		if (!$keys || !$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order'])) // permission denied
		{
			return lang('Permission denied !!!');
		}
		$route_type = $keys['route_type'];
		$discipline = $keys['discipline'];
		$keys = array_intersect_key($keys, array_flip(array('WetId','GrpId','route_order')));

		if (!isset($route_type))
		{
			if (!($route = $this->route->read($keys)))
			{
				return lang('Route NOT found!').' keys='.array2string($keys);
			}
			$route_type = $route['route_type'];
		}
		if (!isset($discipline))
		{
			$comp = $this->comp->read($keys['WetId']);
			if (!$comp['dicipline']) $cat = $this->cats->read($keys['GrpId']);
			$discipline = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];
		}
		if (is_resource($file))
		{
			$head = fread($file, 10);
			fseek($file, 0, SEEK_SET);
		}
		else
		{
			$head = file_get_contents($file,false,null,0,10);
		}
		if (($xml = strpos($head,'<?xml') === 0 || $discipline == 'speedrelay'))	// no csv import for speedrelay
		{
			$data = $this->parse_xml($keys+array(
				'route_type' => $route_type,
				'discipline' => $discipline,
			),$file,$add_athletes);
		}
		else
		{
			$data = $this->parse_csv($keys,$file,false,$add_athletes,$ignore_comp_heat);
		}
		if (!is_array($data) || $return_data) return $data;

		$this->route_result->route_type = $route_type;
		$this->route_result->discipline = $discipline;

		if (!$xml)
		{
			$this->route_result->delete(array(
				'WetId'    => $keys['WetId'],
				'GrpId'    => $keys['GrpId'],
				'route_order' => $keys['route_order'],
			));
		}
		//_debug_array($lines);
		foreach($data as $line)
		{
			$this->route_result->init($line);
			$this->route_result->save(array(
				'result_modifier' => $this->user,
				'result_modified' => time(),
			));
		}
		if ($xml)	// Zingerle timing ranks are NOT according to rules --> do an own ranking
		{
			$this->route_result->update_ranking($keys,$route_type,$discipline);
		}
		return count($data);
	}

	/**
	 * XMLReader instace of parse_xml
	 *
	 * @var XMLReader
	 */
	private $reader;

	/**
	 * Parse xml file or Zingerle's ClimbingData.xsd schema
	 *
	 * Schema DTD is in /ranking/doc/ClimbingData.xsd, URL http://tempuri.org/ClimbingData.xsd gives 404 Not Found
	 *
	 * That schema can NOT create routes, as it only contains start-numbers, no PerId's!!!!
	 *
	 * @param array $keys WetId, GrpId, route_order and optional 'route_type' and 'discipline'
	 * @param string|FILE $file uploaded file name or handle
	 * @param boolean $add_athletes=false add not existing athletes, default bail out with an error
	 * @return array|string array with imported data (array of array for route_result->save) or string with error message
	 */
	protected function parse_xml($keys,$file,$add_athletes=false)
	{
		$rank_missing = false;
		$route_type = $keys['route_type'];
		$discipline = $keys['discipline'];
		$keys = array_intersect_key($keys, array_flip(array('WetId','GrpId','route_order')));

		if (!isset($route_type))
		{
			if (!($route = $this->route->read($keys)))
			{
				return lang('Route NOT found!').' keys='.array2string($keys);
			}
			$route_type = $route['route_type'];
		}
		if (!isset($discipline))
		{
			$comp = $this->comp->read($keys['WetId']);
			if (!$comp['dicipline']) $cat = $this->cats->read($keys['GrpId']);
			$discipline = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];
		}
		if ($this->route_result->isRelay != ($discipline == 'speedrelay'))
		{
			$this->route_result->__construct($this->config['ranking_db_charset'],$this->db,null,
				$discipline == 'speedrelay');
		}
		$this->route_result->route_type = $route_type;
		$this->route_result->discipline = $discipline;

		if (!($participants = $this->route_result->search(array(),false,'','','',false,'AND',false,$keys+array(
			'discipline'  => $discipline,
			'route_type'  => $route_type,
		))))
		{
			return lang('No participants yet!').' '.lang('ClimbingData xml files can only set results, not create NEW participants!');
		}
		$this->reader = new XMLReader();
		if (is_resource($file))
		{
			$this->reader->XML(stream_get_contents($file));
		}
		elseif (!$this->reader->open($file))
		{
			return lang('Could not open %1!',$file);
		}
		if (!$this->reader->setSchema(EGW_SERVER_ROOT.'/ranking/doc/ClimbingData.xsd'))
		{
			return lang('XML file uses unknown schema (format)!');
		}
		$results = $settings = array();
		while ($this->reader->read())
		{
			if ($this->reader->nodeType == XMLReader::ELEMENT)
			{
				switch ($this->reader->name)
				{
					case 'Settings':
						$settings = $this->read_node();
						break;

					case 'Results':
						$results[] = $this->read_node();
						break;
				}
			}
		}
		//_debug_array($settings);
		switch($settings['Mode'])
		{
			case 'IndividualQualification':
			case 'IndividualFinals':
				if ($discipline != 'speed')
				{
					return lang('Wrong Mode="%1" for discipline "%2"!',$settings['Mode'],$discipline);
				}
				if (($keys['route_order'] < 2) != ($settings['Mode'] == 'IndividualQualification'))
				{
					return lang('Wrong Mode="%1" for this heat (qualification - final mismatch)!',$settings['Mode'],$discipline);

				}
				break;
			case 'TeamQualification':
			case 'TeamFinals':
				if ($discipline != 'speedrelay')
				{
					return lang('Wrong Mode="%1" for discipline "%2"!',$settings['Mode'],$discipline);
				}
				/* as Arco 2011 used only on run per team, qualification used "TeamFinals" mode too
				if (($keys['route_order'] < 2) != ($settings['Mode'] == 'TeamQualification'))
				{
					return lang('Wrong Mode="%1" for this heat (qualification - final mismatch)!',$settings['Mode'],$discipline);
				}*/
				break;
			default:
				return lang('Unknown Mode="%1"!',$settings['Mode']);
		}
		//_debug_array($results);
		//_debug_array($participants);
		$data = array();
		foreach($results as $result)
		{
			if (!$result['StartNumber'])
			{
				continue;	// ignore records without startnumber (not sure how they get into the xml file, but they are!)
			}
			$participant = null;
			foreach($participants as $p)
			{
				if ($discipline == 'speedrelay' && $result['StartNumber'] == $p['team_id'])
				{
					$participant = $keys+array_intersect_key($p, array_flip(array(
						'team_id', 'start_order', 'team_nation', 'team_name',
						'PerId_1','PerId_2','PerId_3','start_number_1','start_number_2','start_number_3',
					)));
					break;
				}
				elseif ($discipline != 'speedrelay' && $result['StartNumber'] == ($p['start_number'] ? $p['start_number'] : $p['start_order']))
				{
					$participant = $keys+array_intersect_key($p, array_flip(array('PerId', 'start_order', 'start_number', 'start_order2n')));
					break;
				}
			}
			if (!$participant)
			{
				echo lang('No participant with startnumber "%1"!',$result['StartNumber']).' '.array2string($result);
				continue;
			}
			switch($settings['Mode'])
			{
				case 'IndividualQualification':
					$participant['result_time'] = self::parse_time($result['Run1'],$participant['eliminated']);
					$participant['result_time_r'] = self::parse_time($result['Run2'],$participant['eliminated_r']);
					break;
				case 'IndividualFinals':
					$participant['result_time'] = self::parse_time($result['BestRun'],$participant['eliminated']);
					break;
				case 'TeamQualification':
					$participant['result_time'] = $result['ResultValue'] / 1000.0;
					$start = isset($result['BestRun']) && $result['BestRun'] == $result['TeamTotalRun1'] ||
						!isset($result['TeamTotalRun2']) ? 1 : 5;
					for ($i = 1; $i <= 3; ++$i, ++$start)
					{
						$participant['result_time_'.$i] = self::parse_time($result['Run'.$start]);
					}
					break;
				case 'TeamFinals':
					$participant['result_time'] = $result['ResultValue'] / 1000.0;
					for ($start = 1; $start <= 3; ++$start)
					{
						$participant['result_time_'.$start] = self::parse_time($result['Run'.$start],$participant['eliminated']);
						if ($participant['eliminated']) break;
					}
					break;
			}

			//error_log($p['nachname'].': '.array2string($participant));
			$data[] = $participant;
		}
		if (!is_resource($file)) $this->reader->close();

		return $data;
	}

	/**
	 * Parse time from xml file m:ss.ddd
	 *
	 * @param string $str
	 * @param string &$eliminated=null on return '' or 1 if climber took a fall, no time
	 * @return double|string
	 */
	static function parse_time($str,&$eliminated=null)
	{
		$eliminated = '';
		if (!isset($str) || (string)$str == '')
		{
			// empty / not set
		}
		elseif (preg_match('/^([0-9]*:)?([0-9.]+)$/', $str, $matches))
		{
			$time = 60.0 * $matches[1] + $matches[2];
		}
		else
		{
			$eliminated = '1';
			$time = '';
		}
		//echo __METHOD__.'('.array2string($str).') eliminated='.array2string($eliminated).' returning '.array2string($time)."\n";
		return $time;
	}

	/**
	 * Return (flat) array of all child nodes
	 *
	 * @return array
	 */
	private function read_node()
	{
		$nodeName = $this->reader->name;

		$data = array();
		while($this->reader->read() && !($this->reader->nodeType == XMLReader::END_ELEMENT && $this->reader->name == $nodeName))
		{
			if ($this->reader->nodeType == XMLReader::ELEMENT)
			{
				$data[$this->reader->name] = trim($this->reader->readString());
			}
		}
		return $data;
	}

	/**
	 * Import the general result of a competition into the ranking
	 *
	 * @param array $keys WetId, GrpId, discipline, route_type, route_order=-1
	 * @param string $filter_nation=null only import athletes of the given nation
	 * @param string $import_cat=null only import athletes of the given category
	 * @return string message
	 */
	function import_ranking($keys, $filter_nation=null, $import_cat=null)
	{
		if (!$keys['WetId'] || !$keys['GrpId'] || !is_numeric($keys['route_order']))
		{
			return false;
		}
		if ($import_cat)
		{
			list($import_cat, $import_comp) = explode(':', $import_cat);
			$import_cat = $this->cats->read($import_cat);
			if ($import_comp && ($import_comp = $this->comp->read($import_comp)) && $import_comp['nation'])
			{
				$filter_nation = $import_comp['nation'];
			}
		}
		$skipped = $last_rank = $ex_aquo = 0;
		$rank = 1;
		foreach($this->route_result->search('',false,'result_rank','','',false,'AMD',false,array(
			'WetId' => $keys['WetId'],
			'GrpId' => $keys['GrpId'],
			'route_order' => $keys['route_order'],
			'discipline' => $keys['discipline'],
			// TWO_QUALI_SPEED is handled like ONE_QUALI (sum is stored in the result, the 2 times in the extra array)
			'route_type' => $keys['route_type'] == TWO_QUALI_SPEED ? ONE_QUALI : $keys['route_type'],
		)) as $row)
		{
			if ($row['result_rank'])
			{
				if ($filter_nation && $row['nation'] != $filter_nation)
				{
					$skipped++;
					continue;
				}
				if ($import_cat && !$this->cats->in_agegroup($row['geb_date'], $import_cat))
				{
					$skipped++;
					continue;
				}
				if ($last_rank === (int)$row['result_rank'])
				{
					++$ex_aquo;
				}
				else
				{
					$ex_aquo = 0;
				}
				$last_rank = (int)$row['result_rank'];

				//echo "<p>$row[nachname], $row[vorname] $row[geb_date]: result_rank=$row[result_rank], rank=$rank, ex_aquo=$ex_aquo --> ".($rank - $ex_aquo)."</p>\n";
				$row['result_rank'] = $rank++ - $ex_aquo;
				$result[$row['PerId']] = $row;
			}
		}
		if ($import_cat)
		{
			$keys['GrpId'] = $import_cat['GrpId'];
		}
		if ($import_comp)
		{
			$keys['WetId'] = $import_comp['WetId'];
		}
		if ($skipped)
		{
			if ($import_cat) $reason = $import_cat['name'];
			if ($filter_nation) $reason .= ($reason ? ' '.lang('or').' ' : '').$filter_nation;
		}
		return parent::import_ranking($keys, $result).($skipped ? "\n".lang('(%1 athletes not from %2 skipped)', $skipped, $reason) : '');
	}

	/**
	 * Gets called via the async service if an automatic import from the rock programms is configured
	 *
	 * @param array $hook_data
	 */
	function import_from_rock($hook_data)
	{
		//echo "import_from_rock"; _debug_array($this->config);
		$this->_bridge_log("**** bridge run started");
		foreach($this->config as $name => $value) if (substr($name,0,11)=='rock_import') $this->_bridge_log("config[$name]='$value'");

		if (!$this->config['rock_import_comp'] || !($comp = $this->comp->read($this->config['rock_import_comp'])))
		{
			$this->_bridge_log("no competition configured or competition ({$this->config['rock_import_comp']}) not found!");
			return;
		}

		for ($n = 1; $n <= 2; ++$n)
		{
			if (!($rroute = $this->config['rock_import'.$n]))
			{
				$this->_bridge_log("$n: No route configured!");
				continue;
			}

			list(,$rcomp) = explode('.',$rroute);
			$year = 2000 + (int) $rcomp;
			$file = $this->config['rock_import_path'].'/'.$year.'/'.$rcomp.'/'.$rroute.'.php';

			$imported = $this->import_rock($file,$comp,$this->config['rock_import_route'.$n]);
			if (is_int($imported))
			{
				$this->_bridge_log("$n: number of participants imported: ".$imported);
			}
			else
			{
				$this->_bridge_log($n.': '.$imported);
			}
		}
		$this->_bridge_log("**** bridge run finished");
	}

	/**
	 * Import an ROCK export file into result-service
	 *
	 * @param string $file full path to export file
	 * @param array $comp result service competition
	 * @param int $route result service route_order
	 * @param int $cat=null cat if not autodetected from $file
	 * @return string|int string with error message or number of participants imported
	 */
	function import_rock($file,array $comp,$heat,$cat=null)
	{
		if (!file_exists($file))
		{
			return "File '$file' not found!";
		}
		include($file);

		if (!is_array($route) || !$route['teilnehmer'])
		{
			return "File '$file' does not include a rock route or participants!";
		}

		if (!$cat) $cat = $route['GrpId'];

		if (!$cat || !($cat = $this->cats->read($cat)) ||
			!in_array($cat['rkey'],$comp['gruppen']) || $route['GrpId'] != $cat['GrpId'])
		{
			//_debug_array($cat);
			//_debug_array($comp);
			//$route['teilnehmer'] = 'not shown'; _debug_array($route);
			return "Category not configured, not belonging to the competition or not found!";
		}
		$discipline = $comp['discipline'] ? $comp['discipline'] : $cat['discipline'];
		$route_imported = $this->_rock2route($route);

		if (!$this->route->read($keys = array(
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
			'route_order' => (int)$heat,
		)))
		{
			// create a new route
			$this->route->init($keys);
			$this->route->save($route_imported);
			//_debug_array($this->route->data);
		}
		elseif($this->route->data['route_status'] == STATUS_RESULT_OFFICIAL)
		{
			return "Result already offical!";	// we dont change the result if it's offical!
		}
		else
		{
			// incorporate changes, not sure if we should do that automatic ???
			unset($route_imported['route_type']);
			unset($route_imported['route_name']);
			$this->route->save($route_imported);
			//_debug_array($this->route->data);
		}
		$this->route_result->delete($keys+array('PerId NOT IN ('.implode(',',array_keys($route['teilnehmer'])),')'));
		foreach($route['teilnehmer'] as $PerId => $data)
		{
			$keys['PerId'] = $PerId;
			$this->route_result->init($keys);
			$this->route_result->save($this->_rock2result($data,$discipline));
		}
		return count($route['teilnehmer']);
	}

	/**
	 * translate a rock participant into a result-service result
	 *
	 * @param array $rdata rock participant data
	 * @param string $discipline lead, speed or boulder
	 * @return array
	 */
	function _rock2result($rdata,$discipline)
	{
		list($PerId,$GrpId) = explode('+',$rdata['key']);

		$data = array(
			'PerId' => $PerId,
			'GrpId' => $GrpId,
			'start_order' => $rdata['startfolgenr'],
			'start_number' => $rdata['startnummer'], //$rdata['startfolgenr'] != $rdata['startnummer'] ? $rdata['startnummer'] : null,
			'result_rank' =>  $rdata['platz'] ? (int)$rdata['platz'] : null,
		);
		if (!$rdata['platz']) return $data;	// no result yet

		switch ($discipline)
		{
			case 'lead':
				$height = $rdata['hoehe'][0];
				if (strpos($height,'Top') !== false)
				{
					$height = TOP_HEIGHT;
					$plus   = TOP_PLUS;
				}
				else
				{
					switch(substr($height,-1))
					{
						case '+': $plus = 1; break;
						case '-': $plus = -1; break;
						default: $plus = 0; break;
					}
					// round removed digits if they are zero: 10.00 --> 10, 10.50 --> 10.5
					$height = round(substr($height,0,-1),2);
				}
				$data['result_height'] = $height;
				$data['result_plus'] = $plus;
				break;

			case 'speed':
				$data['result_time'] = $rdata['time'][0] ? 100*$rdata['time'][0] : null;
				break;

			case 'boulder':
				if ($rdata['boulder'][0])
				{
					$data['top1'] = '';		// otherwise the result is not recogniced as a boulder result!
					for($i = 1; $i <= 6; ++$i)
					{
						$result = trim($rdata['boulder'][$i]);
						if ($result{0} == 't')
						{
							list(,$data['top'.$i],,$data['zone'.$i]) = preg_split('/[tzb ]/',$result);
						}
						else
						{
							$data['zone'.$i] = (string)(int)substr($result,1);
						}
					}
				}
				else
				{
					unset($data['result_rank']);	// otherwise not climbed athlets are already ranked
				}
				break;
		}
		return $data;
	}

	/**
	 * translate a rock route into a result-service route
	 *
	 * @param array $route rock route-data
	 * @return array
	 */
	function _rock2route($route)
	{
		list($iso_open,$iso_close) = preg_split('/ ?- ?/',$route['isolation'],2);

		$ret = array(
			'route_name' => $route['bezeichnung'],
			'route_judge' => $route['jury'][0],
			'route_status' => $route['frei_str'] ? STATUS_RESULT_OFFICIAL : STATUS_STARTLIST,
			'route_type' => ONE_QUALI,	// ToDo: set it from the erge_modus
			'route_iso_open' => $iso_open,
			'route_iso_open' => $iso_close,
			'route_start' => $route['start'],
			'route_result' => $route['frei_str'],
			'route_quota' => $route['quote'],
			'route_num_problems' => substr($route['erge_modus'],0,9) == 'BoulderZ:' ?
				count(explode('+',substr($route['erge_modus'],9))) : null,
		);
		// current participant(s)
		if ($ret['route_num_problems'])	// boulder uses akt_tns[1,2,..]
		{
			for ($i = 1; $i <= $ret['route_num_problems']; ++$i)
			{
				$ret['current_'.$i] = $route['akt_tns'][$i] ? $route['akt_tns'][$i] : null;
			}
		}
		else
		{
			$ret['current_1'] = $route['akt_tns'][0] ? $route['akt_tns'][0] : null;
		}
		return $ret;
	}

	function _bridge_log($str)
	{
		if ($this->rock_bridge_log && ($f = @fopen($this->rock_bridge_log,'a+')))
		{
			fwrite ($f,date('Y-m-d H:i:s: ').$str."\n");
			fclose($f);
		}
	}

	/**
	 * Get the default quota for a given disciplin, route_order and optional quali_type or participants number
	 *
	 * @param string $discipline 'speed', 'lead' or 'boulder'
	 * @param int $route_order
	 * @param int $quali_type=null TWO_QUALI_ALL, TWO_QUALI_HALF, ONE_QUALI
	 * @param int $num_participants=null
	 * @return int
	 */
	static function default_quota($discipline,$route_order,$quali_type=null,$num_participants=null)
	{
		$quota = null;

		switch($discipline)
		{
			case 'speed':
				if (!is_numeric($num_participants)) break;
				for($n = 16; $n > 1; $n /= 2) if ($num_participants > $n || !$route_order && $num_participants >= $n)
				{
					$quota = $n;
					break;
				}
				break;

			case 'lead':
				switch($route_order)
				{
					case 0: $quota = $quali_type == TWO_QUALI_HALF ? 13 : 26; break;	// quali
					case 1: $quota = 13; break;		// 2. quali
					case 2: $quota = 8;  break;		// 1/2-final
				}
				break;

			case 'boulder':
				switch($route_order)
				{
					case 0: $quota = $quali_type == TWO_QUALI_HALF ? 10 : 20; break;	// quali
					case 1: $quota = 10; break;		// 2. quali
					case 2: $quota = 6;  break;		// 1/2-final
				}
				break;
		}
		//echo "<p>boresult::default_quota($discipline,$route_order,$quali_type,$num_participants)=$quota</p>\n";
		return $quota;
	}

	/**
	 * Initialise a route for a given competition, category and route_order and check the (read) permissions
	 *
	 * For existing routes we only check the (read) permissions and read comp and cat.
	 *
	 * @param array &$content on call at least keys WetId, GrpId, route_order, on return initialised route
	 * @param array &$comp on call competition array or null, on return competition array
	 * @param array &$cat  on call category array or null, on return category array
	 * @param string &$discipline on return discipline of route: 'lead', 'speed' or 'boulder'
	 * @return boolean|string true on success, false if permission denied or string with message (eg. 'No quota set in previous heat!')
	 */
	function init_route(array &$content,&$comp,&$cat,&$discipline)
	{
		if (!is_array($comp) && !($comp = $this->comp->read($content['WetId'])) ||
			!is_array($cat) && !($cat = $this->cats->read($content['GrpId'])) ||
			!in_array($cat['rkey'],$comp['gruppen']))
		{
			return false;	// permission denied
		}
		$discipline = !empty($content['discipline']) ? $content['discipline'] :
			($comp['discipline'] ? $comp['discipline'] : $cat['discipline']);
		// switch route_result class to relay mode, if necessary
		if ($this->route_result->isRelay != ($discipline == 'speedrelay'))
		{
			$this->route_result->__construct($this->config['ranking_db_charset'],$this->db,null,
				$discipline == 'speedrelay');
		}
		if (count($content) > 3)
		{
			return true;	// no new route
		}
		$keys = array(
			'WetId' => $comp['WetId'],
			'GrpId' => $cat['GrpId'],
			'route_order' => $route_order=$content['route_order'],
		);
		if ((int)$comp['WetId'] && (int)$cat['GrpId'] && (!is_numeric($route_order) ||
			!($content = $this->route->read($content,true))))
		{
			// try reading the previous heat, to set some stuff from it
			if (($keys['route_order'] = $this->route->get_max_order($comp['WetId'],$cat['GrpId'])) >= 0 &&
				($previous = $this->route->read($keys,true)))
			{
				++$keys['route_order'];
				if ($keys['route_order'] == 1 && in_array($previous['route_type'],array(ONE_QUALI,TWO_QUALI_SPEED,TWO_QUALI_BESTOF)))
				{
					$keys['route_order'] = 2;
				}
				foreach(array('route_type','dsp_id','frm_id','dsp_id2','frm_id2','route_time_host','route_time_port') as $name)
				{
					$keys[$name] = $previous[$name];
				}
				if (!empty($previous['discipline'])) $keys['discipline'] = $previous['discipline'];
			}
			else
			{
				$keys['route_order'] = '0';
				$keys['route_type'] = ONE_QUALI;
			}
			$keys['route_name'] = $keys['route_order'] >= 2 ? lang('Final') :
				($keys['route_order'] == 1 ? '2. ' : '').lang('Qualification');

			if ($previous && !$previous['route_quota']/* && ($discipline != 'speed' || $content['route_order'] <= 2)*/)
			{
				$msg = lang('No quota set in the previous heat!!!');
			}
			if (substr($discipline,0,5) != 'speed')
			{
				if ($comp['nation'] == 'SUI')
				{
					$keys['route_quota'] = '';	// no default quota for SUI
				}
				else
				{
					$keys['route_quota'] = self::default_quota($discipline,$keys['route_order'],null,null);
				}
			}
			elseif ($previous && $previous['route_quota'])
			{
				$keys['route_quota'] = $previous['route_quota'] / 2;
				if ($keys['route_quota'] > 1)
				{
					$keys['route_name'] = '1/'.$keys['route_quota'].' - '.lang('Final');
				}
				elseif($keys['route_quota'] == 1)
				{
					$keys['route_quota'] = '';
					$keys['route_name'] = lang('Small final');
				}
			}
			if ($previous && $previous['route_judge'])
			{
				$keys['route_judge'] = $previous['route_judge'];
			}
			else	// set judges from the competition
			{
				if ($comp['judges'])
				{
					$keys['route_judge'] = array();
					foreach($comp['judges'] as $uid)
					{
						$keys['route_judge'][] = common::grab_owner_name($uid);
					}
					$keys['route_judge'] = implode(', ',$keys['route_judge']);
				}
			}
			$content = $this->route->init($keys);
			$content['new_route'] = true;
			$content['route_status'] = STATUS_STARTLIST;

			// default to 5 boulders
			$content['route_num_problems'] = 5;
		}
		if (empty($content['discipline']))
		{
			$content['discipline'] = $discipline;
		}
		else
		{
			$discipline = $content['discipline'];
		}
		return $msg ? $msg : true;
	}

	/**
	 * Singleton to get a boresult instance
	 *
	 * @return boresult
	 */
	static public function getInstance()
	{
		if (!is_object($GLOBALS['boresult']))
		{
			$GLOBALS['boresult'] = new boresult;
		}
		return $GLOBALS['boresult'];
	}

	/**
	 * Delete export cache for given route and additionaly the general result
	 *
	 * @param int|array $comp WetId or array with values for WetId, GrpId and route_order
	 * @param int $cat=null GrpId
	 * @param int $route_order=null
	 */
	public static function delete_export_route_cache($comp, $cat=null, $route_order=null)
	{
		if (is_array($comp))
		{
			$cat = $comp['GrpId'];
			$route_order = $comp['route_order'];
			$comp = $comp['WetId'];
		}
		egw_cache::unsetInstance('ranking','export_route:'.$comp.':'.$cat.':'.$route_order);
		egw_cache::unsetInstance('ranking','export_route:'.$comp.':'.$cat.':-1');
		egw_cache::unsetInstance('ranking','export_route:'.$comp.':'.$cat.':');	// used if no route is specified!
	}

	/**
	 * Fix ordering of result (so it is identical in xml/json and UI)
	 *
	 * @param array $query
	 * @param boolean $isRelay=false
	 */
	function process_sort(array &$query,$isRelay=false)
	{
		$alpha_sort = $isRelay ? ',team_nation '.$query['sort'].',team_name' :
			',nachname '.$query['sort'].',vorname';
		// in speed(relay) sort by time first and then alphabetic
		if (substr($query['discipline'],0,5) == 'speed')
		{
			$alpha_sort = ',result_time '.$query['sort'].$alpha_sort;
		}
		switch (($order = $query['order']))
		{
			case 'result_rank':
				if ($query['route'] == -1)      // in general result we sort unranked at the end and then as the rest by name
				{
					$query['order'] = 'result_rank IS NULL '.$query['sort'];
				}
				else    // in route-results we want unranked sorted by start_order for easier result-entering
				{
					$query['order'] = 'CASE WHEN result_rank IS NULL THEN start_order ELSE 0 END '.$query['sort'];
				}
				$query['order'] .= ',result_rank '.$query['sort'].$alpha_sort;
				break;
			case 'result_height':
				$query['order'] = 'CASE WHEN result_height IS NULL THEN -start_order ELSE 0 END '.$query['sort'].
					',result_height '.$query['sort'].',result_plus '.$query['sort'].$alpha_sort;
				break;
			case 'result_top,result_zone':
				$query['order'] = 'result_top IS NULL,result_top '.$query['sort'].',result_zone IS NULL,result_zone';
				break;
			case 'nation':
				$query['order'] = 'Federations.nation '.$query['sort'].$alpha_sort;
				break;
		}
	}
}
