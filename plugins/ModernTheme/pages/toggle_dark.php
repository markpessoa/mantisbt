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
 * Toggle dark mode and return to the previous page.
 */

auth_ensure_user_authenticated();

/** @var ModernThemePlugin $t_plugin */
$t_plugin = plugin_get();
$t_enabled = !$t_plugin->is_dark_mode();
$t_plugin->set_dark_mode( $t_enabled );

$t_return = gpc_get_string( 'return', 'index.php' );
print_header_redirect( $t_return, true, false );
