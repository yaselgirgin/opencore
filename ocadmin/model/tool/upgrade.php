<?php
namespace Opencart\Admin\Model\Tool;
/**
 * Class Upgrade
 *
 * @package Opencart\Admin\Model\Tool
 */
class Upgrade extends \Opencart\System\Engine\Model {
	private const RELEASES_URL = 'https://api.github.com/repos/yaselgirgin/opencore/releases?per_page=100';
	private const RELEASE_URL_PREFIX = 'https://github.com/yaselgirgin/opencore/releases/tag/';

	public function discover(string $current_version): array {
		$curl = curl_init(self::RELEASES_URL);

		curl_setopt_array($curl, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT        => 20,
			CURLOPT_USERAGENT      => 'OpenCore/' . VERSION,
			CURLOPT_HTTPHEADER     => [
				'Accept: application/vnd.github+json',
				'X-GitHub-Api-Version: 2022-11-28'
			]
		]);

		$response = curl_exec($curl);
		$status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$error = curl_error($curl);

		curl_close($curl);

		if ($response === false || $status < 200 || $status >= 300) {
			$this->log->write('OpenCore release discovery failed with HTTP status ' . $status . ($error ? ': ' . $error : '.'));

			return ['success' => false];
		}

		$releases = json_decode($response, true);

		if (!is_array($releases) || !array_is_list($releases)) {
			$this->log->write('OpenCore release discovery returned an invalid JSON response.');

			return ['success' => false];
		}

		$latest = null;

		foreach ($releases as $release) {
			if (!is_array($release) || !empty($release['draft']) || !empty($release['prerelease'])) {
				continue;
			}

			$version = $this->normalizeVersion((string)($release['tag_name'] ?? ''));

			if (!$version || ($latest && $this->compareVersions($version, $latest['version']) <= 0)) {
				continue;
			}

			$url = (string)($release['html_url'] ?? '');

			if (!str_starts_with($url, self::RELEASE_URL_PREFIX) || filter_var($url, FILTER_VALIDATE_URL) === false) {
				$url = '';
			}

			$latest = [
				'version'      => $version,
				'tag'          => (string)$release['tag_name'],
				'name'         => (string)($release['name'] ?? ''),
				'published_at' => (string)($release['published_at'] ?? ''),
				'url'          => $url
			];
		}

		if (!$latest) {
			return [
				'success'         => true,
				'status'          => 'NO_RELEASE_AVAILABLE',
				'current_version' => $current_version,
				'latest_version'  => null
			];
		}

		return [
			'success'         => true,
			'status'          => $this->compareVersions($latest['version'], $current_version) > 0 ? 'UPDATE_AVAILABLE' : 'UP_TO_DATE',
			'current_version' => $current_version,
			'latest_version'  => $latest['version'],
			'release'         => $latest
		];
	}

	public function normalizeVersion(string $tag): ?string {
		if (!preg_match('/^v?(\d{4})\.(0[1-9]|1[0-2])\.([1-9]\d*)$/', $tag, $matches)) {
			return null;
		}

		return $matches[1] . '.' . $matches[2] . '.' . $matches[3];
	}

	public function compareVersions(string $version1, string $version2): int {
		$first = array_map('intval', explode('.', $version1));
		$second = array_map('intval', explode('.', $version2));

		return $first <=> $second;
	}
}
