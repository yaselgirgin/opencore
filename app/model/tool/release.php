<?php
namespace Opencart\App\Model\Tool;

/**
 * Checks GitHub releases and records the newest release already announced.
 */
class Release extends \Opencart\System\Engine\Model {
	private const REPOSITORY = 'yaselgirgin/opencore';

	/**
	 * Check for a newer stable release and create one global notification when needed.
	 *
	 * @param string $title
	 * @param string $text
	 *
	 * @return array{status: string, version?: string}
	 */
	public function check(string $title, string $text): array {
		$release = $this->fetchLatestStableRelease();

		if (!$release || version_compare($release['version'], VERSION, '<=')) {
			return ['status' => 'current'] + ($release ? ['version' => $release['version']] : []);
		}

		$this->db->query('START TRANSACTION');

		try {
			$notified_version = $this->getNotifiedVersionForUpdate();

			if ($notified_version !== '' && version_compare($release['version'], $notified_version, '<=')) {
				$this->db->query('ROLLBACK');

				return ['status' => 'already_notified', 'version' => $release['version']];
			}

			$this->load->model('tool/notification');

			$this->model_tool_notification->addNotification([
				'code'      => 'release',
				'reference' => $release['version'],
				'title'     => $title,
				'text'      => sprintf($text, $release['version'], VERSION),
				'url'       => $release['url'],
				'is_global' => true
			]);

			$this->db->query("UPDATE `" . DB_PREFIX . "release_notification` SET `release_version` = '" . $this->db->escape($release['version']) . "', `date_modified` = NOW() WHERE `release_notification_id` = '1'");
			$this->db->query('COMMIT');
		} catch (\Throwable $e) {
			try {
				$this->db->query('ROLLBACK');
			} catch (\Throwable) {
			}

			throw $e;
		}

		return ['status' => 'notified', 'version' => $release['version']];
	}

	/**
	 * @return array{version: string, url: string|null}|null
	 */
	private function fetchLatestStableRelease(): ?array {
		$curl = curl_init();

		if ($curl === false) {
			throw new \RuntimeException('Release request could not be initialized.');
		}

		curl_setopt_array($curl, [
			CURLOPT_URL            => 'https://api.github.com/repos/' . self::REPOSITORY . '/releases?per_page=100',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => false,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_HTTPHEADER     => ['Accept: application/vnd.github+json', 'User-Agent: OpenCore/' . VERSION]
		]);

		$response = curl_exec($curl);
		$status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$error = curl_error($curl);

		curl_close($curl);

		if ($response === false || $status !== 200) {
			throw new \RuntimeException('Release request failed' . ($error ? ': ' . $error : ' with HTTP status ' . $status) . '.');
		}

		$releases = json_decode($response, true);

		if (!is_array($releases)) {
			throw new \RuntimeException('Release response was invalid.');
		}

		$latest = null;

		foreach ($releases as $release) {
			if (!is_array($release) || !empty($release['draft']) || !empty($release['prerelease'])) {
				continue;
			}

			$version = ltrim(trim((string)($release['tag_name'] ?? '')), 'vV');

			if ($version === '' || strlen($version) > 255 || !preg_match('/^\d+(?:\.\d+){1,3}(?:[.-][0-9A-Za-z.-]+)?$/', $version)) {
				continue;
			}

			$url = (string)($release['html_url'] ?? '');

			$candidate = [
				'version' => $version,
				'url'     => filter_var($url, FILTER_VALIDATE_URL) ? $url : null
			];

			if ($latest === null || version_compare($candidate['version'], $latest['version'], '>')) {
				$latest = $candidate;
			}
		}

		return $latest;
	}

	private function getNotifiedVersionForUpdate(): string {
		$this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "release_notification` SET `release_notification_id` = '1', `release_version` = '', `date_modified` = NOW()");

		$query = $this->db->query("SELECT `release_version` FROM `" . DB_PREFIX . "release_notification` WHERE `release_notification_id` = '1' FOR UPDATE");

		if (!$query->num_rows) {
			throw new \RuntimeException('Release notification state could not be locked.');
		}

		return (string)$query->row['release_version'];
	}
}
