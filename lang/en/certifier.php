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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English language strings for the Certifier activity module.
 *
 * @package    mod_certifier
 * @copyright  2026 Certifier
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['apiclientnotconfigured'] = 'Certifier API URL or API key is not configured.';
$string['apiclientnotconfiguredform'] = 'Configure API URL and key in plugin settings to load templates and attributes.';
$string['apifetchfailed'] = 'Could not load credential templates or custom attributes from Certifier. Check the plugin API configuration and try again.';
$string['apiinvalidresponse'] = 'Certifier API returned an invalid response.';
$string['apikey'] = 'API key';
$string['apikey_desc'] = 'Certifier API key used by all Certifier activity instances.';
$string['apimissingcredentialid'] = 'Certifier API create response did not include a credential id.';
$string['apirequestfailed'] = 'Certifier API request failed: {$a}';
$string['apiunexpectedshape'] = 'Certifier API response did not match the expected shape: {$a}';
$string['apiunexpectedstatus'] = 'Certifier API returned unexpected HTTP status {$a}.';
$string['apiurl'] = 'API URL';
$string['apiurl_desc'] = 'Base URL for the Certifier API. Must be an http(s) URL.';
$string['certifier:addinstance'] = 'Add a new Certifier activity';
$string['certifier:manage'] = 'Manage Certifier activity instances';
$string['certifier:view'] = 'View Certifier activity';
$string['certifiername'] = 'Name shown in Moodle course';
$string['certifiername_help'] = 'Activity name shown to learners and teachers in the course.';
$string['configurationsummary'] = 'Certifier configuration';
$string['credentialissuedat'] = 'Issued';
$string['credentialissuedbody'] = 'Your credential for {$a} has been issued.';
$string['credentialissuedsmall'] = 'Your credential for {$a} has been issued.';
$string['credentialissuedsubject'] = 'Credential issued';
$string['credentiallastupdated'] = 'Last updated';
$string['credentiallink'] = 'Credential link';
$string['credentiallinkavailableafterissue'] = 'This credential is still a draft. Once it is issued, the learner-facing link will appear here.';
$string['credentiallinkpending'] = 'A learner-facing credential link will appear here once the credential is ready.';
$string['credentialsentat'] = 'Sent';
$string['credentialsettings'] = 'Certifier credential';
$string['customattributesettings'] = 'Custom attribute mapping';
$string['defaultactivityname'] = 'Certifier credential for {$a}';
$string['deliverymode'] = 'Delivery mode';
$string['deliverymode_create'] = 'Create only';
$string['deliverymode_create_issue'] = 'Create + issue';
$string['deliverymode_create_issue_send'] = 'Create + issue + send';
$string['deliverymode_error'] = 'Select a valid delivery mode.';
$string['donotmap'] = 'Do not map';
$string['groupid'] = 'Credential template';
$string['groupid_help'] = 'Certifier template to issue credentials into. Loaded using the configured API key.';
$string['groupid_required'] = 'Select a Certifier credential template before saving.';
$string['issuancestatus'] = 'Credential status';
$string['issuerportaldomain'] = 'Custom issuer portal domain';
$string['issuerportaldomain_desc'] = 'Base URL for learner-facing credential links. Defaults to the Certifier issuer portal (`https://credsverse.com`). Must be an http(s) URL.';
$string['mappingnotice'] = 'Custom attribute mapping options will appear here once the Certifier API responds.';
$string['messageprovider:credentialissued'] = 'Credential issued';
$string['minimumgrade'] = 'Minimum grade percentage';
$string['minimumgrade_error'] = 'Minimum grade must be between 0 and 100.';
$string['minimumgrade_help'] = 'Minimum percentage grade required before Certifier issuance is queued.';
$string['modulename'] = 'Certifier - Certificates & Badges';
$string['modulename_help'] = 'Issue Certifier certificates and badges when learners meet configured course criteria.';
$string['modulenameplural'] = 'Certifier activities';
$string['nonewmodules'] = 'No Certifier activities found';
$string['pluginadministration'] = 'Certifier administration';
$string['pluginname'] = 'Certifier - Certificates & Badges';
$string['privacy:metadata'] = 'Stores issuance queue and status records for learners eligible for Certifier credentials.';
$string['privacy:metadata:certifier'] = 'The Certifier activity sends recipient and mapped credential data to Certifier.';
$string['privacy:metadata:certifier:customattributes'] = 'Mapped custom attributes sent to Certifier.';
$string['privacy:metadata:certifier:groupid'] = 'Certifier credential template used to create the credential.';
$string['privacy:metadata:certifier:recipientemail'] = 'Recipient email sent to Certifier.';
$string['privacy:metadata:certifier:recipientname'] = 'Recipient name sent to Certifier.';
$string['privacy:metadata:certifier_issuances'] = 'Issuance queue and audit records for Certifier credentials.';
$string['privacy:metadata:certifier_issuances:credentialid'] = 'Certifier credential ID returned by Certifier.';
$string['privacy:metadata:certifier_issuances:credentialurl'] = 'Public credential URL for the learner.';
$string['privacy:metadata:certifier_issuances:customattributes'] = 'Mapped custom attributes snapshot sent to Certifier.';
$string['privacy:metadata:certifier_issuances:errorcode'] = 'Last issuance error code.';
$string['privacy:metadata:certifier_issuances:errormessage'] = 'Last issuance error message.';
$string['privacy:metadata:certifier_issuances:groupid'] = 'Certifier credential template used for issuance.';
$string['privacy:metadata:certifier_issuances:recipientemail'] = 'Recipient email snapshot sent to Certifier.';
$string['privacy:metadata:certifier_issuances:recipientname'] = 'Recipient name snapshot sent to Certifier.';
$string['privacy:metadata:certifier_issuances:status'] = 'Current issuance status.';
$string['privacy:metadata:certifier_issuances:timecreated'] = 'Time the issuance record was created.';
$string['privacy:metadata:certifier_issuances:timeissued'] = 'Time the credential was issued.';
$string['privacy:metadata:certifier_issuances:timemodified'] = 'Time the issuance record was last updated.';
$string['privacy:metadata:certifier_issuances:timesent'] = 'Time the credential email was sent.';
$string['privacy:metadata:certifier_issuances:triggertype'] = 'Configured trigger type that created the issuance record.';
$string['privacy:metadata:certifier_issuances:userid'] = 'Moodle user associated with the issuance record.';
$string['requirepassinggrade'] = 'Grade requirement';
$string['requirepassinggrade_label'] = 'Require passing/minimum grade for the selected activity';
$string['requirepassinggrade_nograde'] = 'The selected activity has no grade item. Pick a graded activity or set a minimum grade.';
$string['restorefrozenissuance'] = 'This issuance record was restored from backup. Automatic processing was not resumed in the restored course to avoid duplicate Certifier API calls.';
$string['selectactivity'] = 'Select an activity';
$string['selectgroup'] = 'Select a Certifier credential template';
$string['source_course_fullname'] = 'Course full name';
$string['source_course_idnumber'] = 'Course ID number';
$string['source_course_shortname'] = 'Course short name';
$string['source_user_email'] = 'User email';
$string['source_user_firstname'] = 'User first name';
$string['source_user_fullname'] = 'User full name';
$string['source_user_lastname'] = 'User last name';
$string['source_user_username'] = 'Username';
$string['status_created'] = 'Created';
$string['status_failed_permanent'] = 'Failed';
$string['status_failed_retryable'] = 'Retrying';
$string['status_issued'] = 'Issued';
$string['status_processing'] = 'Processing';
$string['status_queued'] = 'Queued';
$string['status_send_skipped'] = 'Send skipped (no recipient email)';
$string['status_sent'] = 'Sent';
$string['status_unknown'] = 'Unknown';
$string['statusdesc_created'] = 'Your credential draft has been created in Certifier. Once it is issued, the learner-facing link will appear here.';
$string['statusdesc_failed_permanent'] = 'We could not complete credential delivery automatically. Please contact your teacher or site administrator.';
$string['statusdesc_failed_retryable'] = 'We hit a temporary problem while preparing your credential. Moodle will retry automatically.';
$string['statusdesc_issued'] = 'Your credential has been issued and is ready to view.';
$string['statusdesc_processing'] = 'We are preparing your credential now.';
$string['statusdesc_queued'] = 'Your credential has been queued and will appear here soon.';
$string['statusdesc_send_skipped'] = 'Your credential is ready, but email delivery was skipped for this record.';
$string['statusdesc_sent'] = 'Your credential has been issued and sent.';
$string['statusdesc_unknown'] = 'The credential is in an unknown state. Please refresh the page or contact support if this continues.';
$string['statusplaceholder'] = 'Your credential will appear here once it has been queued.';
$string['trigger_activity_completion'] = 'Selected activity completion';
$string['trigger_course_completion'] = 'Course completion';
$string['triggercmid'] = 'Activity to watch';
$string['triggercmid_nocompletion'] = 'The selected activity does not have completion tracking enabled.';
$string['triggercmid_notincourse'] = 'The selected activity does not belong to this course.';
$string['triggersettings'] = 'Issuance trigger';
$string['triggertype'] = 'Trigger';
$string['triggertype_error'] = 'Select a valid trigger type.';
$string['url_invalid'] = 'The URL must be a valid http(s) URL.';
$string['viewcredential'] = 'View credential';
