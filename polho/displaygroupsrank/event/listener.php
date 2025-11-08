<?php

/**
 *
 * polho: display groups rank
 * phpBB3.3 Extension Package
 * @copyright (c) 2015 posey [ www.godfathertalks.com ]
 * @copyright (c) 2016 kasimi [ https://kasimi.net ]
 * @copyright (c) 2025 polho [ https://github.com/polhomarkho ]
 * @license GNU General Public License v2 [ http://opensource.org/licenses/gpl-2.0.php ]
 *
 */

namespace polho\displaygroupsrank\event;

use phpbb\config\config;
use phpbb\db\driver\driver_interface;
use phpbb\event\data;
use phpbb\path_helper;
use phpbb\group\helper;
use phpbb\template\template;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event Listener
 */
class listener implements EventSubscriberInterface
{
	/** @var template */
	protected $template;

	/** @var config */
	protected $config;

	/** @var driver_interface */
	protected $db;

	/** @var path_helper */
	protected $path_helper;

	/** @var helper */
	protected $helper;

	/** @var array */
	private $users_extra_rank_template_data;

	/**
	 * Constructor
	 *
	 * @param template			$template
	 * @param config			$config
	 * @param driver_interface	$db
	 * @param path_helper		$path_helper
	 * @access public
	 */
	public function __construct(
		template $template,
		config $config,
		driver_interface $db,
		path_helper $path_helper,
		helper $helper
	)
	{
		$this->template		= $template;
		$this->config		= $config;
		$this->db			= $db;
		$this->path_helper	= $path_helper;
		$this->helper = $helper;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.memberlist_view_profile'		=> 'viewprofile',
			'core.viewtopic_modify_post_data'	=> 'viewtopic_fetch',
			'core.viewtopic_modify_post_row'	=> 'viewtopic_assign',
			'core.ucp_pm_view_messsage'			=> 'viewpm',
		];
	}

	/**
	 * @param data $event
	 */
	public function viewprofile($event)
	{
		$user_id = $event['member']['user_id'];
		$extra_rank_template_data = $this->get_extra_rank_template_data($user_id);

		foreach ($extra_rank_template_data as $rank)
		{
			$this->template->assign_block_vars('extra_ranks', [
				'EXTRA_RANK_IMG' => $rank['EXTRA_RANK_IMG'],
			]);
		}
	}

	/**
	 * @param data $event
	 */
	public function viewpm($event)
	{
		$user_id = $event['user_info']['user_id'];
		$extra_rank_template_data = $this->get_extra_rank_template_data($user_id);

		foreach ($extra_rank_template_data as $rank)
		{
			$this->template->assign_block_vars('extra_ranks', [
				'EXTRA_RANK_IMG' => $rank['EXTRA_RANK_IMG'],
			]);
		}
	}

	/**
	 * @param data $event
	 */
	public function viewtopic_fetch($event)
	{
		$user_ids = [];
		foreach ($event['rowset'] as $post_row)
		{
			$user_ids[] = (int) $post_row['user_id'];
		}
		$user_ids = array_unique($user_ids);

		$this->users_extra_rank_template_data = $this->get_extra_ranks_template_data($user_ids);
	}

	/**
	 * @param data $event
	 */
	public function viewtopic_assign($event)
	{
		$poster_id = $event['poster_id'];

		if (!empty($this->users_extra_rank_template_data[$poster_id])) {
			$extra_ranks = $this->users_extra_rank_template_data[$poster_id];

			$post_row = $event['post_row'];

			foreach ($extra_ranks as $rank) {
				$post_row['extra_ranks'][] = [
					'EXTRA_RANK_IMG' => $rank['EXTRA_RANK_IMG'] ?? '',
				];
			}

			$event['post_row'] = $post_row;
		}
	}


	/**
	 * Helper method to return the rank template data for a single user
	 *
	 * @param int $user_id The ID of the user to fetch the rank template data
	 * @return array
	 */
	protected function get_extra_rank_template_data($user_id)
	{
		$template_data = $this->get_extra_ranks_template_data([$user_id]);

		return $template_data[$user_id];
	}

	/**
	 * Generates the rank template data for mutiple users
	 *
	 * @param array $user_posts, mapping from user_id to user_posts
	 * @return array mapping from user_id to the array of rank template data
	 */
	protected function get_extra_ranks_template_data(array $user_ids)
	{
		$template_data = [];
		$users_groups_rank_id = $this->get_users_groups_rank_id($user_ids);

		foreach ($users_groups_rank_id as $user_id => $groups_rank_id)
		{
			foreach ($groups_rank_id as $group_rank_id)
			{
				$group_rank = $this->helper->get_rank(['group_rank' => $group_rank_id]);
				$template_data[$user_id][] = [
					'EXTRA_RANK_IMG' => $group_rank['img'],
				];
			}
		}

		return $template_data;
	}
	
	/**
	 * Get the groups with a special rank associated to (group_rank != 0)
	 * for a list of user ids
	 *
	 * @param array $user_ids  User ids
	 * @return array [user_id => [group_rank_id, group_rank_id, ...]]
	 */
	function get_users_groups_rank_id(array $user_ids): array
	{
		if (empty($user_ids))
		{
			return [];
		}
		
		$user_ids = array_map('intval', $user_ids);

		$sql = 'SELECT ug.user_id, g.group_rank
				FROM ' . USER_GROUP_TABLE . ' ug
				JOIN ' . GROUPS_TABLE . ' g
					ON g.group_id = ug.group_id
				WHERE ' . $this->db->sql_in_set('ug.user_id', $user_ids) . '
				AND ug.user_pending = 0
				AND g.group_rank <> 0
				ORDER BY g.group_rank ASC';

		$result = $this->db->sql_query($sql);

		$user_groups = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$user_id  = (int) $row['user_id'];
			$group_rank = (int) $row['group_rank'];
			$user_groups[$user_id][] = $group_rank;
		}
		$this->db->sql_freeresult($result);

		return $user_groups;
	}
}
