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
 * Modern Theme plugin
 *
 * Injects a Tailwind-inspired slate + indigo stylesheet over the Ace layout
 * and provides a per-user dark mode toggle.
 */
class ModernThemePlugin extends MantisPlugin {
	const COOKIE_SUFFIX = '_modern_theme_dark';

	/**
	 * Plugin registration.
	 *
	 * @return void
	 */
	function register() {
		$this->name = plugin_lang_get( 'title' );
		$this->description = plugin_lang_get( 'description' );
		$this->page = '';

		$this->version = '1.1.0';
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
			'EVENT_LAYOUT_RESOURCES' => 'resources',
			'EVENT_LAYOUT_PAGE_HEADER' => 'navbar_toggle',
		);
	}

	/**
	 * Cookie name used to persist dark mode.
	 *
	 * @return string
	 */
	function cookie_name() {
		return config_get_global( 'cookie_prefix' ) . self::COOKIE_SUFFIX;
	}

	/**
	 * Whether dark mode is currently enabled.
	 *
	 * @return bool
	 */
	function is_dark_mode() {
		$t_cookie = gpc_get_cookie( $this->cookie_name(), null );
		if( $t_cookie !== null && $t_cookie !== '' ) {
			return $t_cookie === '1';
		}

		if( auth_is_user_authenticated() ) {
			return (int)plugin_config_get( 'dark_mode', OFF, false, auth_get_current_user_id() ) === ON;
		}

		return false;
	}

	/**
	 * Persist dark mode in a cookie and, when logged in, in plugin config.
	 *
	 * @param bool $p_enabled Dark mode on or off.
	 * @return void
	 */
	function set_dark_mode( $p_enabled ) {
		gpc_set_cookie( $this->cookie_name(), $p_enabled ? '1' : '0', true );

		if( auth_is_user_authenticated() ) {
			plugin_config_set(
				'dark_mode',
				$p_enabled ? ON : OFF,
				auth_get_current_user_id(),
				ALL_PROJECTS,
				ANYBODY
			);
		}
	}

	/**
	 * Load the theme stylesheet and apply the dark-mode class before paint.
	 *
	 * @return void
	 */
	function resources() {
		$t_scheme = $this->is_dark_mode() ? 'dark' : 'light';
		echo '<meta name="modern-theme-color-scheme" content="' . $t_scheme . '" />' . "\n";
		echo '<link rel="stylesheet" href="' . plugin_file( 'modern.css' ) . '" />' . "\n";
		echo '<script src="' . plugin_file( 'modern.js' ) . '"></script>' . "\n";
	}

	/**
	 * Print the dark-mode toggle. CSP blocks inline scripts, so this is HTML
	 * only; modern.js moves it into the Ace navbar when available.
	 *
	 * @return void
	 */
	function navbar_toggle() {
		if( !auth_is_user_authenticated() ) {
			return;
		}

		$t_dark = $this->is_dark_mode();
		$t_return = string_sanitize_url( $_SERVER['REQUEST_URI'] ?? 'index.php' );
		$t_url = htmlspecialchars(
			plugin_page( 'toggle_dark' ) . '&return=' . urlencode( $t_return ),
			ENT_QUOTES,
			'UTF-8'
		);
		$t_label = htmlspecialchars(
			plugin_lang_get( $t_dark ? 'disable_dark_mode' : 'enable_dark_mode' ),
			ENT_QUOTES,
			'UTF-8'
		);
		$t_icon = $t_dark ? 'fa-sun-o' : 'fa-moon-o';

		echo '<a id="modern-theme-toggle" class="modern-theme-toggle-btn" href="', $t_url, '" title="', $t_label, '">';
		echo '<i class="ace-icon fa ', $t_icon, '"></i>';
		echo '<span class="modern-theme-toggle-label">', $t_label, '</span>';
		echo '</a>', "\n";
	}
}
