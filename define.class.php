<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * CPF profile field definition class.
 *
 * @package   profilefield_cpf
 * @copyright 2014 onwards Willian Mano {@link http://willianmano.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * CPF profile field — administration form definition (shown in the field-settings page).
 *
 * @package   profilefield_cpf
 * @copyright 2014 onwards Willian Mano {@link http://willianmano.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_define_cpf extends profile_define_base {

    /**
     * Adds the default-value input to the field-settings form.
     *
     * @param MoodleQuickForm $form The form object.
     */
    public function define_form_specific($form) {
        $form->addElement('text', 'defaultdata', get_string('profiledefaultdata', 'admin'));
        $form->setType('defaultdata', PARAM_TEXT);
    }
}
