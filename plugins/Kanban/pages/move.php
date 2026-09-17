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
 * Move issue to another status and/or target version (Kanban drag-and-drop).
 */

use Mantis\Exceptions\ClientException;

require_once dirname( __DIR__ ) . '/KanbanIssueHelper.php';

header( 'Content-Type: application/json; charset=utf-8' );

auth_ensure_user_authenticated();

$t_response = array( 'ok' => false );

try {
	form_security_validate( KanbanPlugin::FORM_MOVE );

	$f_bug_id = gpc_get_int( 'bug_id' );
	$f_status = gpc_get_int( 'status' );

	$f_bug_ids = KanbanIssueHelper::parse_gpc_bug_id_list( 'bug_ids' );
	$f_source_bug_ids = KanbanIssueHelper::parse_gpc_bug_id_list( 'source_bug_ids' );

	bug_ensure_exists( $f_bug_id );

	$t_bug = bug_get( $f_bug_id, true );

	if( !access_has_bug_level( config_get( 'view_bug_threshold' ), $f_bug_id ) ) {
		throw new ClientException(
			'Access denied',
			ERROR_ACCESS_DENIED
		);
	}

	$t_can_drag = access_has_bug_level( config_get( 'update_bug_status_threshold' ), $f_bug_id )
		|| access_has_bug_level( config_get( 'roadmap_update_threshold' ), $f_bug_id );

	$t_want_status_change = (int)$t_bug->status !== $f_status;
	$t_want_version_change = false;
	$f_target_version = (string)$t_bug->target_version;

	if( gpc_isset( 'target_version' ) ) {
		$f_target_version = gpc_get_string( 'target_version' );
		if( $f_target_version === '__none__' ) {
			$f_target_version = '';
		}
		$t_want_version_change = (string)$t_bug->target_version !== (string)$f_target_version;
	}

	$t_want_rank_save = !empty( $f_bug_ids ) || !empty( $f_source_bug_ids );
	$t_reorder_only = !$t_want_status_change && !$t_want_version_change;

	if( $t_reorder_only && !$t_want_rank_save ) {
		http_response_code( HTTP_STATUS_BAD_REQUEST );
		$t_response['message'] = plugin_lang_get( 'move_denied' );
		echo json_encode( $t_response );
		exit;
	}

	if( $t_want_rank_save && !$t_can_drag ) {
		throw new ClientException(
			'Access denied',
			ERROR_ACCESS_DENIED
		);
	}

	if( $t_want_status_change ) {
		if( !access_has_bug_level( config_get( 'update_bug_status_threshold' ), $f_bug_id ) ) {
			throw new ClientException(
				'Access denied',
				ERROR_ACCESS_DENIED
			);
		}

		if( !bug_check_workflow( (int)$t_bug->status, $f_status ) ) {
			http_response_code( HTTP_STATUS_CONFLICT );
			$t_response['message'] = plugin_lang_get( 'move_denied' );
			echo json_encode( $t_response );
			exit;
		}
	}

	if( $t_want_version_change ) {
		if( !access_has_bug_level( config_get( 'roadmap_update_threshold' ), $f_bug_id ) ) {
			throw new ClientException(
				'Access denied',
				ERROR_ACCESS_DENIED
			);
		}
	}

	if( $t_want_status_change ) {
		bug_set_field( $f_bug_id, 'status', $f_status );
	}
	if( $t_want_version_change ) {
		bug_set_field( $f_bug_id, 'target_version', $f_target_version );
	}

	if( !empty( $f_bug_ids ) ) {
		if( !KanbanIssueHelper::rank_table_exists() ) {
			http_response_code( HTTP_STATUS_UNAVAILABLE );
			$t_response['message'] = plugin_lang_get( 'rank_schema_required' );
			echo json_encode( $t_response );
			exit;
		}
		KanbanIssueHelper::save_cell_order( $f_bug_ids );
	}
	if( !empty( $f_source_bug_ids ) ) {
		KanbanIssueHelper::save_cell_order( $f_source_bug_ids );
	}

	$t_response['ok'] = true;
	echo json_encode( $t_response );
} catch( ClientException $e ) {
	http_response_code( HTTP_STATUS_FORBIDDEN );
	$t_response['message'] = $e->getMessage();
	echo json_encode( $t_response );
}
