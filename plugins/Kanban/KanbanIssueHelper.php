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
	 * Priority badge map (P1–P3).
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
			if( (int)$t_priority_id <= 10 || $t_rank > 3 ) {
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
	 * Default priority id for P1.
	 *
	 * @return int
	 */
	public static function default_priority_id() {
		$t_badges = self::priority_badges();
		if( empty( $t_badges ) ) {
			return (int)config_get( 'default_bug_priority' );
		}
		$t_keys = array_keys( $t_badges );
		return (int)$t_keys[0];
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
		$t_list = get_status_option_list( $t_auth, $p_current_status, true, false, $p_project_id );

		$t_closed = (int)config_get( 'bug_closed_status_threshold', null, null, $p_project_id );
		$t_optional = KanbanPlugin::optional_status_ids();

		foreach( $t_list as $t_status_id => $t_label ) {
			$t_sid = (int)$t_status_id;
			if( $t_sid >= $t_closed ) {
				unset( $t_list[$t_status_id] );
				continue;
			}
			if( in_array( $t_sid, $t_optional, true ) ) {
				$t_show = ( $p_issue_status !== null && (int)$p_issue_status === $t_sid );
				if( !$t_show ) {
					unset( $t_list[$t_status_id] );
				}
			}
		}

		ksort( $t_list, SORT_NUMERIC );
		return $t_list;
	}

	/**
	 * @param int $p_project_id Project id.
	 *
	 * @return array<int, string>
	 */
	public static function version_options( $p_project_id ) {
		$t_versions = array();
		$t_rows = version_get_all_rows( $p_project_id, VERSION_ALL, false );
		foreach( $t_rows as $t_row ) {
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
		$t_badges = self::priority_badges();
		$t_priorities = array();
		foreach( $t_badges as $t_id => $t_badge ) {
			$t_priorities[] = array(
				'id' => (int)$t_id,
				'label' => $t_badge['label'],
				'class' => $t_badge['class'],
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
				$t_due = date( 'Y-m-d', $t_bug->due_date );
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
		foreach( self::version_options( $p_project_id ) as $t_version ) {
			$t_versions[] = array( 'name' => $t_version );
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

		return array(
			'ok' => true,
			'mode' => $t_mode,
			'issue' => $t_issue,
			'options' => array(
				'statuses' => $t_status_list,
				'versions' => $t_versions,
				'projects' => $t_projects,
				'reporters' => $t_reporters,
				'priorities' => $t_priorities,
				'tag_separator' => config_get( 'tag_separator' ),
			),
			'permissions' => array(
				'can_edit' => $t_mode === 'create' ? $t_can_create : $t_can_update,
				'can_change_status' => $t_mode === 'create' ? $t_can_create : access_has_bug_level( config_get( 'update_bug_status_threshold' ), $t_bug_id ),
				'can_change_version' => $t_mode === 'create' ? $t_can_create : access_has_bug_level( config_get( 'roadmap_update_threshold' ), $t_bug_id ),
				'can_change_project' => $t_mode === 'create' ? $t_can_create : access_has_bug_level( config_get( 'move_bug_threshold' ), $t_bug_id ),
				'can_change_reporter' => $t_mode === 'create' ? $t_can_create : $t_can_update,
				'can_change_due_date' => $t_mode === 'create'
					? $t_can_create
					: access_has_bug_level( config_get( 'due_date_update_threshold' ), $t_bug_id ),
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
		$t_ts = strtotime( $p_due_date );
		if( $t_ts === false ) {
			throw new ClientException( 'Invalid due date', ERROR_INVALID_FIELD_VALUE, array( lang_get( 'due_date' ) ) );
		}
		return $t_ts;
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
		$t_bug->due_date = self::parse_due_date( gpc_get_string( 'due_date', '' ) );
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
