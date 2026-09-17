<?php
# MantisBT - A PHP based bugtracking system
#
# Kanban issue modal — load/save helpers.

use Mantis\Exceptions\ClientException;

/**
 * Helpers for Kanban card modal JSON API.
 */
class KanbanIssueHelper {
	/**
	 * Priority badge map (P1–P5).
	 *
	 * @return array<int, array{label: string, class: string}>
	 */
	public static function priority_badges() {
		$t_priority_enum = config_get( 'priority_enum_string' );
		$t_priority_values = array_keys( MantisEnum::getAssocArrayIndexedByValues( $t_priority_enum ) );
		rsort( $t_priority_values, SORT_NUMERIC );

		$t_badges = array();
		$t_rank = 1;
		foreach( $t_priority_values as $t_priority_id ) {
			if( (int)$t_priority_id <= 10 || $t_rank > 5 ) {
				continue;
			}
			$t_badges[(int)$t_priority_id] = array(
				'label' => 'P' . $t_rank,
				'class' => 'kanban-priority-p' . $t_rank,
			);
			$t_rank++;
		}
		return $t_badges;
	}

	/**
	 * All priority levels (Mantis enum labels).
	 *
	 * @return array<int, string>
	 */
	public static function priority_options() {
		$t_priority_enum = config_get( 'priority_enum_string' );
		$t_values = MantisEnum::getAssocArrayIndexedByValues( $t_priority_enum );
		$t_out = array();
		foreach( $t_values as $t_id => $t_code ) {
			$t_out[(int)$t_id] = get_enum_element( 'priority', $t_id );
		}
		krsort( $t_out, SORT_NUMERIC );
		return $t_out;
	}

	/**
	 * @return int
	 */
	public static function default_priority_id() {
		return (int)config_get( 'default_bug_priority' );
	}

	/**
	 * Status options for the modal (Kanban rules).
	 *
	 * @param int      $p_project_id     Project id.
	 * @param int      $p_current_status Current status.
	 * @param int|null $p_issue_status   Issue status when editing optional columns.
	 *
	 * @return array<int, string>
	 */
	public static function status_options( $p_project_id, $p_current_status, $p_issue_status = null ) {
		$t_auth = access_get_project_level( $p_project_id );
		$t_closed = (int)config_get( 'bug_closed_status_threshold', null, null, $p_project_id );
		$t_optional = KanbanPlugin::optional_status_ids();
		$t_status_enum = config_get( 'status_enum_string', null, null, $p_project_id );
		$t_all = MantisEnum::getAssocArrayIndexedByValues( $t_status_enum );

		$t_list = array();
		foreach( $t_all as $t_status_id => $t_status_code ) {
			$t_sid = (int)$t_status_id;
			if( $t_sid >= $t_closed ) {
				continue;
			}
			if( in_array( $t_sid, $t_optional, true ) ) {
				if( $p_issue_status === null || (int)$p_issue_status !== $t_sid ) {
					continue;
				}
			}
			if( !access_compare_level( $t_auth, access_get_status_threshold( $t_sid, $p_project_id ) ) ) {
				continue;
			}
			$t_list[$t_sid] = get_enum_element( 'status', $t_sid );
		}

		$t_current = (int)$p_current_status;
		if( $t_current > 0 && $t_current < $t_closed && !isset( $t_list[$t_current] ) ) {
			if( access_compare_level( $t_auth, access_get_status_threshold( $t_current, $p_project_id ) ) ) {
				$t_list[$t_current] = get_enum_element( 'status', $t_current );
			}
		}

		ksort( $t_list, SORT_NUMERIC );
		return $t_list;
	}

	/**
	 * Project versions sorted by date (oldest first), matching the Kanban board.
	 *
	 * @param int $p_project_id Project id.
	 *
	 * @return array
	 */
	public static function version_row_date_order( array $p_row ) {
		$t_val = $p_row['date_order'];
		if( is_numeric( $t_val ) ) {
			return (int)$t_val;
		}
		return (int)date_strtotime( $t_val );
	}

	public static function sorted_version_rows( $p_project_id ) {
		$t_rows = version_get_all_rows( $p_project_id, VERSION_ALL, false );
		usort(
			$t_rows,
			function( $p_a, $p_b ) {
				$t_cmp = self::version_row_date_order( $p_a ) - self::version_row_date_order( $p_b );
				if( $t_cmp !== 0 ) {
					return $t_cmp;
				}
				return (int)$p_a['id'] - (int)$p_b['id'];
			}
		);
		return $t_rows;
	}

	/**
	 * @param int $p_project_id Project id.
	 *
	 * @return string[]
	 */
	public static function version_options( $p_project_id ) {
		$t_versions = array();
		foreach( self::sorted_version_rows( $p_project_id ) as $t_row ) {
			$t_versions[] = $t_row['version'];
		}
		return $t_versions;
	}

	/**
	 * @return array<int, string>
	 */
	public static function project_options() {
		$t_user_id = auth_get_current_user_id();
		$t_projects = user_get_accessible_projects( $t_user_id );
		$t_out = array();
		foreach( $t_projects as $t_id ) {
			$t_out[(int)$t_id] = project_get_name( $t_id );
		}
		return $t_out;
	}

	/**
	 * @param int $p_project_id Project id.
	 *
	 * @return array<int, string>
	 */
	public static function reporter_options( $p_project_id ) {
		$t_users = project_get_all_user_rows( $p_project_id, config_get( 'report_bug_threshold' ) );
		$t_out = array();
		foreach( $t_users as $t_id => $t_user ) {
			$t_out[(int)$t_id] = user_get_name( $t_id );
		}
		asort( $t_out, SORT_NATURAL | SORT_FLAG_CASE );
		return $t_out;
	}

	/**
	 * @param int $p_project_id Project id.
	 *
	 * @return int
	 */
	/**
	 * Whether the Kanban board should load the Mantis date picker assets.
	 *
	 * @param int $p_project_id Project id.
	 *
	 * @return bool
	 */
	public static function board_uses_due_date( $p_project_id ) {
		$t_report_fields = config_get( 'bug_report_page_fields', null, null, $p_project_id );
		$t_view_fields = config_get( 'bug_view_page_fields', null, null, $p_project_id );
		return in_array( 'due_date', $t_report_fields, true )
			|| in_array( 'due_date', $t_view_fields, true );
	}

	/**
	 * @param int      $p_project_id Project id.
	 * @param int|null $p_bug_id     Bug id when editing.
	 *
	 * @return bool
	 */
	public static function can_show_due_date( $p_project_id, $p_bug_id = null ) {
		if( $p_bug_id !== null && $p_bug_id > 0 ) {
			$t_fields = config_get( 'bug_view_page_fields', null, null, $p_project_id );
			if( !in_array( 'due_date', $t_fields, true ) ) {
				return false;
			}
			return access_has_bug_level( config_get( 'due_date_view_threshold' ), $p_bug_id );
		}
		$t_fields = config_get( 'bug_report_page_fields', null, null, $p_project_id );
		if( !in_array( 'due_date', $t_fields, true ) ) {
			return false;
		}
		return access_has_project_level(
			config_get( 'due_date_update_threshold' ),
			$p_project_id,
			auth_get_current_user_id()
		);
	}

	/**
	 * @param int      $p_project_id Project id.
	 * @param int|null $p_bug_id     Bug id when editing.
	 *
	 * @return bool
	 */
	public static function can_update_due_date( $p_project_id, $p_bug_id = null ) {
		if( !self::can_show_due_date( $p_project_id, $p_bug_id ) ) {
			return false;
		}
		if( $p_bug_id !== null && $p_bug_id > 0 ) {
			return access_has_bug_level( config_get( 'due_date_update_threshold' ), $p_bug_id );
		}
		return access_has_project_level(
			config_get( 'due_date_update_threshold' ),
			$p_project_id,
			auth_get_current_user_id()
		);
	}

	public static function default_category_id( $p_project_id ) {
		if( config_get( 'allow_no_category', null, null, $p_project_id ) ) {
			return 0;
		}
		$t_rows = category_get_all_rows( $p_project_id, null, true, true );
		if( empty( $t_rows ) ) {
			return 0;
		}
		return (int)$t_rows[0]['id'];
	}

	/**
	 * Build GET payload for create or edit.
	 *
	 * @param int|null $p_bug_id     Bug id or null for create.
	 * @param int      $p_project_id Project for create / context.
	 *
	 * @return array
	 */
	public static function build_form_payload( $p_bug_id, $p_project_id ) {
		$t_priorities = array();
		foreach( self::priority_options() as $t_id => $t_label ) {
			$t_priorities[] = array(
				'id' => (int)$t_id,
				'label' => $t_label,
			);
		}

		$t_mode = 'create';
		$t_issue = array(
			'id' => 0,
			'project_id' => (int)$p_project_id,
			'summary' => '',
			'description' => '',
			'reporter_id' => auth_get_current_user_id(),
			'due_date' => '',
			'priority' => self::default_priority_id(),
			'status' => (int)config_get( 'bug_submit_status', null, null, $p_project_id ),
			'target_version' => '',
			'tag_string' => '',
			'date_submitted' => '',
			'last_updated' => '',
		);
		$t_issue_status = null;

		if( $p_bug_id !== null && $p_bug_id > 0 ) {
			bug_ensure_exists( $p_bug_id );
			if( !access_has_bug_level( config_get( 'view_bug_threshold' ), $p_bug_id ) ) {
				throw new ClientException( 'Access denied', ERROR_ACCESS_DENIED );
			}
			$t_bug = bug_get( $p_bug_id, true );
			$t_mode = 'edit';
			$t_issue_status = (int)$t_bug->status;
			$p_project_id = (int)$t_bug->project_id;

			$t_tags = tag_bug_get_attached( $p_bug_id );
			$t_tag_names = array();
			foreach( $t_tags as $t_tag ) {
				$t_tag_names[] = $t_tag['name'];
			}

			$t_due = '';
			if( !date_is_null( $t_bug->due_date ) ) {
				$t_due = date( config_get( 'normal_date_format' ), $t_bug->due_date );
			}

			$t_issue = array(
				'id' => (int)$t_bug->id,
				'project_id' => (int)$t_bug->project_id,
				'summary' => $t_bug->summary,
				'description' => $t_bug->description,
				'reporter_id' => (int)$t_bug->reporter_id,
				'due_date' => $t_due,
				'priority' => (int)$t_bug->priority,
				'status' => (int)$t_bug->status,
				'target_version' => (string)$t_bug->target_version,
				'tag_string' => implode( config_get( 'tag_separator' ) . ' ', $t_tag_names ),
				'date_submitted' => date( config_get( 'normal_date_format' ), $t_bug->date_submitted ),
				'last_updated' => date( config_get( 'normal_date_format' ), $t_bug->last_updated ),
			);
		} else {
			access_ensure_project_level( config_get( 'report_bug_threshold' ), $p_project_id );
		}

		$t_statuses = self::status_options( $p_project_id, $t_issue['status'], $t_issue_status );
		$t_status_list = array();
		foreach( $t_statuses as $t_id => $t_label ) {
			$t_status_list[] = array(
				'id' => (int)$t_id,
				'label' => $t_label,
				'color' => get_status_color( $t_id ),
			);
		}

		$t_versions = array();
		foreach( self::sorted_version_rows( $p_project_id ) as $t_row ) {
			$t_versions[] = array(
				'id' => (int)$t_row['id'],
				'name' => $t_row['version'],
				'date_order' => self::version_row_date_order( $t_row ),
			);
		}

		$t_projects = array();
		foreach( self::project_options() as $t_id => $t_name ) {
			$t_projects[] = array( 'id' => (int)$t_id, 'name' => $t_name );
		}

		$t_reporters = array();
		foreach( self::reporter_options( $p_project_id ) as $t_id => $t_name ) {
			$t_reporters[] = array( 'id' => (int)$t_id, 'name' => $t_name );
		}

		$t_bug_id = ( $p_bug_id !== null && $p_bug_id > 0 ) ? (int)$p_bug_id : 0;

		$t_can_create = access_has_project_level( config_get( 'report_bug_threshold' ), $p_project_id );
		$t_can_update = $t_bug_id > 0 && access_has_bug_level( config_get( 'update_bug_threshold' ), $t_bug_id );
		$t_can_delete = $t_bug_id > 0 && access_has_bug_level( config_get( 'delete_bug_threshold' ), $t_bug_id );
		$t_can_archive = false;
		if( $t_bug_id > 0 ) {
			$t_closed = (int)config_get( 'bug_closed_status_threshold', null, null, $p_project_id );
			$t_can_archive = (int)$t_issue['status'] < $t_closed
				&& access_has_bug_level( config_get( 'update_bug_status_threshold' ), $t_bug_id );
		}

		$t_title = lang_get( 'report_bug_link' );
		$t_meta_created = '';
		$t_meta_updated = '';
		if( $t_mode === 'edit' && $t_bug_id > 0 ) {
			$t_title = bug_format_id( $t_bug_id );
			$t_meta_created = lang_get( 'date_submitted' ) . ': ' . $t_issue['date_submitted'];
			$t_meta_updated = lang_get( 'last_update' ) . ': ' . $t_issue['last_updated'];
		}

		return array(
			'ok' => true,
			'mode' => $t_mode,
			'issue' => $t_issue,
			'ui' => array(
				'title' => $t_title,
				'meta_created' => $t_meta_created,
				'meta_updated' => $t_meta_updated,
				'save_create' => lang_get( 'submit_report_button' ),
				'save_update' => lang_get( 'update_information_button' ),
				'save_new' => lang_get( 'report_more_bugs' ),
				'empty_version' => '',
			),
			'options' => array(
				'statuses' => $t_status_list,
				'versions' => $t_versions,
				'projects' => $t_projects,
				'reporters' => $t_reporters,
				'priorities' => $t_priorities,
				'tag_separator' => config_get( 'tag_separator' ),
				'tag_hint' => sprintf( lang_get( 'tag_separate_by' ), config_get( 'tag_separator' ) ),
			),
			'permissions' => array(
				'can_edit' => $t_mode === 'create' ? $t_can_create : $t_can_update,
				'can_change_status' => $t_mode === 'create' ? $t_can_create : access_has_bug_level( config_get( 'update_bug_status_threshold' ), $t_bug_id ),
				'can_change_version' => $t_mode === 'create' ? $t_can_create : access_has_bug_level( config_get( 'roadmap_update_threshold' ), $t_bug_id ),
				'can_change_project' => $t_mode === 'create' ? $t_can_create : access_has_bug_level( config_get( 'move_bug_threshold' ), $t_bug_id ),
				'can_change_reporter' => $t_mode === 'create' ? $t_can_create : $t_can_update,
				'can_show_due_date' => self::can_show_due_date( $p_project_id, $t_bug_id > 0 ? $t_bug_id : null ),
				'can_change_due_date' => self::can_update_due_date( $p_project_id, $t_bug_id > 0 ? $t_bug_id : null ),
				'can_change_tags' => $t_mode === 'create'
					? $t_can_create && access_has_project_level( config_get( 'tag_attach_threshold' ), $p_project_id )
					: access_has_bug_level( config_get( 'tag_attach_threshold' ), $t_bug_id ),
				'can_delete' => $t_can_delete,
				'can_archive' => $t_can_archive,
			),
		);
	}

	/**
	 * @param string $p_due_date Due date (Y-m-d or empty).
	 *
	 * @return int
	 */
	public static function parse_due_date( $p_due_date ) {
		if( is_blank( $p_due_date ) ) {
			return date_get_null();
		}
		return date_strtotime( $p_due_date );
	}

	/**
	 * Sync tags on an issue from a tag string.
	 *
	 * @param int    $p_bug_id     Bug id.
	 * @param string $p_tag_string Tag string.
	 *
	 * @return void
	 */
	public static function sync_tags( $p_bug_id, $p_tag_string ) {
		if( !access_has_bug_level( config_get( 'tag_attach_threshold' ), $p_bug_id ) ) {
			return;
		}

		$t_attached = tag_bug_get_attached( $p_bug_id );
		$t_wanted = tag_parse_string( $p_tag_string );
		$t_wanted_ids = array();
		foreach( $t_wanted as $t_row ) {
			if( $t_row['id'] > 0 ) {
				$t_wanted_ids[(int)$t_row['id']] = true;
			}
		}

		foreach( $t_attached as $t_tag ) {
			if( !isset( $t_wanted_ids[(int)$t_tag['id']] ) ) {
				tag_bug_detach( (int)$t_tag['id'], $p_bug_id );
			}
		}

		if( !is_blank( $p_tag_string ) ) {
			$t_result = tag_attach_many( $p_bug_id, $p_tag_string );
			if( is_array( $t_result ) ) {
				throw new ClientException( 'Invalid tags', ERROR_INVALID_FIELD_VALUE, array( lang_get( 'tags' ) ) );
			}
		}
	}

	/**
	 * Create issue from POST fields.
	 *
	 * @return int New bug id.
	 */
	public static function create_issue() {
		$f_project_id = gpc_get_int( 'project_id' );
		access_ensure_project_level( config_get( 'report_bug_threshold' ), $f_project_id );

		$t_bug = new BugData();
		$t_bug->project_id = $f_project_id;
		$t_bug->reporter_id = gpc_get_int( 'reporter_id', auth_get_current_user_id() );
		$t_bug->summary = gpc_get_string( 'summary' );
		$t_bug->description = gpc_get_string( 'description' );
		$t_bug->priority = gpc_get_int( 'priority', self::default_priority_id() );
		$t_bug->status = gpc_get_int( 'status', (int)config_get( 'bug_submit_status', null, null, $f_project_id ) );
		$t_bug->target_version = gpc_get_string( 'target_version', '' );
		if( $t_bug->target_version === '__none__' ) {
			$t_bug->target_version = '';
		}
		$t_bug->category_id = self::default_category_id( $f_project_id );
		if( self::can_update_due_date( $f_project_id ) && gpc_isset( 'due_date' ) ) {
			$t_bug->due_date = self::parse_due_date( gpc_get_string( 'due_date' ) );
		}
		$t_bug->severity = (int)config_get( 'default_bug_severity' );
		$t_bug->reproducibility = (int)config_get( 'default_bug_reproducibility' );
		$t_bug->view_state = (int)config_get( 'default_bug_view_status' );

		$t_bug_id = $t_bug->create();
		self::sync_tags( $t_bug_id, gpc_get_string( 'tag_string', '' ) );

		return $t_bug_id;
	}

	/**
	 * Update issue from POST fields.
	 *
	 * @param int $p_bug_id Bug id.
	 *
	 * @return void
	 */
	public static function update_issue( $p_bug_id ) {
		bug_ensure_exists( $p_bug_id );
		access_ensure_bug_level( config_get( 'update_bug_threshold' ), $p_bug_id );

		if( bug_is_readonly( $p_bug_id ) ) {
			throw new ClientException( 'Issue is read-only', ERROR_BUG_READ_ONLY_ACTION_DENIED );
		}

		$t_existing = bug_get( $p_bug_id, true );
		$t_bug = clone $t_existing;

		$t_bug->summary = gpc_get_string( 'summary', $t_bug->summary );
		$t_bug->description = gpc_get_string( 'description', $t_bug->description );

		if( gpc_isset( 'reporter_id' ) ) {
			$t_reporter_id = gpc_get_int( 'reporter_id' );
			if( $t_reporter_id != $t_existing->reporter_id ) {
				user_ensure_exists( $t_reporter_id );
				$t_bug->reporter_id = $t_reporter_id;
			}
		}

		if( gpc_isset( 'due_date' ) ) {
			if( access_has_bug_level( config_get( 'due_date_update_threshold' ), $p_bug_id ) ) {
				$t_bug->due_date = self::parse_due_date( gpc_get_string( 'due_date' ) );
			}
		}

		if( gpc_isset( 'priority' ) ) {
			$t_bug->priority = gpc_get_int( 'priority' );
		}

		$f_status = gpc_get_int( 'status', (int)$t_existing->status );
		if( (int)$t_existing->status !== $f_status ) {
			access_ensure_bug_level( config_get( 'update_bug_status_threshold' ), $p_bug_id );
			if( !bug_check_workflow( (int)$t_existing->status, $f_status ) ) {
				throw new ClientException( 'Invalid status', ERROR_CUSTOM_FIELD_INVALID_VALUE, array( lang_get( 'status' ) ) );
			}
			$t_bug->status = $f_status;
		}

		if( gpc_isset( 'target_version' ) ) {
			$f_version = gpc_get_string( 'target_version' );
			if( $f_version === '__none__' ) {
				$f_version = '';
			}
			if( (string)$t_existing->target_version !== (string)$f_version ) {
				access_ensure_bug_level( config_get( 'roadmap_update_threshold' ), $p_bug_id );
				$t_bug->target_version = $f_version;
			}
		}

		$f_project_id = gpc_get_int( 'project_id', (int)$t_existing->project_id );
		if( (int)$t_existing->project_id !== $f_project_id ) {
			access_ensure_bug_level( config_get( 'move_bug_threshold' ), $p_bug_id );
			bug_move( $p_bug_id, $f_project_id );
			$t_bug->project_id = $f_project_id;
		}

		$t_text_update = ( $t_existing->description != $t_bug->description );
		$t_bug->update( $t_text_update, true );

		if( gpc_isset( 'tag_string' ) ) {
			self::sync_tags( $p_bug_id, gpc_get_string( 'tag_string' ) );
		}
	}

	/**
	 * Close (archive) an issue.
	 *
	 * @param int $p_bug_id Bug id.
	 *
	 * @return void
	 */
	public static function archive_issue( $p_bug_id ) {
		bug_ensure_exists( $p_bug_id );
		access_ensure_bug_level( config_get( 'update_bug_status_threshold' ), $p_bug_id );

		$t_bug = bug_get( $p_bug_id );
		$t_closed = (int)config_get( 'bug_closed_status_threshold', null, null, $t_bug->project_id );
		if( (int)$t_bug->status >= $t_closed ) {
			return;
		}
		if( !bug_check_workflow( (int)$t_bug->status, $t_closed ) ) {
			throw new ClientException( 'Cannot close issue', ERROR_CUSTOM_FIELD_INVALID_VALUE, array( lang_get( 'status' ) ) );
		}
		bug_set_field( $p_bug_id, 'status', $t_closed );
	}
}
