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
 * Modern theme Dark Orange UI
 *
 * Dark theme with orange accents. CSS based on
 * https://github.com/Cetheus/MantisBTModernDarkTheme (polnetwork / wiz78 / stigzler).
 */
class ModernThemeDarkOrangeUIPlugin extends MantisPlugin {
	/**
	 * Plugin registration.
	 *
	 * @return void
	 */
	function register() {
		$this->name = plugin_lang_get( 'title' );
		$this->description = plugin_lang_get( 'description' );
		$this->page = '';

		$this->version = '1.0.0';
		$this->requires = array(
			'MantisCore' => '2.25.0',
		);

		$this->author = 'Build Together';
		$this->contact = '';
		$this->url = 'https://github.com/Cetheus/MantisBTModernDarkTheme';
	}

	/**
	 * Event hooks.
	 *
	 * @return array
	 */
	function hooks() {
		return array(
			'EVENT_LAYOUT_RESOURCES' => 'resources',
		);
	}

	/**
	 * Load the dark orange stylesheet on every page.
	 *
	 * @return void
	 */
	function resources() {
		echo '<link rel="stylesheet" href="' . plugin_file( 'dark-orange.css' ) . '" />' . "\n";
	}
}
