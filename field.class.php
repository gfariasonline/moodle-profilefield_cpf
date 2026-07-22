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
 * CPF profile field class definition.
 *
 * @package   profilefield_cpf
 * @copyright 2014 onwards Willian Mano {@link http://willianmano.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * CPF profile field — renders and validates the CPF input in the user profile form.
 *
 * @package   profilefield_cpf
 * @copyright 2014 onwards Willian Mano {@link http://willianmano.net}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class profile_field_cpf extends profile_field_base {

    /**
     * Adds the CPF text element to the edit form with an AMD-loaded input mask.
     *
     * @param MoodleQuickForm $mform The form object.
     */
    public function edit_field_add($mform) {
        global $PAGE;

        $mform->addElement(
            'text',
            $this->inputname,
            format_string($this->field->name),
            'maxlength="14" size="14"'
        );
        $mform->setType($this->inputname, PARAM_TEXT);

        $PAGE->requires->js_call_amd('profilefield_cpf/cpf_mask', 'init', [$this->inputname]);
    }

    /**
     * Pre-formats the stored digits as XXX.XXX.XXX-XX when loading into the edit form.
     *
     * @param stdClass $data The user data object.
     */
    public function edit_field_set_data($data) {
        parent::edit_field_set_data($data);
        $digits = $this->normalize_cpf($this->data);
        if (strlen($digits) === 11) {
            $this->data = substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.'
                        . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
        }
    }

    /**
     * Validates the CPF field: checks format first, then uniqueness.
     *
     * @param stdClass $usernew The submitted user data.
     * @return array Associative array of field errors, empty on success.
     */
    public function edit_validate_field($usernew) {
        $errors = [];

        if (!isset($usernew->{$this->inputname})) {
            return $errors;
        }

        $cpf = $this->normalize_cpf($usernew->{$this->inputname});

        if ($cpf === '') {
            return $errors;
        }

        if (!$this->validate_cpf($cpf)) {
            $errors[$this->inputname] = get_string('invalidcpf', 'profilefield_cpf');
            return $errors;
        }

        $userid = $usernew->id ?? 0;
        if (!$this->cpf_is_unique($cpf, $userid)) {
            $errors[$this->inputname] = get_string('cpfexists', 'profilefield_cpf');
        }

        return $errors;
    }

    /**
     * Strips formatting before saving — stores only the 11 digits.
     *
     * @param string   $data       The submitted CPF value.
     * @param stdClass $datarecord The record being saved (unused).
     * @return string|null Digits-only CPF or null if input was null.
     */
    public function edit_save_data_preprocess($data, $datarecord) {
        if ($data === null) {
            return null;
        }
        return $this->normalize_cpf($data);
    }

    /**
     * Formats stored digits as XXX.XXX.XXX-XX for display on the profile page.
     *
     * @return string Formatted CPF or raw value if not 11 digits.
     */
    public function display_data() {
        $digits = $this->normalize_cpf($this->data);
        if (strlen($digits) === 11) {
            return substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.'
                 . substr($digits, 6, 3) . '-' . substr($digits, 9, 2);
        }
        return format_string($this->data);
    }

    /**
     * Returns true when the given CPF is not already used by another user.
     *
     * @param string $cpf    Digits-only CPF (11 chars).
     * @param int    $userid The current user's id (excluded from the check).
     * @return bool True if unique, false if a duplicate exists.
     */
    private function cpf_is_unique($cpf, $userid) {
        global $DB;

        if ($cpf === '') {
            return true;
        }

        $sql = "SELECT uid.data
                  FROM {user_info_data} uid
                  JOIN {user_info_field} uif ON uid.fieldid = uif.id
                 WHERE uif.datatype = 'cpf'
                   AND uid.userid <> :userid";

        foreach ($DB->get_fieldset_sql($sql, ['userid' => $userid]) as $existing) {
            if ($this->normalize_cpf($existing) === $cpf) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validates a CPF number by checking its two verification digits.
     *
     * @param string $cpf Digits-only CPF (11 chars).
     * @return bool True if mathematically valid.
     */
    private function validate_cpf($cpf) {
        $cpf = str_pad($cpf, 11, '0', STR_PAD_LEFT);

        if (strlen($cpf) !== 11) {
            return false;
        }

        // Reject sequences of identical digits (e.g. 000.000.000-00).
        if (preg_match('/^(\d)\1{10}$/', $cpf)) {
            return false;
        }

        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($c = 0; $c < $t; $c++) {
                $sum += (int)$cpf[$c] * (($t + 1) - $c);
            }
            $digit = ((10 * $sum) % 11) % 10;
            if ((int)$cpf[$c] !== $digit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Strips all non-digit characters from a CPF string.
     *
     * @param string|null $cpf Raw CPF value.
     * @return string Digits-only string, or empty string for null input.
     */
    private function normalize_cpf($cpf) {
        if ($cpf === null) {
            return '';
        }
        return preg_replace('/[^0-9]/', '', (string)$cpf);
    }
}
