<?php
# MantisBT - A PHP based bugtracking system
#
# MantisBT is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 2 of the License, or
# (at your option) any later version.
#
# MantisBT is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with MantisBT.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Kanban board plugin.
 */
class KanbanPlugin extends MantisPlugin {
	const FORM_MOVE = 'plugin_kanban_move';
	const FORM_ISSUE = 'plugin_kanban_issue';
	const FORM_ISSUE_DELETE = 'plugin_kanban_issue_delete';

	/**
	 * Statuses hidden unless the board has at least one card in them
	 * (retorno / admitido).
	 *
	 * @return int[]
	 */
	public static function optional_status_ids() {
		return array( FEEDBACK, ACKNOWLEDGED );
	}

	/**
	 * Plugin registration.
	 *
	 * @return void
	 */
	function register() {
		$this->name = plugin_lang_get( 'title' );
		$this->description = plugin_lang_get( 'description' );
		$this->page = '';

		$this->version = '1.2.9';
		$this->requires = array(
			'MantisCore' => '2.25.0',
		);

		$this->author = 'Build Together';
		$this->contact = '';
		$this->url = '';
	}

	/**
	 * Event hooks.
	 *
	 * @return array
	 */
	function hooks() {
		return array(
			'EVENT_MENU_MAIN_FILTER' => 'menu_filter',
			'EVENT_LAYOUT_RESOURCES' => 'resources',
			'EVENT_LAYOUT_BODY_END' => 'scripts',
		);
	}

	/**
	 * Whether the current request is the Kanban board page.
	 *
	 * @return bool
	 */
	function is_board_page() {
		if( !is_page_name( 'plugin.php' ) ) {
			return false;
		}

		$t_page = gpc_get_string( 'page', '' );
		return strpos( $t_page, 'Kanban/board' ) !== false;
	}

	/**
	 * Insert Kanban link after View Issues in the sidebar.
	 *
	 * @param string $p_event Event name.
	 * @param array  $p_items Sidebar items.
	 * @return array
	 */
	function menu_filter( $p_event, $p_items ) {
		$t_kanban = array(
			'url' => plugin_page( 'board' ),
			'title' => plugin_lang_get( 'menu' ),
			'icon' => 'fa-columns',
			'access_level' => config_get( 'view_bug_threshold' ),
		);

		$t_out = array();
		foreach( $p_items as $t_item ) {
			$t_out[] = $t_item;
			if( isset( $t_item['url'] ) && $t_item['url'] === 'view_all_bug_page.php' ) {
				$t_out[] = $t_kanban;
			}
		}

		return array( $t_out );
	}

	/**
	 * Load Kanban assets on the board page only.
	 *
	 * @return void
	 */
	function resources() {
		if( !$this->is_board_page() ) {
			return;
		}

		$t_ver = urlencode( $this->version );
		echo '<link rel="stylesheet" href="' . plugin_file( 'kanban.css' ) . '&amp;v=' . $t_ver . '" />' . "\n";
	}

	/**
	 * Load Kanban JS at end of body so the board DOM exists.
	 *
	 * @return void
	 */
	function scripts() {
		if( !$this->is_board_page() ) {
			return;
		}

		$t_ver = urlencode( $this->version );
		echo '<script src="' . plugin_file( 'kanban.js' ) . '&amp;v=' . $t_ver . '"></script>' . "\n";
	}
}
