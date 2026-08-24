<?php
namespace Opencart\Admin\Model\Customer;
/**
 * Class Customer
 *
 * Can be loaded using $this->load->model('customer/customer');
 *
 * @package Opencart\Admin\Model\Customer
 */
class Customer extends \Opencart\System\Engine\Model {
	/**
	 * Add Customer
	 *
	 * Create a new customer record in the database.
	 *
	 * @param array<string, mixed> $data array of data
	 *
	 * @return int
	 *
	 * @example
	 *
	 * $customer_data = [
	 *     'store_id'          => 1,
	 *     'language_id'       => 1,
	 *     'customer_group_id' => 1,
	 *     'firstname'         => 'John',
	 *     'lastname'          => 'Doe',
	 *     'email'             => 'demo@opencart.com',
	 *     'telephone'         => '1234567890',
	 *     'custom_field'      => [],
	 *     'newsletter'        => 0,
	 *     'password'          => '',
	 *     'status'            => 0,
	 *     'safe'              => 0,
	 *     'commenter'         => 0
	 * ];
	 *
	 * $this->load->model('customer/customer');
	 *
	 * $customer_id = $this->model_customer_customer->addCustomer($customer_data);
	 */
	public function addCustomer(array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "customer` SET `store_id` = '" . (int)$data['store_id'] . "', `language_id` = '" . (int)$data['language_id'] . "', `customer_group_id` = '" . (int)$data['customer_group_id'] . "', `firstname` = '" . $this->db->escape((string)$data['firstname']) . "', `lastname` = '" . $this->db->escape((string)$data['lastname']) . "', `email` = '" . $this->db->escape(oc_strtolower($data['email'])) . "', `telephone` = '" . $this->db->escape((string)$data['telephone']) . "', `custom_field` = '" . $this->db->escape(isset($data['custom_field']) ? json_encode($data['custom_field']) : json_encode([])) . "', `newsletter` = '" . (isset($data['newsletter']) ? (bool)$data['newsletter'] : 0) . "', `password` = '" . $this->db->escape(password_hash(html_entity_decode($data['password'], ENT_QUOTES, 'UTF-8'), PASSWORD_DEFAULT)) . "', `status` = '" . (isset($data['status']) ? (bool)$data['status'] : 0) . "', `safe` = '" . (isset($data['safe']) ? (bool)$data['safe'] : 0) . "', `commenter` = '" . (isset($data['commenter']) ? (bool)$data['commenter'] : 0) . "', `date_added` = NOW()");

		return $this->db->getLastId();
	}

	/**
	 * Edit Customer
	 *
	 * Edit customer record in the database.
	 *
	 * @param int                  $customer_id primary key of the customer record
	 * @param array<string, mixed> $data        array of data
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $customer_data = [
	 *     'store_id'          => 1,
	 *     'language_id'       => 1,
	 *     'customer_group_id' => 1,
	 *     'firstname'         => 'John',
	 *     'lastname'          => 'Doe',
	 *     'email'             => 'demo@opencart.com',
	 *     'telephone'         => '1234567890',
	 *     'custom_field'      => [],
	 *     'newsletter'        => 0,
	 *     'password'          => '',
	 *     'status'            => 1,
	 *     'safe'              => 0,
	 *     'commenter'         => 0
	 * ];
	 *
	 * $this->load->model('customer/customer');
	 *
	 * $this->model_customer_customer->editCustomer($customer_id, $customer_data);
	 */
	public function editCustomer(int $customer_id, array $data): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "customer` SET `store_id` = '" . (int)$data['store_id'] . "', `language_id` = '" . (int)$data['language_id'] . "', `customer_group_id` = '" . (int)$data['customer_group_id'] . "', `firstname` = '" . $this->db->escape((string)$data['firstname']) . "', `lastname` = '" . $this->db->escape((string)$data['lastname']) . "', `email` = '" . $this->db->escape(oc_strtolower($data['email'])) . "', `telephone` = '" . $this->db->escape((string)$data['telephone']) . "', `custom_field` = '" . $this->db->escape(isset($data['custom_field']) ? json_encode($data['custom_field']) : json_encode([])) . "', `newsletter` = '" . (isset($data['newsletter']) ? (bool)$data['newsletter'] : 0) . "', `status` = '" . (isset($data['status']) ? (bool)$data['status'] : 0) . "', `safe` = '" . (isset($data['safe']) ? (bool)$data['safe'] : 0) . "', `commenter` = '" . (isset($data['commenter']) ? (bool)$data['commenter'] : 0) . "' WHERE `customer_id` = '" . (int)$customer_id . "'");

		if ($data['password']) {
			$this->db->query("UPDATE `" . DB_PREFIX . "customer` SET `password` = '" . $this->db->escape(password_hash(html_entity_decode($data['password'], ENT_QUOTES, 'UTF-8'), PASSWORD_DEFAULT)) . "' WHERE `customer_id` = '" . (int)$customer_id . "'");
		}
	}

	/**
	 * Edit Token
	 *
	 * Edit customer token record in the database.
	 *
	 * @param int    $customer_id primary key of the customer record
	 * @param string $token
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('customer/customer');
	 *
	 * $this->model_customer_customer->editToken($customer_id, $token);
	 */
	public function editToken(int $customer_id, string $token): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "customer` SET `token` = '" . $this->db->escape($token) . "' WHERE `customer_id` = '" . (int)$customer_id . "'");
	}

	/**
	 * Edit Commenter
	 *
	 * Edit customer commenter record in the database.
	 *
	 * @param int  $customer_id primary key of the customer record
	 * @param bool $status
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('customer/customer');
	 *
	 * $this->model_customer_customer->editCommenter($customer_id, $status);
	 */
	public function editCommenter(int $customer_id, bool $status): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "customer` SET `commenter` = '" . (bool)$status . "' WHERE `customer_id` = '" . (int)$customer_id . "'");
	}

	/**
	 * Get Customer
	 *
	 * Get the record of the customer record in the database.
	 *
	 * @param int $customer_id primary key of the customer record
	 *
	 * @return array<string, mixed> customer record that has customer ID
	 *
	 * @example
	 *
	 * $this->load->model('customer/customer');
	 *
	 * $customer_info = $this->model_customer_customer->getCustomer($customer_id);
	 */
	public function getCustomer(int $customer_id): array {
		$query = $this->db->query("SELECT DISTINCT * FROM `" . DB_PREFIX . "customer` WHERE `customer_id` = '" . (int)$customer_id . "'");

		if ($query->num_rows) {
			return ['custom_field' => $query->row['custom_field'] ? json_decode($query->row['custom_field'], true) : []] + $query->row;
		} else {
			return [];
		}
	}

	/**
	 * Get Customer By Email
	 *
	 * @param string $email
	 *
	 * @return array<string, mixed>
	 *
	 * @example
	 *
	 * $this->load->model('customer/customer');
	 *
	 * $customer_info = $this->model_customer_customer->getCustomerByEmail($email);
	 */
	public function getCustomerByEmail(string $email): array {
		$query = $this->db->query("SELECT DISTINCT * FROM `" . DB_PREFIX . "customer` WHERE LCASE(`email`) = '" . $this->db->escape(oc_strtolower($email)) . "'");

		if ($query->num_rows) {
			return ['custom_field' => $query->row['custom_field'] ? json_decode($query->row['custom_field'], true) : []] + $query->row;
		} else {
			return [];
		}
	}

	/**
	 * Get Total Customers By Customer Group ID
	 *
	 * Get the total number of customers by customer group records in the database.
	 *
	 * @param int $customer_group_id primary key of the customer group record
	 *
	 * @return int total number of customer records that have customer group ID
	 *
	 * @example
	 *
	 * $this->load->model('customer/customer');
	 *
	 * $customer_total = $this->model_customer_customer->getTotalCustomersByCustomerGroupId($customer_group_id);
	 */
	public function getTotalCustomersByCustomerGroupId(int $customer_group_id): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "customer` WHERE `customer_group_id` = '" . (int)$customer_group_id . "'");

		if ($query->num_rows) {
			return (int)$query->row['total'];
		} else {
			return 0;
		}
	}

	/**
	 * Add Address
	 *
	 * Create a new address record in the database.
	 *
	 * @param int                  $customer_id primary key of the customer record
	 * @param array<string, mixed> $data        array of data
	 *
	 * @return int returns the primary key of the new address record
	 *
	 * @example
	 *
	 * $address_data = [
	 *     'firstname'    => 'John',
	 *     'lastname'     => 'Doe',
	 *     'company'      => '',
	 *     'address_1'    => 'Address 1',
	 *     'address_2'    => 'Address 2',
	 *     'city'         => '',
	 *     'postcode'     => '90210',
	 *     'country_id'   => 1,
	 *     'zone_id'      => 1,
	 *     'custom_field' => [],
	 *     'default'      => 0
	 * ];
	 *
	 * $this->load->model('customer/customer');
	 *
	 * $this->model_customer_customer->addAddress($customer_id, $address_data);
	 */
	public function addAddress(int $customer_id, array $data): int {
		$this->db->query("INSERT INTO `" . DB_PREFIX . "address` SET `customer_id` = '" . (int)$customer_id . "', `firstname` = '" . $this->db->escape($data['firstname']) . "', `lastname` = '" . $this->db->escape($data['lastname']) . "', `company` = '" . $this->db->escape($data['company']) . "', `address_1` = '" . $this->db->escape($data['address_1']) . "', `address_2` = '" . $this->db->escape($data['address_2']) . "', `city` = '" . $this->db->escape($data['city']) . "', `postcode` = '" . $this->db->escape($data['postcode']) . "', `country_id` = '" . (int)$data['country_id'] . "', `zone_id` = '" . (int)$data['zone_id'] . "', `custom_field` = '" . $this->db->escape(isset($data['custom_field']) ? json_encode($data['custom_field']) : json_encode([])) . "', `default` = '" . (!empty($data['default']) ? (bool)$data['default'] : 0) . "'");

		$address_id = $this->db->getLastId();

		if (!empty($data['default'])) {
			$this->db->query("UPDATE `" . DB_PREFIX . "address` SET `default` = '0' WHERE `customer_id` = '" . (int)$customer_id . "' AND `address_id` != '" . (int)$address_id . "'");
		}

		return $address_id;
	}

	/**
	 * Edit Address
	 *
	 * Edit address record in the database.
	 *
	 * @param int                  $customer_id primary key of the customer record
	 * @param int                  $address_id  primary key of the address record
	 * @param array<string, mixed> $data        array of data
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $address_data = [
	 *     'firstname'    => 'John',
	 *     'lastname'     => 'Doe',
	 *     'company'      => '',
	 *     'address_1'    => 'Address 1',
	 *     'address_2'    => 'Address 2',
	 *     'city'         => '',
	 *     'postcode'     => '90210',
	 *     'country_id'   => 1,
	 *     'zone_id'      => 1,
	 *     'custom_field' => [],
	 *     'default'      => 0
	 * ];
	 *
	 * $this->load->model('customer/customer');
	 *
	 * $this->model_customer_customer->editAddress($customer_id, $address_id, $address_data);
	 */
	public function editAddress(int $customer_id, int $address_id, array $data): void {
		$this->db->query("UPDATE `" . DB_PREFIX . "address` SET `firstname` = '" . $this->db->escape($data['firstname']) . "', `lastname` = '" . $this->db->escape($data['lastname']) . "', `company` = '" . $this->db->escape($data['company']) . "', `address_1` = '" . $this->db->escape($data['address_1']) . "', `address_2` = '" . $this->db->escape($data['address_2']) . "', `city` = '" . $this->db->escape($data['city']) . "', `postcode` = '" . $this->db->escape($data['postcode']) . "', `country_id` = '" . (int)$data['country_id'] . "', `zone_id` = '" . (int)$data['zone_id'] . "', `custom_field` = '" . $this->db->escape(isset($data['custom_field']) ? json_encode($data['custom_field']) : json_encode([])) . "', `default` = '" . (!empty($data['default']) ? (bool)$data['default'] : 0) . "' WHERE `address_id` = '" . (int)$address_id . "'");

		if (!empty($data['default'])) {
			$this->db->query("UPDATE `" . DB_PREFIX . "address` SET `default` = '0' WHERE `customer_id` = '" . (int)$customer_id . "' AND `address_id` != '" . (int)$address_id . "'");
		}
	}

	/**
	 * Delete Addresses
	 *
	 * Delete address records in the database.
	 *
	 * @param int $customer_id primary key of the customer record
	 * @param int $address_id  primary key of the address record
	 *
	 * @return void
	 *
	 * @example
	 *
	 * $this->load->model('customer/customer');
	 *
	 * $this->model_customer_customer->deleteAddresses($customer_id, $address_id);
	 */
	public function deleteAddresses(int $customer_id, int $address_id = 0): void {
		$sql = "DELETE FROM `" . DB_PREFIX . "address` WHERE `customer_id` = '" . (int)$customer_id . "'";

		if ($address_id) {
			$sql .= " AND `address_id` = '" . (int)$address_id . "'";
		}

		$this->db->query($sql);
	}

	/**
	 * Get Address
	 *
	 * Get the record of the address record in the database.
	 *
	 * @param int $address_id primary key of the address record
	 *
	 * @return array<string, mixed> address record that has address ID
	 *
	 * @example
	 *
	 * $this->load->model('customer/customer');
	 *
	 * $address_info = $this->model_customer_customer->getAddress($address_id);
	 */
	public function getAddress(int $address_id): array {
		$address_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "address` WHERE `address_id` = '" . (int)$address_id . "'");

		if ($address_query->num_rows) {
			// Country
			$this->load->model('localisation/country');

			$country_info = $this->model_localisation_country->getCountry($address_query->row['country_id']);

			if ($country_info) {
				$country = $country_info['name'];
				$iso_code_2 = $country_info['iso_code_2'];
				$iso_code_3 = $country_info['iso_code_3'];
				$address_format_id = $country_info['address_format_id'];
			} else {
				$country = '';
				$iso_code_2 = '';
				$iso_code_3 = '';
				$address_format_id = 0;
			}

			// Address Format
			$this->load->model('localisation/address_format');

			$address_format_info = $this->model_localisation_address_format->getAddressFormat($address_format_id);

			if ($address_format_info) {
				$address_format = $address_format_info['address_format'];
			} else {
				$address_format = '';
			}

			// Zone
			$this->load->model('localisation/zone');

			$zone_info = $this->model_localisation_zone->getZone($address_query->row['zone_id']);

			if ($zone_info) {
				$zone = $zone_info['name'];
				$zone_code = $zone_info['code'];
			} else {
				$zone = '';
				$zone_code = '';
			}

			return [
				'zone'           => $zone,
				'zone_code'      => $zone_code,
				'country'        => $country,
				'iso_code_2'     => $iso_code_2,
				'iso_code_3'     => $iso_code_3,
				'address_format' => $address_format,
				'custom_field'   => $address_query->row['custom_field'] ? json_decode($address_query->row['custom_field'], true) : []
			] + $address_query->row;
		}

		return [];
	}

	/**
	 * Get Addresses
	 *
	 * Get the record of the address records in the database.
	 *
	 * @param int $customer_id primary key of the customer record
	 *
	 * @return array<int, array<string, mixed>> address records that have customer ID
	 *
	 * @example
	 *
	 * $this->load->model('customer/customer');
	 *
	 * $results = $this->model_customer_customer->getAddresses($customer_id);
	 */
	public function getAddresses(int $customer_id): array {
		$address_data = [];

		// Country
		$this->load->model('localisation/country');

		// Address Format
		$this->load->model('localisation/address_format');

		// Zone
		$this->load->model('localisation/zone');

		$query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "address` WHERE `customer_id` = '" . (int)$customer_id . "'");

		foreach ($query->rows as $result) {
			$country_info = $this->model_localisation_country->getCountry($result['country_id']);

			if ($country_info) {
				$country = $country_info['name'];
				$iso_code_2 = $country_info['iso_code_2'];
				$iso_code_3 = $country_info['iso_code_3'];
				$address_format_id = $country_info['address_format_id'];
			} else {
				$country = '';
				$iso_code_2 = '';
				$iso_code_3 = '';
				$address_format_id = 0;
			}

			$address_format_info = $this->model_localisation_address_format->getAddressFormat($address_format_id);

			if ($address_format_info) {
				$address_format = $address_format_info['address_format'];
			} else {
				$address_format = '';
			}

			$zone_info = $this->model_localisation_zone->getZone($result['zone_id']);

			if ($zone_info) {
				$zone = $zone_info['name'];
				$zone_code = $zone_info['code'];
			} else {
				$zone = '';
				$zone_code = '';
			}

			$address_data[] = [
				'zone'           => $zone,
				'zone_code'      => $zone_code,
				'country'        => $country,
				'iso_code_2'     => $iso_code_2,
				'iso_code_3'     => $iso_code_3,
				'address_format' => $address_format,
				'custom_field'   => $result['custom_field'] ? json_decode($result['custom_field'], true) : []
			] + $result;
		}

		return $address_data;
	}

	/**
	 * Get Total Addresses
	 *
	 * Get the total number of address records in the database.
	 *
	 * @param int $customer_id primary key of the customer record
	 *
	 * @return int total number of address records that have customer ID
	 *
	 * @example
	 *
	 * $this->load->model('customer/customer');
	 *
	 * $address_total = $this->model_customer_customer->getTotalAddresses($customer_id);
	 */
	public function getTotalAddresses(int $customer_id): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "address` WHERE `customer_id` = '" . (int)$customer_id . "'");

		return (int)$query->row['total'];
	}

	/**
	 * Get Total Addresses By Country ID
	 *
	 * Get the total number of addresses by country records in the database.
	 *
	 * @param int $country_id primary key of the country record
	 *
	 * @return int total number of address records that have country ID
	 *
	 * @example
	 *
	 * $this->load->model('customer/customer');
	 *
	 * $address_total = $this->model_customer_customer->getTotalAddressesByCountryId($country_id);
	 */
	public function getTotalAddressesByCountryId(int $country_id): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "address` WHERE `country_id` = '" . (int)$country_id . "'");

		return (int)$query->row['total'];
	}

	/**
	 * Get Total Addresses By Zone ID
	 *
	 * Get the total number of addresses by zone records in the database.
	 *
	 * @param int $zone_id primary key of the zone record
	 *
	 * @return int total number of address records that have zone ID
	 *
	 * @example
	 *
	 * $this->load->model('customer/customer');
	 *
	 * $address_total = $this->model_customer_customer->getTotalAddressesByZoneId($zone_id);
	 */
	public function getTotalAddressesByZoneId(int $zone_id): int {
		$query = $this->db->query("SELECT COUNT(*) AS `total` FROM `" . DB_PREFIX . "address` WHERE `zone_id` = '" . (int)$zone_id . "'");

		return (int)$query->row['total'];
	}

}
