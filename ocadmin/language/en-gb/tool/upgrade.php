<?php
// Heading
$_['heading_title']        = 'OpenCore Upgrade';

// Text
$_['text_upgrade']         = 'Release Discovery';
$_['text_current_version'] = 'Current Version';
$_['text_latest_version']  = 'Latest Version';
$_['text_status']          = 'Status';
$_['text_not_checked']     = 'Not checked';
$_['text_up_to_date']      = 'Up to Date';
$_['text_update_available'] = 'Update Available';
$_['text_no_release']      = 'No OpenCore release is currently available.';
$_['text_staged']           = 'The update artifact was downloaded, validated, and staged. No live files were changed.';
$_['text_already_staged']   = 'This update is already staged and validated.';
$_['text_applied']          = 'The OpenCore application update was applied successfully.';
$_['text_rolled_back']      = 'The update failed and all application file changes were rolled back.';
$_['text_recovered']        = 'The interrupted application update was rolled back successfully.';

// Button
$_['button_check']         = 'Check for Updates';
$_['button_prepare']       = 'Download & Validate';
$_['button_apply']          = 'Apply Update';
$_['button_recover']        = 'Recover Update';

// Error
$_['error_permission']     = 'Warning: You do not have permission to access upgrades!';
$_['error_check']          = 'Unable to check for updates.';
$_['error_permission_modify'] = 'Warning: You do not have permission to prepare upgrades!';
$_['error_prepare']         = 'Unable to prepare this update.';
$_['error_download']        = 'Unable to download the approved update artifact.';
$_['error_validation']      = 'The update artifact failed validation.';
$_['error_method']          = 'This update action requires a POST request.';
$_['error_apply']           = 'Unable to apply the staged update.';
$_['error_preflight_failed'] = 'The staged update failed the final preflight validation.';
$_['error_recovery_required'] = 'A prior update requires recovery before another update can be applied.';
$_['error_rollback_failed'] = 'Automatic rollback could not be completed. Recovery is required.';
$_['error_database_update_not_supported'] = 'This release requires a database update that is not supported in this phase.';
$_['error_vendor_apply_not_supported'] = 'This release requires a vendor update that is not supported in this phase.';
$_['error_recovery_not_required'] = 'This update does not require recovery.';
$_['error_recovery_failed'] = 'The interrupted update could not be recovered.';
$_['error_apply_failed_rolled_back'] = 'The update failed, but all application file changes were rolled back.';
