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
 * CPF input masking using Cleave.js.
 *
 * @module     profilefield_cpf/cpf_mask
 * @copyright  2026 GFarias <dev@gfarias.com.br>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import Cleave from './cleave-esm';

/**
 * Applies Brazilian CPF formatting to the Moodle-generated profile input.
 *
 * @param {string} fieldName The profile field input name.
 * @returns {Cleave|null} The formatter instance, or null when the input is absent.
 */
export const init = fieldName => {
    const input = document.getElementById('id_' + fieldName);

    if (!input) {
        return null;
    }

    return new Cleave(input, {
        blocks: [3, 3, 3, 2],
        delimiters: ['.', '.', '-'],
        numericOnly: true,
    });
};
