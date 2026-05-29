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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Load Certifier credential template (group) and custom attribute options.
 *
 * Populates the credential template (group) select and renders per-attribute
 * mapping selects after the form has rendered, so the activity edit page
 * never blocks on a synchronous Certifier API call.
 *
 * @module     mod_certifier/form_options
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {get_string as getString, get_strings as getStrings} from 'core/str';

const SOURCE_KEYS = [
    'donotmap',
    'source_user_fullname',
    'source_user_firstname',
    'source_user_lastname',
    'source_user_email',
    'source_user_username',
    'source_course_fullname',
    'source_course_shortname',
    'source_course_idnumber',
];

const SOURCE_VALUES = [
    '',
    'user_fullname',
    'user_firstname',
    'user_lastname',
    'user_email',
    'user_username',
    'course_fullname',
    'course_shortname',
    'course_idnumber',
];

/**
 * Initialise the form options loader.
 *
 * @param {number} courseid Current course id.
 * @param {string} sesskey  Current Moodle session key.
 */
export const init = async(courseid, sesskey) => {
    const hiddenGroupId = document.querySelector('input[type="hidden"][name="groupid"]');
    const groupLoader = document.querySelector('[data-region="certifier-group-loader"]');
    const attributeLoader = document.querySelector('[data-region="certifier-customattributes-loader"]');
    const hiddenMappings = document.querySelector('input[type="hidden"][name="customattributemappings"]');
    if (!hiddenGroupId || !groupLoader || !attributeLoader || !hiddenMappings) {
        return;
    }

    const loadingLabel = await getString('loading', 'core');
    setLoader(groupLoader, loadingLabel);
    setLoader(attributeLoader, loadingLabel);

    const [groupsResponse, attributesResponse] = await Promise.all([
        fetchOptions(courseid, sesskey, 'groups'),
        fetchOptions(courseid, sesskey, 'attributes'),
    ]);

    if (!groupsResponse.configured) {
        setLoader(groupLoader, groupsResponse.message || '');
        setLoader(attributeLoader, groupsResponse.message || '');
        return;
    }

    await populateGroups(groupLoader, groupsResponse, hiddenGroupId);
    await populateAttributes(attributeLoader, attributesResponse, hiddenMappings);
};

/**
 * Render a small notice inside a loader region.
 *
 * @param {Element} region  Loader region element.
 * @param {string} message  Text to render.
 */
const setLoader = (region, message) => {
    region.textContent = message;
};

/**
 * Fetch one set of options from the Certifier AJAX endpoint.
 *
 * @param {number} courseid Course id.
 * @param {string} sesskey Session key.
 * @param {string} action  Endpoint action.
 * @returns {Promise<object>} Parsed JSON response.
 */
const fetchOptions = async(courseid, sesskey, action) => {
    const url = new URL(M.cfg.wwwroot + '/mod/certifier/ajax.php');
    url.searchParams.set('courseid', String(courseid));
    url.searchParams.set('action', action);
    url.searchParams.set('sesskey', sesskey);

    try {
        const response = await fetch(url.toString(), {
            credentials: 'same-origin',
            headers: {'Accept': 'application/json'},
        });
        if (!response.ok) {
            return {configured: true, error: 'HTTP ' + response.status, data: []};
        }
        return await response.json();
    } catch (err) {
        return {configured: true, error: err.message || String(err), data: []};
    }
};

/**
 * Render a credential template (group) select inside the loader region and
 * wire it to the hidden groupid input that mform actually persists.
 *
 * @param {Element} loader  Loader region that receives the visible select.
 * @param {object} response AJAX response.
 * @param {HTMLInputElement} hiddenGroupId Hidden input the value is written to.
 */
const populateGroups = async(loader, response, hiddenGroupId) => {
    if (response.error) {
        setLoader(loader, response.error);
        return;
    }
    const groups = Array.isArray(response.data) ? response.data : [];
    const placeholderText = await getString('selectgroup', 'certifier');

    loader.textContent = '';
    const select = document.createElement('select');
    select.className = 'form-control';
    select.appendChild(new Option(placeholderText, ''));
    const currentValue = hiddenGroupId.value;
    groups.forEach((group) => {
        const option = new Option(group.name, group.id);
        if (group.id === currentValue) {
            option.selected = true;
        }
        select.appendChild(option);
    });
    select.addEventListener('change', () => {
        hiddenGroupId.value = select.value;
    });
    loader.appendChild(select);
};

/**
 * Render per-attribute mapping selects and wire them to the hidden JSON field.
 *
 * @param {Element} container  Region that receives the rendered selects.
 * @param {object} response    AJAX response.
 * @param {HTMLInputElement} hiddenInput Hidden JSON field updated on change.
 */
const populateAttributes = async(container, response, hiddenInput) => {
    if (response.error) {
        setLoader(container, response.error);
        return;
    }
    const attributes = Array.isArray(response.data) ? response.data : [];
    if (attributes.length === 0) {
        setLoader(container, await getString('mappingnotice', 'certifier'));
        return;
    }

    let stored = {};
    try {
        const parsed = JSON.parse(hiddenInput.value || '{}');
        if (parsed && typeof parsed === 'object') {
            stored = parsed;
        }
    } catch (_) {
        stored = {};
    }

    const labels = await getStrings(SOURCE_KEYS.map((key) => ({key, component: 'certifier'})));
    container.textContent = '';
    const wrapper = document.createElement('div');
    wrapper.className = 'certifier-attribute-mappings';

    attributes.forEach((attribute) => {
        const row = document.createElement('div');
        row.className = 'form-group row fitem';

        const label = document.createElement('label');
        label.className = 'col-md-3 col-form-label';
        label.textContent = attribute.name;
        row.appendChild(label);

        const inputCol = document.createElement('div');
        inputCol.className = 'col-md-9';
        const select = document.createElement('select');
        select.className = 'form-control';
        select.dataset.tag = attribute.tag;
        SOURCE_VALUES.forEach((value, index) => {
            const opt = new Option(labels[index], value);
            if (stored[attribute.tag] === value) {
                opt.selected = true;
            }
            select.appendChild(opt);
        });
        select.addEventListener('change', () => updateHidden(wrapper, hiddenInput));
        inputCol.appendChild(select);
        row.appendChild(inputCol);
        wrapper.appendChild(row);
    });

    container.appendChild(wrapper);
    updateHidden(wrapper, hiddenInput);
};

/**
 * Read all per-attribute selects and write the result as JSON.
 *
 * @param {Element} wrapper Container of per-attribute selects.
 * @param {HTMLInputElement} hiddenInput Hidden JSON field.
 */
const updateHidden = (wrapper, hiddenInput) => {
    const mappings = {};
    wrapper.querySelectorAll('select[data-tag]').forEach((select) => {
        if (select.value !== '') {
            mappings[select.dataset.tag] = select.value;
        }
    });
    hiddenInput.value = JSON.stringify(mappings);
};
