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
 * Kanban board page.
 */

auth_ensure_user_authenticated();

require_once dirname( __DIR__ ) . '/KanbanIssueHelper.php';

$t_project_id = helper_get_current_project();
$t_board_url = plugin_page( 'board' );
$t_move_url = plugin_page( 'move' );
$t_issue_url = plugin_page( 'issue' );
$t_issue_delete_url = plugin_page( 'issue_delete' );
$t_form_name = KanbanPlugin::FORM_MOVE;
$t_security_token = form_security_token( $t_form_name );
$t_issue_form_name = KanbanPlugin::FORM_ISSUE;
$t_issue_token = form_security_token( $t_issue_form_name );
$t_issue_delete_form_name = KanbanPlugin::FORM_ISSUE_DELETE;
$t_issue_delete_token = form_security_token( $t_issue_delete_form_name );
$t_can_report = access_has_project_level( config_get( 'report_bug_threshold' ), $t_project_id );
$t_kanban_due_date = KanbanIssueHelper::board_uses_due_date( $t_project_id );
if( $t_kanban_due_date ) {
	require_api( 'datetimepicker_api.php' );
}

$t_page_number = 1;
$t_per_page = -1;
$t_page_count = 0;
$t_bug_count = 0;

$t_rows = filter_get_bug_rows(
	$t_page_number,
	$t_per_page,
	$t_page_count,
	$t_bug_count,
	null,
	$t_project_id,
	null,
	true
);

if( $t_rows === false ) {
	$t_rows = array();
}

$t_closed_threshold = (int)config_get( 'bug_closed_status_threshold' );
$t_optional_statuses = KanbanPlugin::optional_status_ids();
$t_rows = array_values( array_filter(
	$t_rows,
	function( $p_bug ) use ( $t_closed_threshold ) {
		return (int)$p_bug->status < $t_closed_threshold;
	}
) );

$t_by_version = array();
foreach( $t_rows as $t_bug ) {
	$t_version_key = (string)$t_bug->target_version;
	if( !isset( $t_by_version[$t_version_key] ) ) {
		$t_by_version[$t_version_key] = array();
	}
	$t_status = (int)$t_bug->status;
	if( !isset( $t_by_version[$t_version_key][$t_status] ) ) {
		$t_by_version[$t_version_key][$t_status] = array();
	}
	$t_by_version[$t_version_key][$t_status][] = $t_bug;
}

$t_status_enum = config_get( 'status_enum_string' );
$t_statuses = MantisEnum::getAssocArrayIndexedByValues( $t_status_enum );

$t_status_totals = array();
foreach( $t_statuses as $t_status_id => $t_status_code ) {
	$t_status_totals[(int)$t_status_id] = 0;
}
foreach( $t_rows as $t_bug ) {
	$t_sid = (int)$t_bug->status;
	if( isset( $t_status_totals[$t_sid] ) ) {
		$t_status_totals[$t_sid]++;
	}
}

foreach( $t_statuses as $t_status_id => $t_status_code ) {
	$t_sid = (int)$t_status_id;
	if( $t_sid >= $t_closed_threshold ) {
		unset( $t_statuses[$t_status_id] );
		continue;
	}
	# Retorno / admitido only appear when they have at least one card.
	if( in_array( $t_sid, $t_optional_statuses, true ) && $t_status_totals[$t_sid] === 0 ) {
		unset( $t_statuses[$t_status_id] );
	}
}
$t_status_count = count( $t_statuses );
if( $t_status_count < 1 ) {
	$t_status_count = 1;
}

$t_priority_enum = config_get( 'priority_enum_string' );
$t_priority_values = array_keys( MantisEnum::getAssocArrayIndexedByValues( $t_priority_enum ) );
rsort( $t_priority_values, SORT_NUMERIC );
$t_priority_badges = array();
$t_priority_rank = 1;
foreach( $t_priority_values as $t_priority_id ) {
	if( (int)$t_priority_id <= 10 || $t_priority_rank > 3 ) {
		continue;
	}
	$t_priority_badges[$t_priority_id] = array(
		'label' => 'P' . $t_priority_rank,
		'class' => 'kanban-priority-p' . $t_priority_rank,
	);
	$t_priority_rank++;
}

$t_version_meta = array();
$t_version_rows = KanbanIssueHelper::sorted_version_rows( $t_project_id );
foreach( $t_version_rows as $t_version_row ) {
	$t_version_meta[$t_version_row['version']] = $t_version_row;
}

$t_lanes = array();
$t_lane_keys_added = array();
foreach( $t_version_rows as $t_version_row ) {
	$t_vname = $t_version_row['version'];
	$t_lane_bugs = 0;
	if( isset( $t_by_version[$t_vname] ) ) {
		foreach( $t_by_version[$t_vname] as $t_status_bugs ) {
			$t_lane_bugs += count( $t_status_bugs );
		}
	}
	$t_lanes[] = array(
		'key' => $t_vname,
		'label' => $t_vname,
		'date_order' => KanbanIssueHelper::version_row_date_order( $t_version_row ),
		'bugs' => isset( $t_by_version[$t_vname] ) ? $t_by_version[$t_vname] : array(),
		'count' => $t_lane_bugs,
	);
	$t_lane_keys_added[$t_vname] = true;
}

foreach( $t_by_version as $t_vname => $t_version_bugs ) {
	if( $t_vname === '' || isset( $t_lane_keys_added[$t_vname] ) ) {
		continue;
	}
	$t_lane_bugs = 0;
	foreach( $t_version_bugs as $t_status_bugs ) {
		$t_lane_bugs += count( $t_status_bugs );
	}
	if( $t_lane_bugs > 0 ) {
		$t_lanes[] = array(
			'key' => $t_vname,
			'label' => $t_vname,
			'date_order' => 0,
			'bugs' => $t_version_bugs,
			'count' => $t_lane_bugs,
		);
		$t_lane_keys_added[$t_vname] = true;
	}
}

usort(
	$t_lanes,
	function( $p_a, $p_b ) {
		$t_a = (int)$p_a['date_order'];
		$t_b = (int)$p_b['date_order'];
		if( $t_a === 0 && $t_b === 0 ) {
			return 0;
		}
		if( $t_a === 0 ) {
			return 1;
		}
		if( $t_b === 0 ) {
			return -1;
		}
		return $t_a - $t_b;
	}
);

$t_none_key = '';
$t_none_count = 0;
if( isset( $t_by_version[$t_none_key] ) ) {
	foreach( $t_by_version[$t_none_key] as $t_status_bugs ) {
		$t_none_count += count( $t_status_bugs );
	}
}
if( $t_none_count > 0 ) {
	$t_lanes[] = array(
		'key' => '__none__',
		'label' => plugin_lang_get( 'no_version' ),
		'date_order' => 0,
		'bugs' => $t_by_version[$t_none_key],
		'count' => $t_none_count,
	);
}

$t_max_per_cell = 200;
$t_short_date = config_get( 'short_date_format' );
$t_project_name = project_get_name( $t_project_id );
$t_total_cards = count( $t_rows );

layout_page_header_begin( plugin_lang_get( 'title' ) );
layout_page_header_end( 'kanban-board-page' );
layout_page_begin( $t_board_url );
?>

<div class="kanban-shell">
	<header class="kanban-toolbar">
		<div class="kanban-toolbar-title">
			<span class="kanban-toolbar-prompt">&gt;</span>
			<span class="kanban-toolbar-name"><?php echo string_display_line( $t_project_name ); ?></span>
		</div>
		<div class="kanban-toolbar-actions">
			<span class="kanban-toolbar-count"><?php echo sprintf( plugin_lang_get( 'cards_count' ), $t_total_cards ); ?></span>
			<?php if( $t_can_report ) { ?>
			<button type="button" class="kanban-btn kanban-btn-new" id="kanban-new-issue">
				<?php echo string_display_line( lang_get( 'report_bug_link' ) ); ?>
			</button>
			<?php } ?>
		</div>
	</header>

	<div id="kanban-board"
		class="kanban-board"
		style="--kanban-cols: <?php echo (int)$t_status_count; ?>;"
		data-move-url="<?php echo string_attribute( $t_move_url ); ?>"
		data-issue-url="<?php echo string_attribute( $t_issue_url ); ?>"
		data-issue-delete-url="<?php echo string_attribute( $t_issue_delete_url ); ?>"
		data-project-id="<?php echo (int)$t_project_id; ?>"
		data-form-token-name="<?php echo string_attribute( $t_form_name . '_token' ); ?>"
		data-form-token-value="<?php echo string_attribute( $t_security_token ); ?>"
		data-issue-token-name="<?php echo string_attribute( $t_issue_form_name . '_token' ); ?>"
		data-issue-token-value="<?php echo string_attribute( $t_issue_token ); ?>"
		data-issue-delete-token-name="<?php echo string_attribute( $t_issue_delete_form_name . '_token' ); ?>"
		data-issue-delete-token-value="<?php echo string_attribute( $t_issue_delete_token ); ?>"
		data-empty-label="<?php echo string_attribute( plugin_lang_get( 'empty' ) ); ?>">

		<div class="kanban-status-row">
<?php
foreach( $t_statuses as $t_status_id => $t_status_code ) {
	$t_label = get_enum_element( 'status', $t_status_id );
	$t_color = get_status_color( $t_status_id );
	?>
			<div class="kanban-status-col" data-status="<?php echo (int)$t_status_id; ?>">
				<span class="kanban-status-dot" style="background-color: <?php echo string_attribute( $t_color ); ?>;"></span>
				<span class="kanban-status-label"><?php echo string_display_line( $t_label ); ?></span>
				<span class="kanban-status-count"><?php echo (int)$t_status_totals[$t_status_id]; ?></span>
			</div>
<?php
}
?>
		</div>

		<div class="kanban-lanes">
<?php
foreach( $t_lanes as $t_lane ) {
	$t_lane_key = $t_lane['key'];
	$t_lane_date = '';
	if( $t_lane['date_order'] > 0 ) {
		$t_lane_date = date( 'd/m/Y', $t_lane['date_order'] );
	}
	?>
			<section class="kanban-lane" data-lane="<?php echo string_attribute( $t_lane_key ); ?>">
				<button type="button" class="kanban-lane-header" aria-expanded="true">
					<span class="kanban-lane-chevron" aria-hidden="true">&gt;</span>
					<span class="kanban-lane-title"><?php echo string_display_line( $t_lane['label'] ); ?></span>
					<?php if( $t_lane_date !== '' ) { ?>
					<span class="kanban-lane-date"><?php echo string_display_line( $t_lane_date ); ?></span>
					<?php } ?>
					<span class="kanban-lane-count"><?php echo (int)$t_lane['count']; ?></span>
				</button>
				<div class="kanban-lane-cells">
<?php
	foreach( $t_statuses as $t_status_id => $t_status_code ) {
		$t_issues = isset( $t_lane['bugs'][(int)$t_status_id] ) ? $t_lane['bugs'][(int)$t_status_id] : array();
		$t_shown = array_slice( $t_issues, 0, $t_max_per_cell );
		$t_hidden = count( $t_issues ) - count( $t_shown );
		$t_version_date = 0;
		if( $t_lane_key !== '__none__' && isset( $t_version_meta[$t_lane_key] ) ) {
			$t_version_date = (int)$t_version_meta[$t_lane_key]['date_order'];
		}
		?>
					<div class="kanban-cell" data-status="<?php echo (int)$t_status_id; ?>" data-lane="<?php echo string_attribute( $t_lane_key ); ?>" data-sortable="1">
<?php
		if( count( $t_shown ) === 0 ) {
			echo '<span class="kanban-empty">' . plugin_lang_get( 'empty' ) . '</span>';
		} else {
			foreach( $t_shown as $t_bug ) {
				$t_bug_id = (int)$t_bug->id;
				$t_can_drag_status = access_has_bug_level( config_get( 'update_bug_status_threshold' ), $t_bug_id );
				$t_can_drag_version = access_has_bug_level( config_get( 'roadmap_update_threshold' ), $t_bug_id );
				$t_can_drag = $t_can_drag_status || $t_can_drag_version;
				$t_view_url = string_sanitize_url( string_get_bug_view_url( $t_bug_id ), true );
				$t_priority = (int)$t_bug->priority;
				$t_badge_label = 'P?';
				$t_badge_class = 'kanban-priority-unknown';
				if( isset( $t_priority_badges[$t_priority] ) ) {
					$t_badge_label = $t_priority_badges[$t_priority]['label'];
					$t_badge_class = $t_priority_badges[$t_priority]['class'];
				}
				$t_description = trim( preg_replace( '/\s+/', ' ', strip_tags( $t_bug->description ) ) );
				$t_date = '';
				if( !date_is_null( $t_bug->due_date ) ) {
					$t_date = date( $t_short_date, $t_bug->due_date );
				} elseif( $t_version_date > 0 ) {
					$t_date = date( 'd/m/Y', $t_version_date );
				}
				?>
						<article class="kanban-card<?php echo $t_can_drag ? ' kanban-card-draggable' : ' kanban-card-clickable'; ?>"
							data-bug-id="<?php echo $t_bug_id; ?>"
							data-href="<?php echo string_attribute( $t_view_url ); ?>"
							data-lane="<?php echo string_attribute( $t_lane_key ); ?>"
							data-priority="<?php echo $t_priority; ?>"
							tabindex="0"
							role="link">
							<div class="kanban-card-header">
								<span class="kanban-card-priority <?php echo string_attribute( $t_badge_class ); ?>"><?php echo string_display_line( $t_badge_label ); ?></span>
								<?php if( $t_can_drag ) { ?>
								<button type="button"
									class="kanban-card-handle"
									aria-label="<?php echo string_attribute( plugin_lang_get( 'drag_handle' ) ); ?>"
									title="<?php echo string_attribute( plugin_lang_get( 'drag_handle' ) ); ?>">
									<span class="kanban-card-handle-grip" aria-hidden="true"></span>
								</button>
								<?php } ?>
							</div>
							<div class="kanban-card-body">
								<span class="kanban-card-summary" title="<?php echo string_attribute( $t_bug->summary ); ?>"><?php echo string_display_line( $t_bug->summary ); ?></span>
								<?php if( $t_description !== '' ) { ?>
								<span class="kanban-card-description" title="<?php echo string_attribute( $t_description ); ?>"><?php echo string_display_line( $t_description ); ?></span>
								<?php } ?>
								<?php if( $t_date !== '' ) { ?>
								<span class="kanban-card-date"><?php echo string_display_line( $t_date ); ?></span>
								<?php } ?>
							</div>
						</article>
<?php
			}
			if( $t_hidden > 0 ) {
				echo '<p class="kanban-cell-more">' . sprintf( plugin_lang_get( 'more_issues' ), $t_hidden ) . '</p>';
			}
		}
		?>
					</div>
<?php
	}
	?>
				</div>
			</section>
<?php
}
?>
		</div>
	</div>
</div>

<dialog id="kanban-issue-dialog" class="kanban-dialog" aria-labelledby="kanban-dialog-title"
	data-confirm-delete="<?php echo string_attribute( lang_get( 'delete_bug_sure_msg' ) ); ?>">
	<form id="kanban-issue-form" method="dialog" class="kanban-dialog-form">
		<header class="kanban-dialog-header">
			<h2 id="kanban-dialog-title" class="kanban-dialog-title"></h2>
			<button type="button" class="kanban-dialog-close" id="kanban-dialog-close" aria-label="<?php echo string_attribute( lang_get( 'close' ) ); ?>">&times;</button>
		</header>
		<div class="kanban-dialog-body">
			<p class="kanban-dialog-error" id="kanban-dialog-error" hidden></p>
			<div class="kanban-field">
				<label for="kanban-field-summary"><?php echo string_display_line( lang_get( 'summary' ) ); ?></label>
				<input type="text" id="kanban-field-summary" name="summary" maxlength="128" required />
			</div>
			<div class="kanban-field">
				<label for="kanban-field-description"><?php echo string_display_line( lang_get( 'description' ) ); ?></label>
				<textarea id="kanban-field-description" name="description" rows="5" required></textarea>
			</div>
			<div class="kanban-field-row kanban-field-row-2">
				<div class="kanban-field">
					<label for="kanban-field-reporter"><?php echo string_display_line( lang_get( 'reporter' ) ); ?></label>
					<select id="kanban-field-reporter" name="reporter_id"></select>
				</div>
				<div class="kanban-field kanban-field-due" id="kanban-due-date-wrap">
					<label for="due_date"><?php echo string_display_line( lang_get( 'due_date' ) ); ?></label>
					<div class="kanban-due-date-input">
						<?php datetimepicker_print( '', 'due_date' ); ?>
					</div>
				</div>
			</div>
			<div class="kanban-field-row kanban-field-row-3">
				<div class="kanban-field">
					<label for="kanban-field-priority"><?php echo string_display_line( lang_get( 'priority' ) ); ?></label>
					<select id="kanban-field-priority" name="priority"></select>
				</div>
				<div class="kanban-field">
					<label for="kanban-field-status"><?php echo string_display_line( lang_get( 'status' ) ); ?></label>
					<select id="kanban-field-status" name="status"></select>
				</div>
				<div class="kanban-field">
					<label for="kanban-field-version"><?php echo string_display_line( lang_get( 'target_version' ) ); ?></label>
					<select id="kanban-field-version" name="target_version"></select>
				</div>
			</div>
			<div class="kanban-field">
				<label for="kanban-field-project"><?php echo string_display_line( lang_get( 'email_project' ) ); ?> *</label>
				<select id="kanban-field-project" name="project_id" required></select>
			</div>
			<div class="kanban-field">
				<label for="kanban-field-tags"><?php echo string_display_line( lang_get( 'tags' ) ); ?></label>
				<input type="text" id="kanban-field-tags" name="tag_string" autocomplete="off" />
				<span class="kanban-field-hint" id="kanban-tags-hint"></span>
			</div>
			<div class="kanban-dialog-meta" id="kanban-dialog-meta" hidden>
				<span id="kanban-meta-created"></span>
				<span id="kanban-meta-updated"></span>
			</div>
		</div>
		<footer class="kanban-dialog-footer">
			<div class="kanban-dialog-footer-left">
				<button type="button" class="kanban-btn kanban-btn-muted" id="kanban-btn-archive" hidden>
					<?php echo string_display_line( lang_get( 'closed_bug_button' ) ); ?>
				</button>
				<button type="button" class="kanban-btn kanban-btn-danger-text" id="kanban-btn-delete" hidden>
					<?php echo string_display_line( lang_get( 'delete' ) ); ?>
				</button>
			</div>
			<div class="kanban-dialog-footer-right">
				<button type="button" class="kanban-btn kanban-btn-muted" id="kanban-btn-save-new" hidden>
					<?php echo string_display_line( lang_get( 'report_more_bugs' ) ); ?>
				</button>
				<button type="submit" class="kanban-btn kanban-btn-primary" id="kanban-btn-save">
					<?php echo string_display_line( lang_get( 'update_information_button' ) ); ?>
				</button>
			</div>
		</footer>
	</form>
</dialog>

<?php
layout_page_end();
