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

		$this->version = '1.4.1';
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
			'EVENT_LAYOUT_BODY_BEGIN' => 'body_styles',
			'EVENT_LAYOUT_BODY_END' => 'scripts',
		);
	}

	/**
	 * Allow plugin_file.php to serve Inter woff2 with the correct MIME type.
	 *
	 * @return void
	 */
	function init() {
		global $g_plugin_mime_types;
		if( is_array( $g_plugin_mime_types ) ) {
			$g_plugin_mime_types['woff2'] = 'font/woff2';
		}
	}

	/**
	 * Database schema for card rank positions.
	 *
	 * @return array
	 */
	function schema() {
		$t_table_options = array(
			'mysql' => 'ENGINE=InnoDB DEFAULT CHARSET=utf8',
			'pgsql' => 'WITHOUT OIDS',
		);

		return array(
			array(
				'CreateTableSQL',
				array(
					plugin_table( 'rank' ),
					"
	bug_id					I		UNSIGNED NOTNULL PRIMARY,
	position				I		NOTNULL DEFAULT '0' ",
					$t_table_options,
				),
			),
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
	 * Re-apply DevBoard look after Ace/ModernTheme CSS (head order varies by plugin).
	 *
	 * @return void
	 */
	function body_styles() {
		if( !$this->is_board_page() ) {
			return;
		}

		$this->echo_devboard_css();
	}

	/**
	 * Inter + near-black board. Inline so plugin_file.php cache cannot hide it.
	 *
	 * @return void
	 */
	function echo_devboard_css() {
		$t_ver = urlencode( $this->version );
		$t_font_400 = htmlspecialchars( plugin_file( 'fonts/inter-latin-400.woff2' ) . '&v=' . $t_ver, ENT_QUOTES, 'UTF-8' );
		$t_font_600 = htmlspecialchars( plugin_file( 'fonts/inter-latin-600.woff2' ) . '&v=' . $t_ver, ENT_QUOTES, 'UTF-8' );
		$t_font_700 = htmlspecialchars( plugin_file( 'fonts/inter-latin-700.woff2' ) . '&v=' . $t_ver, ENT_QUOTES, 'UTF-8' );

		echo '<style id="kanban-devboard-inline">';
		echo '@font-face{font-family:Inter;font-style:normal;font-weight:400;font-display:swap;src:url("' . $t_font_400 . '") format("woff2")}';
		echo '@font-face{font-family:Inter;font-style:normal;font-weight:600;font-display:swap;src:url("' . $t_font_600 . '") format("woff2")}';
		echo '@font-face{font-family:Inter;font-style:normal;font-weight:700;font-display:swap;src:url("' . $t_font_700 . '") format("woff2")}';
		echo 'html:has(body#kanban-board-page),body#kanban-board-page,body#kanban-board-page.skin-3,';
		echo 'body#kanban-board-page .main-container,body#kanban-board-page .main-content,';
		echo 'body#kanban-board-page .page-content,body#kanban-board-page #navbar,';
		echo 'body#kanban-board-page .navbar,body#kanban-board-page .navbar.navbar-collapse,';
		echo 'body#kanban-board-page .kanban-shell,body#kanban-board-page .kanban-board,';
		echo 'body#kanban-board-page .kanban-toolbar,body#kanban-board-page .kanban-status-row,';
		echo 'body#kanban-board-page .kanban-lanes{';
		echo 'background:#0a0a0a!important;background-color:#0a0a0a!important;';
		echo '--mt-bg:#0a0a0a;--mt-nav-bg:#0a0a0a;--mt-surface:#141414;--mt-text:#f5f5f5;--mt-text-muted:#a3a3a3}';
		echo 'body#kanban-board-page,body#kanban-board-page .kanban-shell,';
		echo 'body#kanban-board-page .kanban-shell button,body#kanban-board-page .kanban-card,';
		echo 'body#kanban-board-page .kanban-card-summary,body#kanban-board-page .kanban-card-description,';
		echo 'body#kanban-board-page .kanban-card-id,body#kanban-board-page .kanban-lane-header,';
		echo 'body#kanban-board-page .kanban-status-col,body#kanban-board-page .kanban-toolbar,';
		echo 'body#kanban-board-page .kanban-btn,body#kanban-board-page .kanban-dialog{';
		echo 'font-family:Inter,ui-sans-serif,system-ui,sans-serif!important}';
		echo 'body#kanban-board-page .fa,body#kanban-board-page .ace-icon{font-family:FontAwesome!important}';
		echo 'body#kanban-board-page .kanban-card{background:#141414!important;border:1px solid rgba(255,255,255,.08)!important;padding:10px 12px!important;gap:4px!important;border-radius:4px!important}';
		echo 'body#kanban-board-page .kanban-card-summary{font-size:14px!important;font-weight:600!important;line-height:1.3!important;color:#f5f5f5!important}';
		echo 'body#kanban-board-page .kanban-card-description{font-size:13px!important;font-weight:400!important;line-height:1.4!important;color:#a3a3a3!important;opacity:1!important}';
		echo 'body#kanban-board-page .kanban-card-id{font-size:12px!important;font-weight:400!important;color:#a3a3a3!important;opacity:1!important;font-family:Inter,ui-sans-serif,system-ui,sans-serif!important}';
		echo 'body#kanban-board-page .kanban-card-footer{margin-top:2px!important;padding-top:4px!important;border-top-color:rgba(255,255,255,.08)!important}';
		echo 'body#kanban-board-page .kanban-card-priority{font-size:11px!important;font-weight:700!important}';
		echo 'body#kanban-board-page .kanban-cell{padding:8px!important;gap:8px!important}';
		echo 'body#kanban-board-page .kanban-lane-header{background:transparent!important;color:#4ade80!important;font-size:13px!important;font-weight:600!important}';
		echo 'body#kanban-board-page .kanban-status-col,body#kanban-board-page .kanban-toolbar{font-size:13px!important;color:#f5f5f5!important}';
		echo '</style>' . "\n";
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
