<?php
# MantisBT - A PHP based bugtracking system
#
# Kanban card modal — delete issue.

use Mantis\Exceptions\ClientException;

header( 'Content-Type: application/json; charset=utf-8' );

auth_ensure_user_authenticated();

$t_response = array( 'ok' => false );

try {
	if( ( $_SERVER['REQUEST_METHOD'] ?? '' ) !== 'POST' ) {
		http_response_code( HTTP_STATUS_BAD_REQUEST );
		$t_response['message'] = 'Method not allowed';
		echo json_encode( $t_response );
		exit;
	}

	form_security_validate( KanbanPlugin::FORM_ISSUE_DELETE );

	$f_bug_id = gpc_get_int( 'bug_id' );
	bug_ensure_exists( $f_bug_id );

	if( !access_has_bug_level( config_get( 'delete_bug_threshold' ), $f_bug_id ) ) {
		throw new ClientException( 'Access denied', ERROR_ACCESS_DENIED );
	}

	if( bug_is_readonly( $f_bug_id ) ) {
		throw new ClientException( 'Issue is read-only', ERROR_BUG_READ_ONLY_ACTION_DENIED );
	}

	bug_delete( $f_bug_id );

	$t_response['ok'] = true;
	echo json_encode( $t_response );
} catch( ClientException $e ) {
	http_response_code( HTTP_STATUS_FORBIDDEN );
	$t_response['message'] = $e->getMessage();
	echo json_encode( $t_response );
}
