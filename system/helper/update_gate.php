<?php
/**
 * Return the unresolved database update state, if one exists.
 *
 * @return array<string, mixed>
 */
function oc_update_gate_state(string $storage): array {
	$updates = rtrim($storage, '/\\') . '/updates/';
	$blocked = [
		'DATABASE_PENDING',
		'DATABASE_APPLYING',
		'DATABASE_RECOVERY_REQUIRED',
		'DATABASE_RESTORE_REQUIRED',
		'DATABASE_RESTORING',
		'DATABASE_RESTORE_FAILED',
		'DATABASE_RESTORED',
		'ROLLBACK_REQUIRED',
		'ROLLBACK_FAILED'
	];

	$states = glob($updates . '*/state/state.json') ?: [];
	sort($states, SORT_STRING);

	foreach ($states as $file) {
		$state = json_decode((string)file_get_contents($file), true);

		if (!is_array($state) || !isset($state['status'])) {
			return ['status' => 'DATABASE_RECOVERY_REQUIRED'];
		}

		if (is_array($state) && in_array($state['status'] ?? '', $blocked, true)) {
			return $state;
		}
	}

	if (is_file($updates . 'apply.lock')) {
		return ['status' => 'DATABASE_RECOVERY_REQUIRED'];
	}

	return [];
}

function oc_update_gate_active(string $storage): bool {
	return (bool)oc_update_gate_state($storage);
}

function oc_update_database_compatible(\Opencart\System\Engine\Config $config): bool {
	return $config->has('database_version') && $config->get('database_version') === VERSION;
}

function oc_update_gate_admin_route_allowed(string $route): bool {
	$route = str_replace(['|', '%7C'], '.', $route);

	return in_array($route, [
		'common/login',
		'common/login.login',
		'common/logout',
		'common/forgotten',
		'common/forgotten.confirm',
		'common/forgotten.reset',
		'common/forgotten.password',
		'common/authorize',
		'common/authorize.send',
		'common/authorize.save',
		'common/authorize.reset',
		'common/authorize.confirm',
		'common/authorize.unlock',
		'common/language',
		'common/language.save',
		'tool/upgrade',
		'tool/upgrade.database',
		'tool/upgrade.recover'
	], true);
}
