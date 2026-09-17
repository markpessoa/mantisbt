<?php
# MantisBT - A PHP based bugtracking system
#
# Kanban card modal — load and save issue JSON API.

use Mantis\Exceptions\ClientException;

require_once dirname( __DIR__ ) . '/KanbanIssueHelper.php';

header( 'Content-Type: application/json; charset=utf-8' );

auth_ensure_user_authenticated();

$t_response = array( 'ok' => false );

try {
	$t_method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

	if( $t_method === 'GET' ) {
		$t_bug_id = gpc_get_int( 'bug_id', 0 );
		$t_project_id = helper_get_current_project();
		if( $t_bug_id === 0 ) {
			$t_project_id = gpc_get_int( 'project_id', $t_project_id );
		}

		$t_payload = KanbanIssueHelper::build_form_payload(
			$t_bug_id > 0 ? $t_bug_id : null,
			$t_project_id
		);
		echo json_encode( $t_payload );
		exit;
	}

	if( $t_method !== 'POST' ) {
		http_response_code( HTTP_STATUS_BAD_REQUEST );
		$t_response['message'] = 'Method not allowed';
		echo json_encode( $t_response );
		exit;
	}

	form_security_validate( KanbanPlugin::FORM_ISSUE );

	$f_action = gpc_get_string( 'action', 'save' );
	$f_bug_id = gpc_get_int( 'bug_id', 0 );

	if( $f_action === 'archive' ) {
		if( $f_bug_id <= 0 ) {
			throw new ClientException( 'Invalid issue', ERROR_BUG_NOT_FOUND );
		}
		KanbanIssueHelper::archive_issue( $f_bug_id );
		$t_response['ok'] = true;
		$t_response['bug_id'] = $f_bug_id;
		echo json_encode( $t_response );
		exit;
	}

	if( $f_bug_id > 0 ) {
		KanbanIssueHelper::update_issue( $f_bug_id );
		$t_response['ok'] = true;
		$t_response['bug_id'] = $f_bug_id;
		echo json_encode( $t_response );
		exit;
	}

	$t_new_id = KanbanIssueHelper::create_issue();
	$t_response['ok'] = true;
	$t_response['bug_id'] = $t_new_id;
	echo json_encode( $t_response );
} catch( ClientException $e ) {
	http_response_code( HTTP_STATUS_FORBIDDEN );
	$t_response['message'] = $e->getMessage();
	echo json_encode( $t_response );
}
