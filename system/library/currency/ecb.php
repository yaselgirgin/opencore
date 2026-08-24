<?php
namespace Opencart\System\Library\Currency;
/**
 * Class ECB
 *
 * @package Opencart\System\Library\Currency
 */
class Ecb {
	/**
	 * Fetch normalized currency rates from the European Central Bank.
	 *
	 * @param string $base
	 *
	 * @return array<string, float>
	 */
	public function getRates(string $base): array {
		$rates = $this->fetchRates();
		$base = strtoupper($base);

		if (!isset($rates[$base]) || !is_numeric($rates[$base]) || (float)$rates[$base] <= 0) {
			throw new \InvalidArgumentException('The base currency rate is unavailable or invalid');
		}

		$values = [];

		foreach ($rates as $code => $rate) {
			$values[$code] = (float)$rate / (float)$rates[$base];
		}

		$values[$base] = 1.0;

		return $values;
	}

	/**
	 * Fetch current ECB currency rates.
	 *
	 * @return array<string, float>
	 */
	private function fetchRates(): array {
		$curl = curl_init();

		if ($curl === false) {
			throw new \RuntimeException('ECB currency request could not be initialized');
		}

		curl_setopt_array($curl, [
			CURLOPT_URL            => 'https://www.ecb.europa.eu/stats/eurofxref/eurofxref-daily.xml',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HEADER         => false,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2
		]);

		$response = curl_exec($curl);
		$status = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
		$error = curl_error($curl);

		curl_close($curl);

		if ($response === false || $status !== 200) {
			throw new \RuntimeException('ECB request failed' . ($error ? ': ' . $error : ' with HTTP status ' . $status));
		}

		return $this->parseRates($response);
	}

	/**
	 * Parse an ECB exchange-rate response.
	 *
	 * @param string $response
	 *
	 * @return array<string, float>
	 */
	private function parseRates(string $response): array {
		if ($response === '') {
			throw new \RuntimeException('ECB returned an empty response');
		}

		$previous = libxml_use_internal_errors(true);
		$xml = simplexml_load_string($response, \SimpleXMLElement::class, LIBXML_NONET);
		libxml_clear_errors();
		libxml_use_internal_errors($previous);

		if ($xml === false) {
			throw new \RuntimeException('ECB returned malformed XML');
		}

		$rates = ['EUR' => 1.0];

		foreach ($xml->xpath('//*[local-name()="Cube"][@currency][@rate]') ?: [] as $currency) {
			$code = strtoupper((string)$currency['currency']);
			$rate = (float)$currency['rate'];

			if (preg_match('/^[A-Z]{3}$/', $code) && is_finite($rate) && $rate > 0) {
				$rates[$code] = $rate;
			}
		}

		if (count($rates) === 1) {
			throw new \RuntimeException('ECB returned no valid currency rates');
		}

		return $rates;
	}
}
